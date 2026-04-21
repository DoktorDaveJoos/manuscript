# Plot Coach

Interactive AI-coached plotting. A creative dialogue between the author and an editorial-grade AI that shapes a book's plot structure, characters, storylines, and beats on the existing plot board. Hop-on/hop-off, resumable, per-book.

Status: Design locked (grilled 2026-04-21). Pencil designs landed. Phase 1 in progress.

Branch: `feat/plot-coach` off `dev`.

## Phase 1 status

Foundation + agent skeleton + frontend shell landed on `feat/plot-coach` (commits `efb76a4f`, `83d5ceb7`, `4c253d35`). Next: Phase 2 (mutation & batch flow).

## Pencil designs

All ten views live in `untitled.pen`, grouped around (x=-9052, y=18408). Node IDs:

| # | Name | Node ID |
|---|---|---|
| PC1 | Coach Mode (Mid-Dialogue) | `UHAz7` |
| PC2 | Intake Empty State + Coaching-Mode Picker | `f9VoD` |
| PC3 | Batch Approval (Plan Mode) | `RdKEY` |
| PC4 | Speculative Bursts | `IOS8n` |
| PC5 | Board Mode + Floating Entry | `y3Oja` |
| PC6 | Ungated / No AI Configured | `zwoTo` |
| PC7 | Resume State (24h+ gap) | `bw8SH` |
| PC8 | Session Archive | `kHhhM` |
| PC9 | Template Remap Preview (modal) | `wuyI2` |
| PC10 | Chapter Plan Preview | `BQxr0` |

