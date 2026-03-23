# Editorial Review (Lektorat) — Design Spec

## Overview

A comprehensive AI-powered editorial review feature that analyzes an entire manuscript and produces a structured editorial report — similar to what a professional Lektor would deliver. It covers story structure, character arcs, pacing, narrative voice, themes, scene craft, prose style, and per-chapter notes.

The feature builds on Manuscript's existing AI infrastructure (chapter analyses, character data, embeddings) via a hybrid layered approach, avoiding redundant API calls while adding manuscript-wide editorial synthesis.

### Key Decisions

- **English naming:** "Editorial Review" throughout codebase and UI
- **Hybrid approach:** Reuses existing chapter analyses, fills gaps, then synthesizes per editorial section
- **Report + Interactive:** Produces a structured report with the ability to discuss any finding via the AI Chat Drawer
- **History:** Each run is stored and timestamped; authors can compare improvement across revisions
- **Sub-page:** Lives under a new "Editorial Review" tab on the AI — Pro page
- **User guidance:** Clearly communicates this is designed for completed first drafts and is token-intensive

---

## Data Model

### `editorial_reviews` table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | |
| `book_id` | foreignId | Belongs to Book |
| `status` | string (enum) | `pending`, `analyzing`, `synthesizing`, `completed`, `failed` |
| `progress` | json, nullable | `{ phase, current_chapter, total_chapters, current_section }` |
| `error_message` | text, nullable | Error details if status is `failed` |
| `overall_score` | integer, nullable | 0-100, set on completion |
| `executive_summary` | text, nullable | 2-3 paragraph summary, set on completion |
| `top_strengths` | json, nullable | Array of top 3 strengths |
| `top_improvements` | json, nullable | Array of top 3 areas for improvement |
| `started_at` | timestamp, nullable | |
| `completed_at` | timestamp, nullable | |
| `timestamps` | | created_at, updated_at |

### `editorial_review_sections` table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | |
| `editorial_review_id` | foreignId | Belongs to EditorialReview |
| `type` | string (enum) | One of the 8 `EditorialSectionType` values |
| `score` | integer, nullable | 0-100 |
| `summary` | text, nullable | Section-level summary |
| `findings` | json, nullable | Array of findings (see Finding structure below) |
| `recommendations` | json, nullable | Array of actionable recommendations |
| `timestamps` | | |

**Finding structure:**
```json
{
  "severity": "critical|warning|suggestion",
  "description": "The antagonist's motivation shifts without explanation between chapters 4 and 7.",
  "chapter_references": [4, 7],  // chapter IDs (database PKs), not reader order
  "recommendation": "Establish a turning point or internal conflict that explains the shift."
}
```

### `editorial_review_chapter_notes` table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | |
| `editorial_review_id` | foreignId | Belongs to EditorialReview |
| `chapter_id` | foreignId | Belongs to Chapter |
| `notes` | json | Keyed by editorial dimension: `narrative_voice`, `themes`, `scene_craft`, `prose_style_patterns`. Only these 4 dimensions because: Plot is covered by `CharacterConsistency` and `PlotDeviation` analyses in the `analyses` table; Characters by `CharacterConsistency` analysis; Pacing data lives on chapter columns (`tension_score`, `pacing_feel`, `micro_tension_score`) from `ChapterAnalyzer`. |
| `timestamps` | | |

### Relationships

- `Book` hasMany `EditorialReview`
- `EditorialReview` belongsTo `Book`
- `EditorialReview` hasMany `EditorialReviewSection`
- `EditorialReview` hasMany `EditorialReviewChapterNote`
- `EditorialReviewChapterNote` belongsTo `Chapter`

### Cascade Behavior

- `editorial_review_sections` and `editorial_review_chapter_notes` use `cascadeOnDelete()` on their `editorial_review_id` foreign key
- Review deletion is not a v1 feature but the cascade ensures clean data if added later

### Enum: `EditorialSectionType`

Eight cases (TitleCase):
- `Plot` — Story structure, arc completeness, plot holes, logical consistency
- `Characters` — Character development, motivation, consistency, voice distinctiveness
- `Pacing` — Tension curve, chapter rhythm, sagging middles, rushed endings
- `NarrativeVoice` — POV consistency, tense, tone shifts, authorial voice
- `Themes` — Thematic coherence, recurring motifs, whether themes land
- `SceneCraft` — Scene purpose, show vs. tell, sensory detail, dialogue quality
- `ProseStyle` — Repetitions, filter words, sentence variety, readability
- `ChapterNotes` — Cross-chapter pattern synthesis from per-chapter observations (the raw per-chapter notes live in `editorial_review_chapter_notes`; this section summarizes recurring patterns, chapter-to-chapter progression, and standout moments)

---

## Processing Pipeline

