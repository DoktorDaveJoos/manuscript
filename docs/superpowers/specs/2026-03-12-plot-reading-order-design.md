# Plot & Reading Order — Design Spec

## Problem

Authors with multiple storylines need a way to define the **reading order** (the linear sequence in which chapters are printed/exported) separately from the **storyline structure** (which chapters belong to which storyline). Without this, export cannot produce a correctly ordered manuscript.

The current app already has the data foundation:

- `reader_order` (integer) on chapters — global reading sequence across all storylines
- `storyline_id` on chapters — each chapter belongs to one storyline
- `act_id` (nullable) on chapters — optional act assignment
- Acts with `sort_order` — structural divisions of the book
- Export service walks `reader_order` to produce the manuscript

**What's missing is the UX** — there is no dedicated interface for managing `reader_order`. The sidebar groups chapters by storyline (correct for writing), but the Plot page needs to let authors define the interleaved reading sequence.

### The Import Problem

When an author imports a book (H1 headings = chapters, files = storylines), chapters from each file get sequential `reader_order` values. File A's chapters come first, then File B's. The author needs an efficient way to interleave them into the correct reading order without dragging 100+ items one by one.

## Research Summary

Analysis of Scrivener, Plottr, yWriter, Dabble, Atticus, Vellum, Campfire, Aeon Timeline, and Novelcrafter revealed three patterns:

1. **Single linear tree** (most tools): One sidebar = export order. Storylines are metadata overlays. Author manually interleaves.
2. **Grid + separate outline** (Plottr): 2D planning grid (plotlines x chapters) for structure, separate linear Outline for reading order. Export uses the Outline.
3. **Timeline + narrative** (Aeon Timeline): Chronological timeline separate from narrative reading order.

**No tool auto-generates reading order from acts or storylines.** All require manual definition of the linear sequence.

### Why Acts Don't Drive Export Order

Acts are useful for structural grouping but cannot determine reading order because:

- Most storylines span multiple acts
- Acts don't define interleaving (A-B-A-B vs A-A-B-B?)
- For imports, chapters still need manual positioning

Acts remain valuable as a **planning axis** in the grid view.

## Design: Approach C — Grid for Context + Outline for Ordering

The Plot page has three panels:

### Left Sidebar (existing, minor update)

- Lists **storylines** grouped by type (Main Storylines / Backstory)
- No chapter numbering in the sidebar — just storyline names with color indicators
- Clicking a storyline could scroll/highlight its row in the grid

### Center: Swimlane Grid (existing, restructured)

The structural planning view. Shows what chapters each storyline has in each act.

- **Rows** = storylines (with colored bar + name as row label)
- **Columns** = acts (not individual chapters — acts are the horizontal axis)
- **Cards** = chapters within each act-storyline cell, displayed as compact tags showing "Ch N · Title"
- Acts have colored headers (e.g., "ACT 1 — THE FRACTURE")
- Backstory storylines separated by a labeled divider
- Tension arc remains at the top
- Grid is **read-only for ordering purposes** — it shows structure, not sequence

### Right Panel: Reading Order (new, default right panel)

The export-order view. A linear list defining the exact sequence readers will encounter chapters.

**AI Sidebar displacement:** The current AI sidebar (tension arc generation, plot health, plot holes, beat suggestions) moves behind a toggle button in the header bar. The Reading Order panel is the default right panel. Clicking the AI toggle swaps the right panel to the AI sidebar. Only one is visible at a time.

**Header:**

- "READING ORDER" label with list-ordered icon
- Chapter count badge (e.g., "8 ch")

**Action Bar:**

- **Auto-interleave button** — one-click interleaving of chapters across storylines, respecting act assignments. Algorithm: within each act, alternate chapters across storylines in storyline `sort_order`. This solves the 100-chapter import problem.
- "= Export order" hint text — makes it explicit that this list IS the print order

**Chapter List:**
Each row contains:

- **Drag handle** (grip-vertical icon) — for manual reordering via drag-and-drop
- **Storyline color bar** — 3px vertical bar matching the storyline's color
- **Reading position number** (bold) — the `reader_order` value (1, 2, 3...)
- **Chapter title** — the chapter name
- **Storyline + chapter info** (light text) — e.g., "Elena · Ch 1"