Design primitives: Geist typeface, copper accent `$accent` (#B87333), warm off-white `$bg` (#FAFAF7), hairline borders `$border-light` (#ECEAE4) / `$border` (#E8E6E0). Chat content max-width 720px for comfortable reading. Mode toggle keyboard shortcut: `⌘\`.

---

## Intent

The author ping-pongs with an AI that plays the role of a developmental editor who knows plot structures (Save the Cat, 3-Act, Hero's Journey, Fichtean, Seven-Point, etc.), acts, plot points, and beats. The AI guides the author through the decisions that must be made (genre, length, premise, protagonist, conflict), proposes 2–3 candidate structures, then plots the book collaboratively. As decisions firm up, AI proposes batches of entities (characters, storylines, plot points, beats) that the author approves in chat, plan-mode style. The board fills in as the dialogue advances. After plotting completes, the AI can draft a chapter plan, stubbing chapters that link to beats.

The conversation is the primary surface. The board is the residue of decisions that stuck.

---

## Scope boundary

- **In:** AI-coached creation of plot structure + plot points + beats + storylines + characters + wiki entries, with chat-based batch approval. Chapter stubbing. Structural critique. Template remapping with preview. Resume logic. Cost attribution.
- **Out:** AI-written chapter prose. Editorial review of written chapters (that's `EditorialChatAgent`). Post-hoc analysis of finished manuscripts (that's `ManuscriptAnalyzer` / `EntityExtractor`).

---

## Decisions log

### Layout
- **Surface:** dedicated Coach panel on `plot/index.tsx`, not a secondary sidebar.
- **Mode toggle (`⌘\`):** Coach mode (chat dominant, board shrinks to summary strip) ↔ Board mode (board full, chat collapses to floating entry bar).
- **Default landing:** Coach mode if active unfinished session exists, Board mode otherwise.

### Session model
- **One active session per book + archived sessions.** Table `plot_coach_sessions` with partial-unique `(book_id)` where `status = 'active'`. FK to `agent_conversations`.
- **Resume by AI-config state:**
  - AI configured + `ai_enabled` + prior session: straight scrollback, no resume card. If gap ≥ 24h, one deterministic AI resume-opener message.
  - AI configured + no prior session: Coach mode, warm intake opener.
  - No AI / not PRO: Board mode. Coach panel visible-but-inert with CTA. Read-only transcript access if prior sessions exist.

### Resume state (what AI carries across sessions)
- Structured state blob (JSON) on `plot_coach_sessions.decisions`: stage, coaching_mode, genre, target_length, structure_template, premise, protagonist notes, conflict, open threads.
- Recent message window (last ~20 turns) from `agent_conversations`.
- Board state fetched on demand via `GetPlotBoardState` tool — not prepended.

### Intake blockers (before structure selection)
1. Genre (reuse `Book.genre` + `secondary_genres`, confirm if set)
2. Target length (reuse `Book.target_word_count`)
3. Premise — **new column `books.premise` VARCHAR(500) nullable**
4. Protagonist sketch (name, core want, core wound)
5. Central conflict (internal vs external, stakes)
6. Coaching mode (`suggestive` vs `guided`) — stored on session

Nice-to-have (adaptive): POV, tense, setting, tone/themes, comps, series info.
Skip intake if blockers 1–5 are already satisfied from prior work.

### Structure selection
- After intake, AI presents **2–3 candidate templates** with its top pick bolded. User confirms one.
- **Switching templates mid-session:** allowed, via `ProposeTemplateRemap` tool that shows a diff preview before applying (beat-level best-effort remap; orphan beats become unassigned).

### Batching & plan mode
- **Claude-Code-style plan mode:** AI accumulates intended writes during dialogue, proposes batch summary in chat, user approves via chip or free text.
- **Triggers:** AI judges the moment (post-agreement, post-decision, pre-hop-off) — **not stage-gated.** Micro-commits (1-item batches) are first-class.
- **Apply:** single transactional tool call `ApplyPlotCoachBatch`. Failure rolls back entire batch; AI re-proposes with collision resolved.
- **Partial accept:** via free text ("approve but skip storyline") — AI re-proposes.
- **Undo:** one-click "Undo last batch" in Coach panel header. Backed by `plot_coach_batches` table; reverses most recent applied batch in a transaction.

### AI writes
- **Reuse `ai_description` convention** (`HasDualDescription` trait). AI writes only `ai_description`, never `description`. Applies to `Character`, `WikiEntry`.
- **Modifications:** AI can update `ai_description` freely even after user edits `description`. Name is user-sovereign once user has edited (no silent renames).
- **Speculative mode:** AI can post "brainstorm bursts" — 2–3 hairline-bordered lightweight idea cards inline in chat. Single click on a small icon (`arrow-right`) elevates an idea to a real AI turn. No emoji reactions. Ignored cards stay quietly in scrollback.

### Voice
- Editorial coach persona: curious collaborator, not project manager. Short sentences. Real questions. Allowed to push back and have opinions. No "As an AI..." / "I can help with..." helper-speak. Never narrate structured state unsolicited.

### Coaching mode toggle
- Asked at second AI message: "Pitch freely" / "Keep it structural" / free-text ("mix — only when I ask").
- Stored on `plot_coach_sessions.coaching_mode`.
- Changeable mid-session via chat or settings cog in Coach panel header. Change logged in `decisions`.
- In Guided mode, `ai_description` is a neutral synthesis of prior conversation, no invented traits. In Suggestive mode, AI can enrich.

### Board ↔ chat sync
- Board edits (drag-drop, rename, delete) while a session is active append to `pending_board_changes` queue (JSONB) on the session.
- On next stream turn, controller prepends a system message summarizing the queue, then clears it. AI naturally acknowledges in its reply. Changes beyond 10 edits collapse into a summary.
- Manual user edits do **not** grant AI permission to skip batch approval for equivalent future writes.
- Above the chat input, non-dismissible line: "3 board changes since last turn" — disappears after next AI response.

### Chapter handoff
- **Assisted, opt-in, never auto.** AI proposes chapter plan; user approves via batch flow; chapter stubs created.
- **Stub default:** empty content, metadata only (title, `act_id`, linked `beats`, `pov_character_id`, `storyline_id`). Per-book preference to seed with "intent summary" paragraph from beat descriptions. Never full AI-drafted prose.
- **Triggers:** user-initiated ("turn this into chapters"), AI-initiated when state is ready (beats have titles + descriptions), or visible "Draft chapter plan" button in Coach panel header.
- **N:1 beat→chapter supported.** Cross-storyline chapters supported. AI's proposal must respect both.
- **Idempotency:** additive only. If chapters exist, propose extending, never silently renaming or deleting user-created chapters.

### Long-session management
- Rolling message window (last ~20 turns) + structured state + on-demand board snapshot.
- **Soft threshold** at 60 user-turns: AI mentions once "happy to keep going, or summarize and start fresh?"
- **Hard threshold** at 120 user-turns or >80% context: AI proactively proposes archive.
- On archive: structured YAML-ish summary (decisions, characters, structure, open threads, last topic) injected as system message into new session. Archived session read-only.
- Future: `LookupArchivedSession` tool for cross-session references. Not in v1.

### Agent architecture
- **New `PlotCoachAgent`** at `app/Ai/Agents/PlotCoachAgent.php`. Implements `Agent`, `Conversational`, `HasTools`, `HasMiddleware`, `RemembersConversations`.
- **New tool namespace** `app/Ai/Tools/Plot/`: `GetPlotBoardState`, `ProposeBatch` (no writes), `ApplyPlotCoachBatch` (transactional commit), `UndoLastBatch`, `ProposeTemplateRemap`, `ProposeChapterPlan`.
- Shared tools (`LookupExistingEntities`, `RetrieveManuscriptContext`) stay at `app/Ai/Tools/` and are composed in by the coach.
- **Single system prompt** dynamically composed from: static persona + stage-specific guidance + runtime state injection.
- **Streaming:** reuse `StreamsConversation` trait. New `PlotCoachController@stream`.
- **Model tier:** user's configured provider/model as-is. Non-blocking intake note recommends a reasoning-class model if a small/cheap one is set.

### i18n
- UI strings localized to en/de/es at phase landing (`plot-coach.json` per locale).
- AI system prompt in English only; locale passed as context; AI responds in user's language; AI-generated DB content (ai_description, beat titles, plot point titles) written in user's locale directly.

### Cost & telemetry
- Attribute tokens to existing `Book.ai_input_tokens`, `ai_output_tokens`, `ai_cost` counters.
- Per-session subtotals: new nullable columns on `plot_coach_sessions` — `input_tokens`, `output_tokens`, `cost_cents`. Surfaced in session archive.

### Transcript export
- Markdown download from session archive view. Conversation + final decisions summary + entities created. Not part of manuscript export (docx/epub/pdf/kdp/txt).

---

## Data model

New tables / columns:

1. **`books.premise`** — VARCHAR(500) nullable. New column.

2. **`plot_coach_sessions`** — new table:
   - `id` bigint PK
   - `book_id` FK → books (cascade)
   - `agent_conversation_id` FK → agent_conversations (restrict)
   - `status` enum(`active`, `archived`)
   - `stage` enum(`intake`, `structure`, `plotting`, `entities`, `refinement`, `complete`)
   - `coaching_mode` enum(`suggestive`, `guided`)
   - `decisions` JSON (genre, target_length, structure_template, premise, protagonist sketch, conflict, open threads, mode-change log)
   - `pending_board_changes` JSON (queue of unacknowledged board edits)
   - `input_tokens` int nullable
   - `output_tokens` int nullable
   - `cost_cents` int nullable
   - `archived_at` timestamp nullable
   - `created_at`, `updated_at`
   - Partial unique index on `(book_id)` where `status = 'active'` (SQLite `WHERE` partial index).

3. **`plot_coach_batches`** — new table:
   - `id` bigint PK
   - `session_id` FK → plot_coach_sessions (cascade)
   - `summary` text (human-readable chat preview)
   - `payload` JSON (full diff of what was applied — reverse-engineerable)
   - `applied_at` timestamp
   - `reverted_at` timestamp nullable
   - `undo_window_expires_at` timestamp (soft; UI may hide Undo after expiry)
   - `created_at`, `updated_at`

No changes to `acts`, `plot_points`, `beats`, `storylines`, `characters`, `wiki_entries`, `chapters`.

**Migration reminder:** run against BOTH databases per CLAUDE.md:
- `php artisan migrate`
- `DB_DATABASE=database/nativephp.sqlite php artisan migrate --no-interaction` (or `php artisan native:migrate`)

---

## Phased rollout (three PRs to `dev`)

### Phase 1 — Foundation (no user-visible mutation)

- ✓ Migrations: `plot_coach_sessions`, `plot_coach_batches`, `books.premise`.
- ✓ Models: `PlotCoachSession`, `PlotCoachBatch`.
- ✓ `PlotCoachAgent` skeleton with read-only tools: `GetPlotBoardState` + shared `LookupExistingEntities` + shared `RetrieveManuscriptContext`.
- ✓ `PlotCoachController` with `stream`, `sessionIndex`, `sessionShow`, `sessionArchive` endpoints.
- ✓ Wayfinder route generation.
- ✓ Coach panel UI shell on `plot/index.tsx` with `⌘\` mode toggle.
- ✓ Gate/degradation rendering (the three AI-config states).
- Intake stage prompt composition (blockers 1–6 handled in dialogue, no board writes yet).
- ✓ i18n strings for en/de/es.

Tests:
- ✓ `tests/Feature/PlotCoachControllerTest.php` (required by guardrails).
- ✓ `tests/Feature/Ai/PlotCoachAgentTest.php` — agent registration, prompt composition, tool set correctness.
- ✓ `tests/Browser/PlotCoachTest.php` (required by guardrails).
- Agent can hold a full intake conversation without writes. Streaming works. Resume works.

### Phase 2 — Mutation & batch flow

- Tools: `ProposeBatch` (no DB writes), `ApplyPlotCoachBatch` (transactional), `UndoLastBatch`, `ProposeTemplateRemap`.
- Chat-based batch approval UX (chips + free-text parse → re-propose).
- Speculative-card rendering in chat.
- Board-change sync queue (`pending_board_changes` + system-message prepend on next turn).
- Undo button in Coach panel header.
- `ai_description` writes for AI-proposed characters and wiki entries.
- Coaching-mode toggle UI (intake chip + settings cog).

Tests:
- Batch transactional atomicity (roll back on collision).
- Undo correctness (no orphan rows, no silent re-creation).
- Speculative cards don't persist unless elevated.
- Board-edit queue flushes on next turn.

### Phase 3 — Handoff, polish, archive

- `ProposeChapterPlan` tool + chapter-stub batch apply.
- Soft/hard long-session thresholds + archive flow.
- Archived-session read-only access; archive → new-session summary injection.
- Transcript markdown export.
- Per-book "seed stub with intent summary" preference.
- Session archive index page.

Tests:
- Chapter handoff idempotency (additive on re-run, no silent deletes).
- Archive → resume preserves decisions.
- Transcript export fidelity.

---

## Risks & mitigations

- **Token bloat on long sessions** → structured state + rolling window + archive threshold (Q14).
- **User trust in AI-written entities** → dual-description convention (`ai_description`), batch approval in chat, cheap one-click undo.
- **Stale AI mental model after board edits** → deterministic queued system-message injection (Q11).
- **Destructive template switches** → remap-with-preview, never destructive-wipe (Q6c).
- **Coach feels like a wizard** → no stage-gated batches; AI judges the moment; speculative bursts are first-class (Q7.1).
- **NativePHP dual-database drift** → migrations must hit both; document in each migration PR description.

---

## Open items (intentionally deferred)

- Cross-session lookup (`LookupArchivedSession` tool) — add when user need materializes.
- Analytics on coach effectiveness (retention, batch-approval rate, session depth). Instrument passively; analyze later.
- Collaborative plotting (multi-author) — out of scope; current app has no multi-user.

---

## References

- Existing plot system: `app/Models/{Act,PlotPoint,Beat,PlotPointConnection}.php`, `app/Services/PlotTemplateService.php`, `resources/js/Pages/plot/index.tsx`.
- Existing AI infra: `app/Http/Controllers/Concerns/StreamsConversation.php`, `app/Ai/Agents/BookChatAgent.php`, `agent_conversations` + `agent_conversation_messages` migrations.
- Entity patterns: `app/Models/Concerns/HasDualDescription.php`, `app/Jobs/Concerns/PersistsExtractedEntities.php`.
- Guardrails: `CLAUDE.md` — no auth checks, controller→feature-test, browser test per feature, red-green bugfixes, dual-database migrations.
