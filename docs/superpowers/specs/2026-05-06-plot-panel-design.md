# Plot Panel: Editor Sidebar Panel for Connected Beats

**Date**: 2026-05-06
**Status**: Draft
**Branch**: `feat/plot-coach` (continuation work)

## Overview

Add a `plot` sidebar panel to the chapter editor that surfaces beats already connected to the active chapter (via the existing `beat_chapter` pivot), grouped by their parent plot point. Mirrors the existing `wiki` panel pattern in layout, behavior, and code shape. Lets the writer connect existing beats to the chapter, edit beat fields inline, and disconnect via a kebab menu — without leaving the editor.

The plot coach pipeline already creates beats and connects them to chapters; this panel is the manual editing surface for those connections from inside the editor.

## Decisions (from brainstorming)

1. **Hierarchy**: Grouped by plot point. Plot points appear as collapsible section headers; their connected beats nest underneath.
2. **Ad-hoc add**: "Connect existing only." Search surfaces existing beats from the book; user connects them. The panel does **not** create new beats or plot points (the full plot board does that).
3. **Plot-point linkage**: Plot points are auto-revealed by their beats. No new `plot_point_chapter` pivot. When a plot point's last connected beat is disconnected, its group header disappears.
4. **Disconnect demoted**: Disconnect lives in a kebab `⋯` menu on the card, not as a prominent button.
5. **Inline edit**: Beat title, description, and status are editable from expanded cards (parity with wiki panel).

## Out of Scope

- Creating new beats or plot points from the panel.
- Reordering beats within the chapter (pivot `sort_order` stays 0, matching all existing call sites).
- Showing pending plot-coach proposals in this panel.
- Surfacing PlotPoint-level metadata beyond title (no act color, no characters, no connections).
- Editing plot points themselves from the panel — only their beats.

## Alignment with Existing Pivot Writes

Three call sites currently write `beat_chapter`:

- `BeatController::linkChapter / unlinkChapter` — direct Eloquent (`syncWithoutDetaching` / `detach`)
- `PlotCoachBatchService::writeChapter` — Eloquent (`attach` / `sync` / `syncWithoutDetaching`) when applying a `chapter` write op carrying `beat_ids: int[]`
- (new) `PlotPanelController::connect / disconnect` — must use the **same direct Eloquent pattern** as `BeatController`

Neither existing call site computes `sort_order` on the pivot. The new panel **must not either** — it defaults to 0 like every other write today.

The plot coach's `chapter` write op shape (`beat_ids: int[]` carried on the chapter, not on the beat) is a parallel path for AI proposals; the manual panel does **not** route through `PlotCoachBatchService`. This preserves the invariant that `PlotCoachBatch` records contain only AI-applied changes.

**Future cleanup (not in scope)**: extract a `BeatChapterLinker` action so `BeatController`, `PlotPanelController`, and `PlotCoachBatchService` all funnel through one place. Called out here so the next person to touch `beat_chapter` writes considers it.

## Architecture

### Frontend

New components in `resources/js/components/editor/`:

- **`PlotPanel.tsx`** — Top-level panel. Owns search query, connected-list state, session-list state, and expand/collapse state.
- **`PlotPanelSearch.tsx`** — Search input with clear button.
- **`PlotPanelGroup.tsx`** — Plot-point section header + collapsible body containing beat cards.
- **`PlotPanelCard.tsx`** — Individual beat card. Collapsed: title + status pill. Expanded: editable title, description textarea, status select, kebab menu (View on plot board, Disconnect).

Editor wiring in `resources/js/pages/chapters/editor.tsx` and `resources/js/components/editor/AccessBar.tsx`:

- Add `'plot'` to `PanelId` union.
- Add `'plot'` to `VALID_PANELS` set.
- Add a toggle button to `accessBarItems` with the `Workflow` icon from lucide-react.
- Add a `<SlidePanel>` rendering `<PlotPanel key={focusedChapter.id} book={book} chapter={focusedChapter} />`.

### Backend

- **`app/Http/Controllers/PlotPanelController.php`** — new controller. Same shape as `WikiPanelController` (HTTP verbs, JSON response style, book-scope enforcement).

  ```php
  index(Book $book, Request $request)        // GET   /books/{book}/plot/panel
  connect(Book $book, Request $request)      // POST  /books/{book}/plot/panel/connect
  disconnect(Book $book, Request $request)   // POST  /books/{book}/plot/panel/disconnect
  update(Book $book, Beat $beat, Request)    // PATCH /books/{book}/plot/panel/beats/{beat}
  ```

  All four enforce book scope via `abort_unless($beat->plotPoint->book_id === $book->id, 404)` (for endpoints that resolve a beat) and a `Rule::exists('chapters', 'id')->where('book_id', $book->id)` validation rule on `chapter_id`.

  - `index` reads query string `chapter_id` (required) and optional `q`. Returns JSON `{ connected: GroupShape[], session: GroupShape[] }`.
  - `connect` reads body `chapter_id`, `beat_id`. Calls `$beat->chapters()->syncWithoutDetaching([$chapter->id])`. Returns `{ ok: true }`.
  - `disconnect` reads body `chapter_id`, `beat_id`. Calls `$beat->chapters()->detach($chapter->id)`. Returns `{ ok: true }`.
  - `update` accepts optional `title`, `description`, `status`. Returns the refreshed beat in the panel resource shape.

