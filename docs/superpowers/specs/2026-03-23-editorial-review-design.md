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
  "chapter_references": [4, 7],
  "recommendation": "Establish a turning point or internal conflict that explains the shift."
}
```

### `editorial_review_chapter_notes` table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | |
| `editorial_review_id` | foreignId | Belongs to EditorialReview |
| `chapter_id` | foreignId | Belongs to Chapter |
| `notes` | json | Keyed by editorial dimension: `narrative_voice`, `themes`, `scene_craft`, `prose_style_patterns` |
| `timestamps` | | |

### Relationships

- `Book` hasMany `EditorialReview`
- `EditorialReview` belongsTo `Book`
- `EditorialReview` hasMany `EditorialReviewSection`
- `EditorialReview` hasMany `EditorialReviewChapterNote`
- `EditorialReviewChapterNote` belongsTo `Chapter`

### Enum: `EditorialSectionType`

Eight cases (TitleCase):
- `Plot` — Story structure, arc completeness, plot holes, logical consistency
- `Characters` — Character development, motivation, consistency, voice distinctiveness
- `Pacing` — Tension curve, chapter rhythm, sagging middles, rushed endings
- `NarrativeVoice` — POV consistency, tense, tone shifts, authorial voice
- `Themes` — Thematic coherence, recurring motifs, whether themes land
- `SceneCraft` — Scene purpose, show vs. tell, sensory detail, dialogue quality
- `ProseStyle` — Repetitions, filter words, sentence variety, readability
- `ChapterNotes` — Specific per-chapter observations and suggestions

---

## Processing Pipeline

### Phase 1 — Preflight

1. Validate: AI configured, PRO license active, book has chapters
2. Check no other editorial review is currently in progress for this book
3. Check existing chapter analyses — identify stale ones (content hash changed since last analysis)
4. If stale analyses exist, re-run them first via existing `AnalyzeChapterJob`
5. Create `editorial_reviews` row with status `pending`
6. Dispatch `RunEditorialReviewJob`

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
- All chapter analyses relevant to that section (from existing `analyses` table)
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

- If any phase fails, mark the review as `failed` with error context in progress JSON
- Individual chapter failures don't fail the whole review — skip and note in the report
- User can retry a failed review

### Estimated API Calls

For a 12-chapter book: ~12 (gap fill) + 8 (synthesis) + 1 (executive summary) = ~21 API calls.

---

## AI Agents

### `EditorialNotesAgent`

- **Purpose:** Per-chapter editorial observations that complement existing chapter analyses
- **Temperature:** 0.3 (analytical, consistent)
- **MaxTokens:** 4096
- **Task Category:** `AiTaskCategory::Analysis`
- **Input:** Chapter text, existing analysis results, writing style profile, character data
- **Output:** Structured JSON with keys: `narrative_voice`, `themes`, `scene_craft`, `prose_style_patterns`

### `EditorialSynthesisAgent`

- **Purpose:** Manuscript-wide synthesis for one editorial section
- **Temperature:** 0.4 (needs some creativity for recommendations)
- **MaxTokens:** 8192
- **Task Category:** `AiTaskCategory::Analysis`
- **Input:** Aggregated chapter-level data for that section, section type context
- **Output:** Score (0-100), findings array, section summary, recommendations
- **Note:** One prompt template per section type (plot synthesis has different instructions than prose style synthesis)

### `EditorialSummaryAgent`

- **Purpose:** Produce the overall score and executive summary
- **Temperature:** 0.3
- **MaxTokens:** 2048
- **Task Category:** `AiTaskCategory::Analysis`
- **Input:** All 8 section scores and summaries
- **Output:** Overall score, executive summary, top 3 strengths, top 3 improvements

### Chat Integration

When the user clicks "Discuss" on a finding, the existing `AiChatDrawer` opens with a Lektorat-specific system prompt containing:
- The full editorial review executive summary
- The specific section the finding belongs to
- The finding itself
- Relevant chapter references

This is a different system prompt from the regular book chat — it does not assume a current chapter is open and has the editorial report as its primary context.

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

All routes nested under existing AI route group with `license` middleware.

### Job: `RunEditorialReviewJob`

- Dispatched by `EditorialReviewController@store`
- Orchestrates the 4 phases sequentially
- Updates `editorial_reviews.progress` JSON after each step
- Catches failures, marks status as `failed`

### Models

- `EditorialReview` — belongsTo `Book`, hasMany sections and chapter notes
- `EditorialReviewSection` — belongsTo `EditorialReview`, enum `type`
- `EditorialReviewChapterNote` — belongsTo `EditorialReview` and `Chapter`

### Enum: `EditorialSectionType`

PHP backed enum with 8 cases as described in Data Model section.

---

## Frontend

### Navigation

Add sub-tabs at the top of the AI main content area:
- **"Overview"** — Current AI dashboard (existing content)
- **"Editorial Review"** — New editorial review page

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

"Discuss" button on any finding opens `AiChatDrawer` with:
- Lektorat-specific system prompt (not the regular book chat prompt)
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