### Phase 1 — Preflight

1. Validate: AI configured, PRO license active, book has chapters (minimum 1; no hard minimum enforced, but the empty state guidance steers users toward completed drafts)
2. Check no other editorial review is currently in progress for this book
3. Create `editorial_reviews` row with status `pending`
4. Dispatch `RunEditorialReviewJob`

**Inside the job (Phase 0 — Refresh Stale Analyses):**
- Check existing chapter analyses via `Chapter::needsAiPreparation()` (compares `content_hash` vs `prepared_content_hash`)
- If stale chapters exist, re-run only the `ChapterAnalyzer` agent synchronously for each stale chapter (not the full `AnalyzeChapterJob` pipeline, which triggers manuscript-wide analyses and would blow the timeout budget)
- This runs inside the job's 1800-second timeout budget, not during the HTTP request
- Progress updates: status `analyzing`, progress `{ phase: "refreshing", current_chapter: 1, total_chapters: 3 }`

### Phase 2 — Gap Fill (chapter-by-chapter)

For each chapter, run `EditorialNotesAgent` with:
- The chapter's full text
- The existing chapter analysis results for that chapter
- The book's writing style profile (if extracted)
- Character/entity data from wiki entries

The agent produces editorial observations that existing analyses don't cover:
- Narrative voice notes (POV, tense, tone)
- Thematic observations (motifs, thematic throughlines)
- Scene craft specifics (scene purpose, show vs. tell moments)
- Prose style patterns (sentence rhythm, vocabulary, repetitions)

Results stored in `editorial_review_chapter_notes`.

Progress updates: status `analyzing`, progress `{ phase: "analyzing", current_chapter: 3, total_chapters: 12 }`

### Phase 3 — Synthesis (per section)

For each of the 8 editorial sections, run `EditorialSynthesisAgent` with:
- All chapter analyses relevant to that section (from existing `analyses` table for Plot/Characters; from chapter columns `tension_score`, `pacing_feel`, `micro_tension_score` for Pacing)
- All editorial chapter notes relevant to that section (from Phase 2)
- Character/entity data where relevant (for Characters, Plot sections)

Each synthesis produces:
- A score (0-100)
- A section summary
- Structured findings array (each with severity, description, chapter references, recommendation)
- Actionable recommendations array

Results stored in `editorial_review_sections`.

Progress updates: status `synthesizing`, progress `{ phase: "synthesizing", current_section: "Characters" }`

### Phase 4 — Executive Summary

Run `EditorialSummaryAgent` with:
- All 8 section scores and summaries

Produces:
- Overall score (0-100)
- Executive summary (2-3 paragraphs)
- Top 3 strengths
- Top 3 areas for improvement

Updates `editorial_reviews` row: status `completed`, `completed_at` timestamp.

### Failure Handling

- If any phase fails, mark the review as `failed` and store the error in `error_message` (the `progress` JSON retains the last known phase/step for debugging context)
- Individual chapter failures don't fail the whole review — skip and note in the report
- User can retry a failed review

### Estimated API Calls

For a 12-chapter book: ~12 (gap fill) + 8 (synthesis) + 1 (executive summary) = ~21 API calls.

---

## AI Agents

All agents implement `HasMiddleware` (for `InjectProviderCredentials`) and `BelongsToBook` (for usage tracking). Non-chat agents additionally implement `HasStructuredOutput` with a `schema()` method defining their JSON output shape. Temperature and MaxTokens values below are PHP attributes (`#[Temperature(0.3)]`, `#[MaxTokens(4096)]`) from `Laravel\Ai\Attributes`.

### `EditorialNotesAgent`

- **Purpose:** Per-chapter editorial observations that complement existing chapter analyses
- **Implements:** `HasStructuredOutput`, `HasMiddleware`, `BelongsToBook`
- **Temperature:** 0.3 (analytical, consistent)
- **MaxTokens:** 4096
- **Task Category:** `AiTaskCategory::Analysis`
- **Input:** Chapter text, existing analysis results, writing style profile, character data
- **Output:** Structured JSON with keys: `narrative_voice`, `themes`, `scene_craft`, `prose_style_patterns`
- **Schema example:**
  ```json
  {
    "narrative_voice": {
      "pov": "third-person limited",
      "tense": "past",
      "observations": ["Shifts to omniscient in paragraph 3", "..."],
      "tone_notes": "Consistently dark, breaks in the dialogue scene"
    },
    "themes": {
      "motifs": ["isolation", "decay"],
      "observations": ["The mirror motif introduced here connects to ch.2", "..."]
    },
    "scene_craft": {
      "scene_purposes": ["setup", "character reveal"],
      "show_vs_tell": ["The grief description in para 5 is told, not shown"],
      "sensory_detail": "Heavy on visual, lacks auditory/tactile"
    },
    "prose_style_patterns": {
      "sentence_rhythm": "Monotonous mid-length sentences in action scenes",
      "repetitions": ["'suddenly' appears 4 times"],
      "vocabulary_notes": "Vocabulary narrows in emotional scenes"
    }
  }
  ```