### Routes

In `routes/web.php`, inside the existing `Route::middleware('license')->group(...)` block (alongside `/books/{book}/plot`):

```php
Route::get('/books/{book}/plot/panel', [PlotPanelController::class, 'index'])->name('plot.panel.index');
Route::post('/books/{book}/plot/panel/connect', [PlotPanelController::class, 'connect'])->name('plot.panel.connect');
Route::post('/books/{book}/plot/panel/disconnect', [PlotPanelController::class, 'disconnect'])->name('plot.panel.disconnect');
Route::patch('/books/{book}/plot/panel/beats/{beat}', [PlotPanelController::class, 'update'])->name('plot.panel.updateBeat');
```

Wayfinder regenerates TypeScript route functions under `resources/js/actions/App/Http/Controllers/PlotPanelController.ts`.

## Data Flow

### Resource Shape

Match `PlotController`'s inline shape exactly. No new `Resource` class — use inline `->only()` on the model. The panel JSON shape is:

```ts
type GroupShape = {
  plot_point: { id: number; title: string; sort_order: number };
  beats: BeatShape[];
};

type BeatShape = {
  id: number;
  title: string;
  description: string | null;
  status: 'planned' | 'fulfilled' | 'abandoned';
  sort_order: number;
  plot_point_id: number;
  chapters: { id: number; title: string; storyline_id: number; reader_order: number }[];
};
```

### `index` query plan

```php
// Connected: beats already linked to this chapter
$connectedBeats = Beat::query()
    ->whereHas('chapters', fn ($q) => $q->where('chapters.id', $chapter->id))
    ->whereHas('plotPoint', fn ($q) => $q->where('book_id', $book->id))
    ->with(['plotPoint', 'chapters:id,title,storyline_id,reader_order'])
    ->get();

// Session (search results): beats matching query, excluding already-connected
$sessionBeats = collect();
if ($q !== null && trim($q) !== '') {
    $sessionBeats = Beat::query()
        ->whereHas('plotPoint', fn ($q) => $q->where('book_id', $book->id))
        ->whereDoesntHave('chapters', fn ($qb) => $qb->where('chapters.id', $chapter->id))
        ->where(fn ($qq) => $qq
            ->where('title', 'like', "%{$q}%")
            ->orWhere('description', 'like', "%{$q}%"))
        ->with(['plotPoint', 'chapters:id,title,storyline_id,reader_order'])
        ->limit(50)
        ->get();
}
```

Group both collections in PHP by `plot_point_id`; order groups by `plot_point.sort_order`. The book scope is enforced via `whereHas('plotPoint', fn ($q) => $q->where('book_id', $book->id))` on every query.

### Frontend state machine

Local state in `PlotPanel`:

- `connectedGroups: GroupShape[]` — refreshed from backend on mount, on chapter change, and after every connect/disconnect/update mutation.
- `sessionGroups: GroupShape[]` — refreshed when search query changes. Match wiki panel: fire request on every keystroke (no debounce), but skip when query is empty or under 2 characters.
- `query: string` — search input value.
- `expandedBeatIds: Set<string>` — persisted to `localStorage` key `manuscript:plot-expanded-beats:{chapterId}`. Default empty (cards start collapsed).
- `collapsedGroupIds: Set<string>` — persisted to `manuscript:plot-collapsed-groups:{chapterId}`. Default empty (groups start expanded).

After connect: refetch `index` with current query → new `connectedGroups` and `sessionGroups`. After disconnect: same. The panel never patches local state speculatively — it always re-reads from the server (matches wiki panel).

### Inline edit

