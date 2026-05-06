# Plot Coach: Solid Entity Linking on Proposed Chapters

**Date**: 2026-05-06
**Status**: Draft
**Branch**: `feat/plot-coach` (continuation work)

## Overview

Make the plot coach reliably populate `character_ids` and `wiki_entry_ids` on every chapter it proposes. Today both fields are optional in `ProposeChapterPlan` and `ProposeBatch`; the LLM omits them silently — in the live "Voyage" book, 31 of 32 chapters have empty `character_chapter` / `wiki_entry_chapter` pivots despite the book containing 11 characters and 18 wiki entries the chapters reference. The wiki panel filters strictly by these pivots, so it correctly shows nothing — the bug is upstream, in the proposal pipeline.

Failure must become loud (the tool returns a structured rejection and the agent retries) instead of silent (chapter persists with empty pivots and the editor's wiki panel goes blank). Beats are the source of truth for what's "in" a chapter; if a beat's description references a known book entity, the chapter that owns the beat must declare it.

## Decisions (from brainstorming)

1. **Strategy**: Schema/prompt tightening (A) + tool-level existence-check validation (B). No server-side fallback inference (rejected — too lossy, masks LLM bugs). No manual editor UI as workaround (rejected — "the plot coach must be solid").
2. **Validation shape**: existence check. If the server-side scanner finds ≥1 known character name in the chapter's beat descriptions, the agent's `character_ids` must be non-empty; same rule for wiki entries. The agent picks *which* specific ids — the server only enforces that *something* was attempted when beats clearly reference book entities.
3. **POV character**: auto-added to the `character_chapter` pivot at chapter persist time. POV lives in two places (FK + pivot row); the wiki panel reads from the pivot, so this guarantees POV is always visible there. Agent's `character_ids` continues to mean *supporting cast only* — POV is added implicitly server-side.
4. **Backfill**: none. The Voyage book stays in its current state and serves as the live test case for the improved agent. No migration, no retroactive tool, no auto-fix on read.

## Out of Scope

- Backfilling existing books (Voyage included).
- Manual UI in the editor's wiki panel to attach/detach entries by hand.
- Server-side fallback inference (auto-attaching matched entities when the agent submits empty arrays).
- Coverage / set-equality validation modes — only the existence check is enforced.
- Aliases or alternate-name matching. The matcher uses the canonical `name` field. (If aliases become a problem in practice, addressed in a follow-up.)
- Manual chapter creation paths (`ChapterController`) — the validation only applies to plot-coach-driven writes.

## Architecture

### Components

**1. `BeatEntityScanner` (new utility, `app/Ai/Support/BeatEntityScanner.php`)**

Pure stateless service. One method:

```php
public function findReferenced(
    array $beatDescriptions, // list<string>
    array $entities,         // list<array{id: int, name: string}>
): array                     // list<array{id: int, name: string, beats: list<int>}>
```

Matching rules:
- Concatenate `$beatDescriptions` into a single search corpus, but track which beat index each match originated from so the rejection message can cite specific beats.
- Case-insensitive Unicode-aware word-boundary regex: `'/(?<![\p{L}\p{N}])'.preg_quote($name, '/').'(?![\p{L}\p{N}])/iu'`. Handles German umlauts, "Apparat", "Voyager", etc.
- Skip entities whose `name` is shorter than 3 characters (avoids "AI", "I", "Z" type collisions).
- Returns matched entities only; non-matches are dropped.

**2. Validation in `ProposeChapterPlan` and `ProposeBatch`**

In `ProposeChapterPlan::handle()`, after parsing the chapter list and before producing the preview:

```
for each proposed chapter:
    load beats via beat_ids from the book
    load all characters and wiki entries for the book (id+name only)
    referencedChars  = scanner.findReferenced(beatDescriptions, characters)
    referencedWiki   = scanner.findReferenced(beatDescriptions, wikiEntries)
    if referencedChars not empty AND character_ids is empty/missing → reject
    if referencedWiki  not empty AND wiki_entry_ids is empty/missing → reject

if any chapter rejected → return structured tool error, do not produce preview
else → produce preview as today
```

`ProposeBatch::handle()` has the same logic for any write of `type: chapter`. Code is shared via a `ValidatesChapterEntityLinks` trait in `app/Ai/Tools/Plot/Concerns/` (matching the existing `CoercesBookId` / `DecodesJsonPayload` trait pattern in that folder).

**3. Schema and instruction tightening**

In both `ProposeChapterPlan` and `ProposeBatch` chapter writes, update the JSON-shape documentation in the tool description to mark `character_ids` and `wiki_entry_ids` as **required** arrays. Empty arrays are valid only when no beats reference known entities — this is documented in the description and enforced by validation.

In `PlotCoachAgent::buildInstructions()`, in the chapter-proposal guidance block (currently the `Pull these from the beat descriptions...` section):
- **Remove** the line "POV does NOT need to be repeated here — it's a separate pivot." It's now misleading: POV is auto-added server-side, and conflating that with "agent doesn't need to think about cast" weakens the per-chapter cast-listing discipline.
- **Replace** the chapter-proposal guidance with an explicit per-chapter checklist:
  > For every chapter you propose:
  > 1. Read each attached beat's description.
  > 2. List every supporting character named or strongly implied as `character_ids` (POV is handled server-side; do not include it).
  > 3. List every location, item, organization, or lore concept named as `wiki_entry_ids`.
  > 4. Empty arrays are valid only when no beats reference any known entity. Otherwise the tool rejects the proposal.
- **Add** a fully-populated example chapter in the schema docstring so the agent has a concrete pattern to copy.

**4. POV auto-include in `PlotCoachBatchService`**

At the chapter create path (around `validateCharacterIds` / pivot sync) and the chapter update path: after syncing `character_ids` to the `character_chapter` pivot, if `pov_character_id` is set on the chapter:

```php
$chapter->characters()->syncWithoutDetaching([$povId]);
```

`syncWithoutDetaching` preserves any existing pivot rows the agent submitted and adds POV if missing. Existing chapters created before this change are unaffected (the auto-include only fires on a new write or update).

If the `character_chapter` pivot has additional columns (e.g. `role`), POV gets the column's default. The migration is checked during implementation; no schema change is part of this design.

### Error handling

The validation rejection is returned as a tool result string, not an exception. The Laravel AI SDK passes it back to the agent as the tool's response, the agent self-corrects within the same conversational turn (or the next), and the user only ever sees the final approved preview.

Rejection message shape:

```
ProposeChapterPlan rejected — chapter entity links are missing.

Chapter "Madeira: Der Apparat tritt an den Fund" (index 1):
  - character_ids is empty, but beats reference these characters from the bible:
      • John (id=42) — beat "Voss erteilt den Befehl"
      • Maja (id=44) — beats "Madeira: Apparat-Anflug", "Vor-Ort-Übergabe"

Chapter "Maja auf der Laufbahn" (index 3):
  - wiki_entry_ids is empty, but beats reference these entries:
      • Voyager Probe (id=12) — beat "Maja's Workout"
      • Apparat (id=18) — beats "Voss morning briefing"

Retry with the referenced entities included. If a specific match is incidental
(e.g. mentioned only in dialogue about a different scene), you may omit it —
but the lists cannot be empty when matches exist.
```

No retry-count fallback. The agent loop in the Laravel AI SDK is responsible for handling tool errors; the LLM's own pattern recognition typically resolves the rejection within one or two retries when given a structured error. If thrashing becomes a real-world problem (manifests as slow turns or visible loops in the user's chat), address it in a follow-up — premature graceful-degradation engineering would let bad data through and defeat the validation's purpose.