### `EditorialSynthesisAgent`

- **Purpose:** Manuscript-wide synthesis for one editorial section
- **Implements:** `HasStructuredOutput`, `HasMiddleware`, `BelongsToBook`
- **Temperature:** 0.4 (needs some creativity for recommendations)
- **MaxTokens:** 8192
- **Task Category:** `AiTaskCategory::Analysis`
- **Input:** Aggregated chapter-level data for that section, section type context
- **Output:** Score (0-100), findings array, section summary, recommendations
- **Note:** One prompt template per section type — use a `match` on `EditorialSectionType` in the agent's `instructions()` method (same pattern as `ManuscriptAnalyzer`)

### `EditorialSummaryAgent`

- **Purpose:** Produce the overall score and executive summary
- **Implements:** `HasStructuredOutput`, `HasMiddleware`, `BelongsToBook`
- **Temperature:** 0.3
- **MaxTokens:** 2048
- **Task Category:** `AiTaskCategory::Analysis`
- **Input:** All 8 section scores and summaries
- **Output:** Overall score, executive summary, top 3 strengths, top 3 improvements

### `EditorialChatAgent`

- **Purpose:** Conversational agent for discussing editorial findings (used by the "Discuss" button)
- **Implements:** `Agent`, `Conversational`, `HasTools`, `HasMiddleware`, `BelongsToBook` (uses `Promptable` trait, matching `BookChatAgent` pattern)
- **Temperature:** 0.5
- **MaxTokens:** 4096
- **Task Category:** `AiTaskCategory::Analysis`
- **System prompt context:** Executive summary, the specific section, the finding, relevant chapter references
- **Tools:** `SearchSimilarChunks`, `RetrieveManuscriptContext` (reused from `BookChatAgent`)
- **Note:** This is a distinct agent from `BookChatAgent` — it does not require a current chapter and uses the editorial report as its primary context

### Chat Integration

The existing `AiChatDrawer` component requires a `chapter` prop and cannot operate without one. To support editorial review chat:
- Refactor `AiChatDrawer` to make `chapter` optional
- When `chapter` is null, skip `chapter_id` in the request body
- Add an optional `editorialReview` prop that passes the review ID and finding context to the backend
- The backend routes to `EditorialChatAgent` instead of `BookChatAgent` when editorial review context is present

---

## Backend Architecture

### Controller: `EditorialReviewController`

| Action | Method | Route | Description |
|--------|--------|-------|-------------|
| `index` | GET | `/books/{book}/ai/editorial-review` | List all reviews (history) |
| `store` | POST | `/books/{book}/ai/editorial-review` | Trigger new review |
| `show` | GET | `/books/{book}/ai/editorial-review/{review}` | Full report with sections |
| `progress` | GET | `/books/{book}/ai/editorial-review/{review}/progress` | Polling endpoint |
| `chat` | POST | `/books/{book}/ai/editorial-review/{review}/chat` | Chat with editorial context |

All routes nested under existing AI route group with `license` middleware. The `chat` endpoint is the sole endpoint for editorial review chat — it is separate from the existing `AiController@chat`. The frontend sends chat requests to this endpoint (not the existing `/books/{book}/ai/chat`) when in editorial review context.

**`show` response:** The Inertia page props include the full `EditorialReview` with sections, chapter notes, and a `chapters` array (id, title, reader_order) for resolving `chapter_references` in findings to human-readable chapter titles.

**`chat` request body:**
```json
{
  "message": "string (required)",
  "history": "array (optional, previous messages)",
  "section_type": "string (optional, EditorialSectionType value — which section the finding belongs to)",
  "finding_index": "integer (optional, index into the section's findings array)"
}
```
The backend constructs the `EditorialChatAgent` system prompt from the review + section + finding context. Returns a `StreamedResponse` (same as existing `AiController@chat`).

### Job: `RunEditorialReviewJob`

- Dispatched by `EditorialReviewController@store`
- **Timeout:** 1800 seconds (30 minutes) — a 12-chapter book may take 10-20 minutes with ~21 sequential API calls
- Orchestrates the 4 phases sequentially
- Updates `editorial_reviews.progress` JSON after each step
- Catches failures, marks status as `failed`, stores error in `error_message`

### AI Usage Tracking

Update `RecordAiTokenUsage::resolveFeature()` to map the new agents:
- `EditorialNotesAgent` → `'editorial_review'`
- `EditorialSynthesisAgent` → `'editorial_review'`
- `EditorialSummaryAgent` → `'editorial_review'`
- `EditorialChatAgent` → `'editorial_review_chat'`