- Title / description / status saved on blur (no debounce).
- `PATCH /books/{book}/plot/panel/beats/{beat}` returns the refreshed beat shape; panel splices it into `connectedGroups` (or `sessionGroups` if it's currently in session).
- On 422 validation error: revert to last known value, surface error inline below the field.

## UI States

### Layout

```
┌─ Plot ─────────────────────── ✕ ┐
│ [search...]                     │
├─────────────────────────────────┤
│ ─ Connected to chapter ─        │
│ ▼ Inciting incident         ⋮  │
│   ┌───────────────────────────┐ │
│   │ ▸ Murder        [planned] │ │
│   └───────────────────────────┘ │
│   ┌───────────────────────────┐ │
│   │ ▼ Body found   [written]  │ │
│   │ ────────────────────────  │ │
│   │ [description textarea]    │ │
│   │ Status: [select ▾]   ⋯   │ │
│   └───────────────────────────┘ │
│ ─ Recently viewed ─             │
│ ▼ Climax                        │
│   ┌───────────────────────────┐ │
│   │ ▸ Confrontation  [Connect]│ │
│   └───────────────────────────┘ │
└─────────────────────────────────┘
```

### Card actions

- **Connected card, collapsed**: shows title, status pill, kebab `⋯` (right). Click body to expand.
- **Connected card, expanded**: editable title, description textarea, status select. Kebab menu items: "View on plot board" (Wayfinder link to the named route `books.plot`), "Disconnect from chapter".
- **Session card**: shows title, status pill, prominent "Connect" button. No expand, no kebab. (Cards in session are read-only previews; users edit on the plot board or after connecting.)

### Empty / edge states

- **License inactive**: the access bar's plot toggle button is hidden when `useLicense().isActive` is false (button-level gate via the existing hook). The route is also server-gated by `Route::middleware('license')`. Users without PRO never see the toggle.
- **No plot points exist in the book**: show "Set up your plot first" with a link to `books.plot` (Wayfinder).
- **Plot exists, no beats connected to this chapter, no search query**: "No beats connected to this chapter. Search to add one."
- **Search query returns no results**: "No beats match '{query}'."
- **Beat connected to multiple chapters**: in expanded view, show other chapter chips below status (data is already in the resource shape via `chapters: []`).
- **Beat is deleted elsewhere while panel is open**: it disappears on next `index` call. No special stale-state handling.

## Testing

### Backend (Pest)

`tests/Feature/PlotPanelControllerTest.php` — required by `tests/Unit/GuardrailsTest.php` for any new controller. Cover:

- `index` returns connected beats grouped by plot point, ordered by `plot_point.sort_order`.
- `index` with `q` returns matching session beats grouped by plot point, excluding already-connected beats.
- `index` ignores beats from other books (book-scope enforcement).
- `connect` adds the pivot row; idempotent (calling twice does not duplicate, since `syncWithoutDetaching` is the underlying call).
- `connect` returns 404 when the beat's plot point belongs to a different book.
- `disconnect` removes the pivot row; safe when the row does not exist.
- `update` patches `title`, `description`, `status` on a beat; returns the refreshed shape.
- `update` returns 422 for an invalid `status` value.
- All four endpoints redirect / 403 when license is inactive (the existing `license` middleware response).

### Browser

`tests/Browser/ChapterEditorTest.php` already covers wiki, ai, notes, editorial panel mounting via the access bar (`[data-access-bar="..."]`). Per CLAUDE.md guardrail "Browser test per feature, not per page," the plot panel is a tweak to the editor feature — extend `ChapterEditorTest.php`, do not create a new browser test file. New cases:

- Toggle plot panel from access bar (`[data-access-bar="plot"]`) → panel renders.
- Search a known beat title → session zone populates with matches.
- Click "Connect" on a session card → card disappears from session, appears in connected zone under its plot point.
- Expand a connected card → kebab menu visible. Click "Disconnect from chapter" → card disappears from connected zone.
- Reload page → expanded/collapsed state persists per chapter.

### Guardrails

`tests/Unit/GuardrailsTest.php` enforces "every controller has a feature test." `PlotPanelController` is new → it must have `tests/Feature/PlotPanelControllerTest.php`. The existing test will fail until that file exists.

## Pencil Designs

Per project workflow rule, designs first. Open the editor `.pen` file (Cortex folder: `MANUSCRIPT`) and add three frames:

1. **Plot panel — empty state** (book has plot points, but none connected to chapter; no search query).
2. **Plot panel — connected only** (two plot point groups, one collapsed, one expanded; cards collapsed).
3. **Plot panel — expanded card with kebab menu open** (full edit affordances, kebab menu showing "View on plot board" / "Disconnect from chapter").

Match the wiki panel's typography, spacing, and dark-mode tokens exactly. Sidebar uses `bg-white dark:bg-surface-card` per the project dark-mode rule.

## Database

No migrations. The `beat_chapter` pivot already exists from the plot-coach work. No new schema.

## Files Changed

### New files

- `app/Http/Controllers/PlotPanelController.php`
- `resources/js/components/editor/PlotPanel.tsx`
- `resources/js/components/editor/PlotPanelSearch.tsx`
- `resources/js/components/editor/PlotPanelGroup.tsx`
- `resources/js/components/editor/PlotPanelCard.tsx`
- `tests/Feature/PlotPanelControllerTest.php`

### Modified files

- `routes/web.php` — add four panel routes inside the existing `Route::middleware('license')->group(...)` block.
- `resources/js/components/editor/AccessBar.tsx` — extend `PanelId` and `accessBarItems`. Plot button is rendered conditionally on `useLicense().isActive`.
- `resources/js/pages/chapters/editor.tsx` — extend `VALID_PANELS`, add `<SlidePanel>` rendering `<PlotPanel>`.
- `tests/Browser/ChapterEditorTest.php` — add plot-panel scenarios (mount, search, connect, disconnect, persistence).
- Pencil design file — three new frames.

### Auto-regenerated

- `resources/js/actions/App/Http/Controllers/PlotPanelController.ts` (Wayfinder).
