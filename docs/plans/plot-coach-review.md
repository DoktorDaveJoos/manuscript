# Plot Coach Review — Inconsistencies, Token Eaters, Missing Links, Bugs

Reviewed: `PlotCoachAgent`, `PlotCoachController`, the 8 registered tools, `PlotCoachBatchService`, `PlotCoachSessionSummarizer`, `PlotCoachWireSignals`, `BoardChangeObserver`, models, and the React surface (`ChatSurface`, `BatchProposalCard`). SDK integration points were verified against the installed `laravel/ai` vendor code (`maxConversationMessages()`, `temperature()`, `providerOptions()` system-block override, tool-result replay).

What checks out: the three-tier Anthropic cache layout is correctly wired (`array_merge($body, $providerOptions)` replaces the plain `system` string, so there is no double-send); `maxConversationMessages()` is a real SDK hook and is in sync with `ROLLING_DIGEST_TAIL_MESSAGES`; tool results (and therefore proposal uuids) ARE replayed in the conversation window, so free-text approvals can find the sentinel uuid; the proposal-id apply path genuinely prevents fabricated writes; undo previous-value capture is thorough (incl. the POV auto-attach pivot trap).

---

## Bugs

### B1 (high) — `[system:` prefixes misroute substantive turns to the cheapest model

`PlotCoachController::stream()` builds the message in this order: `handleApprovalSignals` → `prependBoardChangesNote` → `prependArchiveSummaryOnFirstTurn`, **then** calls `$agent->modelForTurn($message)`. `isTrivialTurn()` returns true for *any* message starting with `[system:` (PlotCoachAgent.php:252).

Consequences:
- Any user turn sent while `pending_board_changes` is non-empty (the author touched the plot board between turns — routine during plotting) gets the **board-changes prefix and is downgraded to `cheapestTextModel()`**, no matter how substantive the user's actual message is.
- The **first turn of a successor session** (archive handoff prepended) — the most context-critical turn — runs on the cheapest model.
- The **apply-failure note** explicitly demands "Diagnose and AUTO-CORRECT… call ProposeBatch again with the fix in the SAME turn" — a multi-step tool-calling recovery — yet routes to the cheapest model.

The docblock's assumption ("Even if more user content follows the prefix, the dominant work is acknowledgment") only holds for approval/cancel/undo notes. Fix: classify on the *raw* message before prepending, or only treat the approval-family notes as trivial. Tests cover `[system:` → trivial for acks, but not these interactions.

### B2 (medium) — ProposeBatch prefers the model-supplied `book_id` over the constructor binding

`ProposeBatch.php:60`: `$bookId = $this->coerceBookId($request['book_id'] ?? null) ?? $this->bookId;` — the request value wins; the comment directly above says the opposite ("Prefer the constructor-bound book id"). A hallucinated `book_id` (the prompts are full of example ids like 12, 42, 88) silently runs duplicate detection, enrichment, and `persistProposal` against **another book**; if that book has an active session, proposal rows land there. `findForSession` blocks the cross-session approval later, but the safety the constructor binding was built for is inverted. Swap the precedence.

### B3 (medium) — `sanitizeUserFacingContent` truncates at the first `]` inside a note

PlotCoachController.php:489–496 strips `[system: …]` by finding the **first** `]`. Notes legitimately contain `]`: apply-failure notes embed `$e->getMessage()`, the first-turn handoff embeds the entire archive summary, board-change notes embed free-text summaries. Everything after the first inner `]` leaks into the chat UI as fake user content on rehydrate. Needs balanced scanning or a structured delimiter.

### B4 (medium) — `sessionExport` leaks internal scaffolding; digests waste budget on it

`buildTranscriptMarkdown` dumps raw message content — `[system: …]` blocks and `APPROVE:batch:<uuid>` wire signals included — into the user-facing markdown export. The UI sanitizes these; the export doesn't. Same root cause: `buildTranscriptDigest` and `buildInSessionDigest` spend their per-message char budget on `[system:` scaffolding instead of real conversation. The sanitizer is private to the controller — extract it (e.g. into `PlotCoachWireSignals`) and reuse in the summarizer.

### B5 (medium) — Unknown write types pass the preview, then explode at approval

`ProposeBatch::handle` silently skips unknown types when grouping the preview, but `persistProposal` stores the **full** writes array. On approval `PlotCoachBatchService::dispatch` throws `Unknown write type` and the whole batch fails — *after* the user approved a clean-looking card whose item count didn't even include the bad write. The preview should reject unknown types loudly, like the chapter-link validator already does.

