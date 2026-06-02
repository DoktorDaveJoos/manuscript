# Coach Insights — Dock as Right-Rail Panel

**Status:** Draft
**Date:** 2026-05-07
**Branch:** `feat/plot-coach`

## Problem

In coach mode on `/books/{book}/plot`, the `CoachInsightsPanel` (model name, context counts, idea-prompts) is rendered as an `absolute`-positioned, 232px-wide overlay pinned to the top-left of the chat surface. The chat itself is centered at `max-w-[720px]`. On common viewport widths the floating panel covers the leftmost portion of the chat content, so the user has to mentally subtract the overlap or collapse the panel to read replies. The user likes the panel's content; only its floating placement is the problem.

## Goal

Replace the floating overlay with a docked right-rail panel that follows the established chapter-editor pattern (`SlidePanel` + `AccessBar`). The panel should be reachable from a single icon, persist its open/closed state and width, and stop occluding chat content.

Non-goals: changing the panel's content, its `i18n` keys, or its idea-prompt list. Board mode is untouched.

## Approach

Adopt full editor parity: reuse `SlidePanel` for width/animation/persistence and add a single-icon `AccessBar` to plot/index visible only in coach mode.

### Layout (coach mode)

```
<main>
  <toolbar row />
  <flex row, flex-1, min-h-0>
    <CoachPanel ... />            ← chat surface, flex-1
    <SlidePanel storageKey=... defaultWidth=272>
      <CoachInsightsPanel ... />  ← now a normal flex column
    </SlidePanel>
    <AccessBar items=[coach-insights] ... />  ← 48px
  </flex>
</main>
```

In board mode the AccessBar and SlidePanel are not mounted (conditional on `mode === 'coach'`).

### Component refactor

- `resources/js/components/plot/CoachPanel.tsx`
  - Remove the `<CoachInsightsPanel>` child and the `relative` wrapper.
  - The component becomes: gate-shells (no Pro / no AI configured) and `<ChatSurface>` only.
  - The `contextCounts` prop becomes unused on `CoachPanel` — remove it from the props type.

- `resources/js/components/plot/CoachInsightsPanel.tsx`
  - Remove the `pointer-events-none absolute top-4 left-4 z-10 hidden w-[232px] lg:block` wrapper and the inner `pointer-events-auto ... rounded-xl border ... shadow-sm` chrome.
  - Replace with a flex column that fills its parent (`flex h-full min-h-0 flex-col gap-3 overflow-y-auto bg-surface-card p-3`).
  - Drop the internal collapse state, the `STORAGE_KEY`, the chevron expand/collapse toggle, and the collapsed-chevron return value.
  - Add `onClose: () => void` to props. Replace the inline header label with the standard `<PanelHeader title=... onClose=onClose>` pattern used by `AiPanel` / `NotesPanel`.
  - Keep `ModelBlock`, `ContextBlock`, `HintsBlock` unchanged.

- `resources/js/pages/plot/index.tsx`
  - Add state: `const [coachInsightsOpen, setCoachInsightsOpen] = useState<boolean>(...)` with a one-shot migration reader.
  - In coach mode, render the row layout described above. The page now owns:
    - Passing `contextCounts={coachContextCounts}` directly to `CoachInsightsPanel` (it no longer flows through `CoachPanel`).
    - Wiring `onHintClick={(hint) => coachPanelRef.current?.fillInput(hint)}` — `CoachPanel`'s existing `forwardRef` exposes `fillInput`, so no API change there.
    - Reading `providerLabel` from `useAiFeatures()` (currently consumed inside `CoachPanel`) and forwarding it to `CoachInsightsPanel`.
  - Mount `<AccessBar items={[{ id: 'coach-insights', icon: Lightbulb, label: t('plot-coach:insights.title') }]} openPanels={openPanelsSet} onToggle={...} />`.
  - `openPanelsSet` is a memoised `Set<PanelId>` containing `'coach-insights'` iff `coachInsightsOpen`.

- `resources/js/components/editor/AccessBar.tsx`
  - Add `'coach-insights'` to the `PanelId` union. Documented in the spec as a known small leak — the editor will never toggle it, but the union is shared. Genericising `AccessBar<T extends string>` is deferred (cheaper to add one union member than to thread a generic through every call site).

### State and persistence

- **Open/closed**: `manuscript:plot-coach-insights-open` — single boolean string (`'1'` / `'0'`). Stored at the page level, not per-book (matches the simpler editor panels which also persist app-wide).
- **Width**: `manuscript:coach-insights-panel-width` — managed by `SlidePanel` via `useResizablePanel`. Default 272, min 200, max 400.
- **Migration**: on first read of the new key, if absent, check the legacy `plot-coach-insights-collapsed-${bookId}` key in localStorage. If present and `'1'`, seed `coachInsightsOpen = false`; otherwise `true`. Then write the new key and never read the legacy one again. (We do not delete the legacy key — it's harmless and avoids a bookId-aware cleanup loop.)

### AccessBar contents

- One item: `{ id: 'coach-insights', icon: Lightbulb, label: t('plot-coach:insights.title') }`.
- The bar is mounted only in coach mode. In board mode the layout collapses back to today's structure.

### Behaviour

- Click the AccessBar icon → toggles `coachInsightsOpen`.
- Click the `<PanelHeader onClose>` X inside the panel → sets `coachInsightsOpen = false`.
- `Escape` while focus is inside the panel → closes (free from `SlidePanel`).
- The panel is now visible at all viewport widths (no `hidden lg:block`), matching editor panels.
- The hint-click flow is unchanged: clicking a hint dispatches into `chatRef.current?.fillInput(hint)` via the same callback path.

### Tests

- **Browser test**: `tests/Browser/PlotCoachTest.php` (existing — extend, don't replace).
  - Assert AccessBar icon visible in coach mode, not visible in board mode.
  - Click the icon → panel becomes visible.
  - Click the X in the panel header → panel hides.
  - Reload the page → state persists (open stays open, closed stays closed).
- No backend / feature tests needed — purely a frontend layout change. No controller, route, or migration touched.

### Out of scope

- Multi-monitor "pop out" / detachable window.
- Generalising `AccessBar` to a `<T extends string>` generic.
- Changes to `ChatSurface` widths or `CoachPanel`'s gate states.
- Changes to the toolbar row above the panels (mode toggle, undo, end-session, archive icons stay where they are).

## Risks

- The legacy `plot-coach-insights-collapsed-${bookId}` key migration is per-book in the legacy schema but per-app in the new schema. A user with multiple books who collapsed the panel on book A but not book B will have one of those preferences win once. Acceptable: the panel is one click away from being re-opened.
- Adding `'coach-insights'` to `PanelId` is a cross-page leak. Documented; revisit if a third page needs an `AccessBar`.