### Models

- `EditorialReview` — belongsTo `Book`, hasMany sections and chapter notes
- `EditorialReviewSection` — belongsTo `EditorialReview`, enum `type`
- `EditorialReviewChapterNote` — belongsTo `EditorialReview` and `Chapter`

### Enum: `EditorialSectionType`

PHP backed enum with 8 cases as described in Data Model section.

---

## Frontend

### Navigation & Page Structure

Currently, AI features live on the book dashboard page (components like `AiPreparation`, `AiInsights`, `AiUsageStats`). There is no standalone AI page.

The Editorial Review introduces a new Inertia page:
- **New controller action:** `EditorialReviewController@index` renders an Inertia page at `resources/js/pages/books/editorial-review.tsx`
- **Route:** `GET /books/{book}/ai/editorial-review` — renders the full editorial review page
- **Sidebar navigation:** The current sidebar (`Sidebar.tsx`) has 4 nav items: Dashboard, Wiki, Plot, Export. The Pencil design (`AI — Pro`) shows an "AI" nav item that does not yet exist in code. Implementation must: (1) add a new "AI" sidebar nav item, and (2) make "Editorial Review" a sub-item or the primary destination of that nav item. The existing AI dashboard components (preparation, insights, usage) may later move under this nav item, but for v1 they stay on the Dashboard.
- The existing dashboard AI components (preparation, insights, usage) stay on the dashboard — they are the "overview"
- The Editorial Review gets its own dedicated page, linked from the sidebar

### Editorial Review Page States

#### Empty State
- Centered message: "Get comprehensive editorial feedback on your manuscript."
- Guidance text: "Editorial Review is designed for completed first drafts. It analyzes your entire manuscript across eight editorial dimensions and is token-intensive. For ongoing feedback while writing, use Chapter Analysis instead."
- "Start Editorial Review" button

#### Confirmation Before Start
- "This will analyze your entire manuscript. Editorial Review works best on completed first drafts. Continue?"
- Confirm / Cancel

#### In-Progress State
- Reuses the AI Preparation progress pattern
- Shows: current phase label, progress bar, specific chapter/section being processed
- "Start Editorial Review" button disabled

#### Report State (primary view)
- **Header:** Overall score (large number with visual indicator), executive summary text, review date
- **History:** Dropdown to select from previous reviews by date
- **"Start New Review" button**
- **Strengths & Improvements:** Top 3 of each, displayed prominently
- **8 Accordion Sections:** Each shows:
  - Section name + score badge (collapsed)
  - Expanded: section summary, findings list (each with severity indicator, description, chapter references, "Discuss" button), recommendations
- **Chapter Notes:** Expandable per-chapter notes at the bottom

#### Failed State
- Error message with "Retry" button

### Chat Integration

"Discuss" button on any finding opens `AiChatDrawer` (with `chapter` as null, `editorialReview` prop set) with:
- Lektorat-specific system prompt via `EditorialChatAgent` (not `BookChatAgent`)
- Editorial review context pre-loaded (executive summary, section, finding)
- No assumption of a current chapter being open

---

## Testing Strategy

### Feature Tests

- `EditorialReviewController` — index, store, show, progress endpoints
- Authorization: requires PRO license, requires AI configured, requires book ownership
- Validation: can't start a review while one is in progress for the same book
- Progress polling returns correct phase/chapter data
- History: multiple reviews stored and retrievable

### Unit Tests

- `EditorialReview` model relationships and status transitions
- `EditorialReviewSection` model and type enum
- `EditorialSectionType` enum coverage
- Progress JSON structure validation

### Job Tests

- `RunEditorialReviewJob` — mock AI agents, verify pipeline phases run in order
- Verify stale chapter analyses get re-triggered in preflight
- Verify failure handling marks review as `failed`
- Verify individual chapter failures are handled gracefully

Agent output quality tests are out of scope — AI output is non-deterministic and best validated manually.

---

## Implementation Notes

### Scoring

Editorial Review uses a 0-100 scale (not the 1-10 scale used by existing `ManuscriptAnalyzer`). This is intentional for finer granularity in editorial assessment.

### i18n

All user-facing strings must use translation keys in `resources/js/i18n/{locale}/`. Add an `editorial-review.json` translation file for `en`, `de`, and `es` locales. Section names, severity labels, button text, guidance text — all translatable.

### Polling

Reuse the same 2-second polling interval as `useAiPreparation`. Create a `useEditorialReview` hook following the same pattern.

### No Cancellation (v1)

First version does not support cancelling a running review. The user must wait for it to complete or fail. Cancellation can be added later if needed.