### B6 (low) — ProposeChapterPlan silently drops malformed chapters

`normalizeChapter` returns null for a missing `title`/`storyline_id` and the chapter vanishes from the preview with no signal to the model. A proposal can persist with fewer chapters than the model intended — or zero writes.

### B7 (low) — Tools resolve `activeForBook`, not the streaming session

`resolveSession` lets the client stream into *any* session by id (including archived ones), but `ProposeBatch`, `ProposeChapterPlan`, `ApplyPlotCoachBatch`, and `UndoLastBatch` all re-resolve `PlotCoachSession::activeForBook()`. Chatting in an archived session while another is active: proposals persist on the *other* session, approvals fail with "proposal does not exist", and `UndoLastBatch` reverts the **active** session's latest batch from inside an unrelated conversation. Either restrict `stream()` to the active session or pass the agent's session into the tools.

### B8 (low) — `undo_window_expires_at` is written but never enforced

Set on every batch (`UNDO_WINDOW_MINUTES = 30`), checked nowhere — not in `undo()`, `undoBatch()`, the controller, or the frontend. Enforce it or drop the column and constant.

### B9 (low) — Raw wire signal kept in the LLM turn

`handleApprovalSignals` prepends the note but keeps `APPROVE:batch:<uuid>` in the message (`"{$note}\n\n{$message}"`). The agent docblock claims the agent "only sees the [system:…] note" — false. The model is handed exactly the uuid the persona forbids it to echo, and it persists in history. Strip matched signals.

### B10 (low) — Undo doesn't detach pivots added to a *reused* chapter

`deleteWrite` returns early for `chapter` writes with `reused: true` (correct — never delete pre-existing chapters), but the `syncWithoutDetaching` additions from the batch (beats/characters/wiki entries) are not captured or reverted. Undo of a reused-chapter batch leaves the new attachments in place.

---

## Inconsistencies (prompt ↔ tools)

### I1 — `book_id` is contradicted three ways