**Backstory divider:**
Backstory chapters (from storylines with `type = backstory`) appear below a "BACKSTORY" divider in the list. They are included in the sequential `reader_order` numbering — backstory chapters are interleaved like any other chapter. The divider is a visual grouping aid, not a structural barrier.

**Empty / edge states:**

- Zero chapters: show an empty state with prompt to create chapters
- Single storyline: auto-interleave button is hidden (nothing to interleave)
- Chapters with no act: appear in an "Unassigned" section at the bottom of the reading order

**Confirmation UX:**
Auto-interleave rewrites every chapter's `reader_order`. Show a confirmation dialog: "This will reorder all N chapters based on act and storyline structure. Continue?"

**Interactions:**

- Drag-and-drop reorders chapters and updates `reader_order` values
- Cross-highlighting between grid and reading order is deferred to a follow-up iteration (MVP focuses on the Reading Order panel itself)
- Auto-interleave recalculates all `reader_order` values based on act grouping and storyline order

## Data Model

No schema changes needed. The existing fields support this design:

| Table        | Field                  | Purpose                                           |
| ------------ | ---------------------- | ------------------------------------------------- |
| `chapters`   | `reader_order` (uint)  | Global reading sequence                           |
| `chapters`   | `storyline_id` (FK)    | Which storyline owns this chapter                 |
| `chapters`   | `act_id` (nullable FK) | Which act this chapter belongs to                 |
| `storylines` | `sort_order` (uint)    | Storyline display order (used by auto-interleave) |
| `acts`       | `sort_order` (uint)    | Act structural order                              |

## Auto-Interleave Algorithm

```
1. Load all non-trashed chapters for the book
2. Group chapters by act_id (null act = unassigned group at end)
3. Sort act groups by act.sort_order
4. Within each act group:
   a. Sub-group chapters by storyline_id
   b. Sort sub-groups by storyline.sort_order
   c. Within each storyline sub-group, chapters retain their relative order
      from their current reader_order values
   d. Round-robin across storylines: take one chapter from each storyline in turn.
      When a storyline has no more chapters in the current round, skip it.
      Continue until all chapters in the act group are placed.
5. Assign sequential reader_order values (1, 2, 3...) to the resulting flat list
6. Unassigned chapters (no act) appended at the end, preserving their
   relative reader_order
```

## API Endpoints

### Reorder Chapter (existing, reuse)

`POST /chapters/reorder` — already handles `reader_order` updates via drag-and-drop.

**Note:** The existing `ReorderChaptersRequest` validates `order.*.storyline_id` as required. The Reading Order panel drag-and-drop preserves each chapter's current `storyline_id` — no cross-storyline moves from this panel. The front-end must include `storyline_id` in the payload.

### Auto-Interleave (new)

`POST /books/{book}/chapters/interleave`

- No request body needed — uses act assignments and storyline order
- Returns the new chapter order as `[{id, reader_order}]`
- Updates all `reader_order` values in a single transaction

## Frontend Components

### ReadingOrderPanel.tsx (new)

- Receives chapters sorted by `reader_order`, storylines, acts
- Renders the draggable list with `@dnd-kit`
- Calls reorder endpoint on drag-end
- Calls interleave endpoint on auto-interleave button click

### Plot page updates (resources/js/pages/plot/index.tsx)

- ReadingOrderPanel as default right panel, AI sidebar behind a toggle
- Restructure grid: remove chapter column headers, use act-based columns
- Cross-highlighting deferred to follow-up iteration

### PlotController update (app/Http/Controllers/PlotController.php)

- Currently loads chapters only via `acts.chapters` — chapters with `act_id = NULL` are invisible
- Must also pass a flat `chapters` collection (all non-trashed chapters sorted by `reader_order`) as a separate prop for the Reading Order panel
- The grid can continue using `acts.chapters` for its act-grouped view

## Header Tabs

The existing "Timeline" and "List" tabs in the header bar control the main content area:

- **Timeline** = swimlane grid view (acts x storylines)
- **List** = flat list view of plot points (existing)

The Reading Order panel is **always visible** alongside either view — it's not a tab, it's a persistent sidebar.

## Design Reference

See `untitled.pen` — frame "Plot — Reading Order View" for the visual mockup.