## Testing

- **Unit (`tests/Unit/Ai/Support/BeatEntityScannerTest.php`)**:
  - case-insensitive match
  - Unicode word boundaries (German umlauts, compound words)
  - "John" does not match inside "Johnson"
  - short-name skip (≥3 char rule)
  - empty inputs return empty
  - duplicate entity names dedupe by id
  - tracks which beat each match came from

- **Feature (`tests/Feature/Ai/Plot/ProposeChapterPlanValidationTest.php`)**:
  - rejects when a beat references a book character but `character_ids` is empty
  - rejects when a beat references a book wiki entry but `wiki_entry_ids` is empty
  - accepts when no entities referenced (empty lists valid)
  - accepts when entities referenced and lists populated (specific picks not enforced)
  - rejection message includes the chapter title, the missing entity ids/names, and the specific beat titles that referenced them

- **Feature (`tests/Feature/Ai/Plot/ProposeBatchChapterValidationTest.php`)**:
  - same coverage for `type: chapter` writes via `ProposeBatch`
  - non-chapter writes in the same batch are unaffected

- **Feature (`tests/Feature/Ai/Plot/PovAutoIncludeTest.php`)**:
  - persisting a chapter with `pov_character_id` set adds POV to the `character_chapter` pivot
  - persisting a chapter with `pov_character_id` AND non-empty `character_ids` keeps both POV and supporting cast in the pivot (no detaching)
  - updating a chapter to clear `pov_character_id` does not detach the previous POV (consistent with `syncWithoutDetaching` semantics — out of scope to remove POV here; if user wants that, it's a separate write)

- **No browser test** — no UI changes. Verification of the wiki panel showing populated entries happens implicitly via the fixed pivots.

## Files Affected

- `app/Ai/Support/BeatEntityScanner.php` *(new)*
- `app/Ai/Tools/Plot/Concerns/ValidatesChapterEntityLinks.php` *(new — shared trait, mirrors `CoercesBookId` / `DecodesJsonPayload` shape)*
- `app/Ai/Tools/Plot/ProposeChapterPlan.php` *(schema doc, validation call)*
- `app/Ai/Tools/Plot/ProposeBatch.php` *(schema doc, validation call for `type: chapter`)*
- `app/Ai/Agents/PlotCoachAgent.php` *(rewrite the chapter-proposal guidance block: remove the misleading POV line, replace with the per-chapter checklist, add a fully-populated example)*
- `app/Services/PlotCoachBatchService.php` *(POV auto-include in `writeChapter` create + update paths)*
- `tests/Unit/Ai/Support/BeatEntityScannerTest.php` *(new)*
- `tests/Feature/Ai/Plot/ProposeChapterPlanValidationTest.php` *(new)*
- `tests/Feature/Ai/Plot/ProposeBatchChapterValidationTest.php` *(new)*
- `tests/Feature/Ai/Plot/PovAutoIncludeTest.php` *(new)*

## Verification Plan

1. `php artisan test --compact tests/Unit/Ai/Support tests/Feature/Ai/Plot` — all green.
2. Run `vendor/bin/pint --dirty --format agent`.
3. Open the live Voyage book in the running NativePHP app and re-run the plot coach on a chapter (e.g. ask "redo the chapter plan for chapters 270–280"). Confirm:
   - Agent retries internally if its first attempt has empty entity arrays.
   - Approved proposal lands with non-empty `character_chapter` and `wiki_entry_chapter` rows for chapters whose beats reference book entities.
   - Wiki panel in the editor for those chapters now displays the linked entries.
4. Confirm the tool's rejection messages surface in the plot-coach session log (visible via `read-log-entries` MCP tool) for inspection.

## Risks and Mitigations

- **False positives in matching** (e.g. character name appears in dialogue about another scene): mitigated by the existence-check shape — the agent picks specific ids, server only enforces non-empty. If a beat genuinely doesn't put a character in the chapter, the agent omits them and the validation passes (matches exist but the list is non-empty with the correct cast).
- **Agent thrashing on persistent rejection**: not mitigated up front — relying on the LLM to act on structured error messages. If observed in production, address with a session-scoped retry cap as a follow-up.
- **POV `syncWithoutDetaching` adding default pivot column values that break referential meaning** (e.g. role column): the migration must be inspected during implementation; if the default is wrong, set the column explicitly when attaching. Out-of-scope risk if the column is plain.
- **Performance** (scanning all beats for all entities on every proposal): trivial — typical book has < 50 entities and < 30 beats per chapter; regex scan is sub-millisecond.