- Plotting guidance: "ProposeBatch / ApplyPlotCoachBatch / GetPlotBoardState … bind book_id at construction; **you don't pass it in the call**" — but ProposeBatch's schema declares `book_id` as **required, non-nullable**. Strict providers force the model to send it anyway, and per B2 the sent value *wins*.
- Refinement guidance: "ProposeChapterPlan **and ApplyPlotCoachBatch** both require `book_id` — always pass {bookId}" — ApplyPlotCoachBatch's schema has **no `book_id` field at all**.
- Only ProposeChapterPlan actually consumes a payload `book_id`. Align the schemas with the guidance (make ProposeBatch's `book_id` nullable or drop it; fix the refinement sentence).

### I2 — Two of three stage prompts steer toward the legacy fabricable-writes apply path

Persona: "Do NOT re-emit the writes array … Passing only `proposal_id` is the safest call shape." But plotting guidance says "When approved, call ApplyPlotCoachBatch **with the same writes**," and refinement guidance says "call ApplyPlotCoachBatch **with the exact writes array** from the ProposeChapterPlan sentinel." That's the legacy path the tool description itself de-recommends, and it bypasses the can't-fabricate guarantee.

### I3 — Dead stages, and an unreachable Refinement stage

`PlotCoachStage::Structure` / `Entities` produce **empty** stage guidance, yet `coerceStage` accepts them — and the prompt never enumerates valid stages, so a model guessing `{"stage":"structure"}` strands the session in a guidance-less stage. Meanwhile no guidance anywhere documents the plotting → refinement transition; `refinementGuidance()` (the chapter-handoff playbook) is unreachable through any instructed path.

### I4 — `isTrivialTurn` docblock vs reality

"the controller intercepts before the LLM runs — the agent only sees the [system: …] note that follows" — wrong on both ends: the raw signal stays in the turn (B9), and non-approval `[system:` notes also trigger the trivial path (B1).

---

## Token eaters

### T1 — The rolling digest is effectively unbounded

`ROLLING_DIGEST_BUDGET = 4000` chars is defeated by the per-message floor: `max(80, 4000 / count)` × N pre-tail messages = **80 chars × N** once N > 50. The digest grows linearly forever: ~100 user turns ≈ 14K chars (~4K tokens) per request; by the 250-turn threshold the guidance anticipates, ~38K chars (~10K tokens) — re-sent every turn and re-cache-written (1.25×) on every refresh and every bible invalidation (i.e., every applied batch). Cap the digest by *dropping/eliding oldest messages* once the budget is hit, not by shrinking per-message budgets toward the floor.

### T2 — Parent handoff is paid twice

`parentHandoffBlock()` puts the parent's archive summary in the system prompt on **every turn for the lifetime of the child session**, while `prependArchiveSummaryOnFirstTurn()` injects the **same summary** into the first user message — which then sits in the 20-message replay window for ~10 turns. Pick one (the system-prompt block is the cacheable one; the first-turn injection is redundant given it).

### T3 — Each proposal is carried twice and replayed for ~10 turns

A ProposeBatch tool result = full markdown preview + sentinel JSON of the *identical* writes (plus the `_existing_*` enrichment fields persisted into the sentinel). Tool results are replayed verbatim in the 20-message window, so every recent proposal costs 2× its writes for ~10 turns. The sentinel is needed (frontend + free-text approval uuid); the markdown half could be slimmed or the sentinel trimmed to `proposal_id` + summary in the *stored* turn (the frontend re-merges from `tool_results` on rehydrate anyway).

### T4 — `RetrieveManuscriptContext` duplicates the bible block almost entirely

Default call dumps story bible + all characters with full descriptions + all plot points + all chapter summaries — nearly all of which is already inlined in `saved_entities`. The tool appears in `tools()` but **no plot-coach guidance ever mentions it** (the don't-refetch rules cover only `GetEntityDetails`/`GetPlotBoardState`/`LookupExistingEntities`), and its enticing description makes a stray multi-thousand-token redundant call likely; with `chapter_id` it also dumps full chapter prose. Either drop it from the plot coach's toolset or add it to the don't-refetch guidance.

### T5 — Summarizer runs every turn for short sessions

Until the conversation exceeds 20 messages, `rollingDigestText()` finds `$stored === ''`, re-reads the whole conversation, and issues a session UPDATE every turn (`rolling_digest_through_turn` churns). Cheap but pointless — gate on message count or only persist when the digest is non-empty.

---

## Missing links

### M1 — Storyline + chapters in one batch is impossible as instructed

Refinement guidance demands proposing the storyline **in the same batch** as the first chapters ("do not split it into two round-trips… chapters can reference the storyline by id once it's persisted in-batch") — but the model cannot know the auto-increment id of a storyline that doesn't exist yet, and chapter writes have **no `storyline_name` fallback** (unlike beats' `plot_point_title`). As instructed, the flow is a guaranteed apply failure with the workaround explicitly forbidden. Add `storyline_name` resolution to `writeChapter`/`updateChapter` (mirroring `resolveBeatPlotPointId`), then the guidance becomes true.

### M2 — `cost_cents` is never computed

Exposed by `sessionIndex`/`sessionShow` and typed in `ArchiveDrawer`, but nothing writes it — `attachPostStreamHooks` updates only token counters. Compute it there or remove it from the API/UI surface.

### M3 — Continuity gap between digest and replay window

Digest refreshes every 5 user turns; the verbatim window slides every turn. Between refreshes, up to ~8 messages have fallen off the tail but aren't in the digest yet — in neither context, precisely the recent-decision zone. Make the digest *overlap* the tail (cover everything older than tail-minus-refresh-interval) so staleness never creates a hole.

### M4 — Chapter-planning rules live only in the Refinement prompt

`ProposeChapterPlan` is registered in **all** stages and its strict `character_ids`/`wiki_entry_ids` requirements are enforced regardless of stage, but the per-chapter checklist exists only in `refinementGuidance()` — which is unreachable (I3). During plotting the model only has the tool description to go on (decent, but the retry loop costs turns). Either gate the tool by stage or surface a one-line pointer in plotting guidance.

---

## Suggested priority

1. **B1** (model misrouting — quality regression on common turns) + **I2/I1** (prompt-tool contract drift — these are pure prompt/schema edits).
2. **M1** (instructed-but-impossible storyline batch) + **B5/B6** (propose-time validation gaps).
3. **T1/T2** (unbounded digest, doubled handoff) — biggest steady-state token wins.
4. **B2, B3, B4, B7** — correctness hardening.
5. **B8, B9, B10, M2, M3, M4, T3, T4, T5, I3, I4** — cleanups.
