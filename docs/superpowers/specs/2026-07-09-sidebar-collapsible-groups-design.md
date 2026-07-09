# Sidebar: Collapsible "Story" and "Publish" Groups

**Date:** 2026-07-09
**Status:** Approved

## Goal

The book sidebar's nav block is seven flat items tall and squeezes the chapter
list below it. Group the five book-tool items into two collapsible groups so
the chapter list gets more vertical room.

## Structure

In `resources/js/components/editor/Sidebar.tsx`, the "Book" section becomes:

```
BOOK
 Dashboard
 Book Settings
 ▾ Story          ← collapsible, default open
   Wiki
   Plot
   AI
 ▸ Publish        ← collapsible, default collapsed
   Design (Typesetting)
   Export
```

- Group headers use the existing `Collapsible` from `resources/js/components/ui/`,
  styled like a `NavItem` row: chevron + label, `text-xs`, same hover treatment
  as sibling nav items.
- Group children are indented one step relative to the header.
- Two new i18n keys, `nav.story` and `nav.publish`, added to en/de/es locales.
- Icons, routes, and active-state detection for the five items are unchanged.

## Behavior

### Defaults and persistence

- Story defaults **open**; Publish defaults **collapsed**.
- Collapse state persists in `localStorage`, **globally** (not per book) — a
  compact-sidebar preference belongs to the user, not the book.

### Auto-expand on active route

- When the active route lives inside a collapsed group (e.g. landing on
  `/plot` while Story is collapsed), that group renders expanded so the active
  item is visible.
- Auto-expand is a visual override only: it does **not** overwrite the stored
  preference. Only a manual toggle by the user updates `localStorage`.

### Icon-only sidebar mode (48px rail)

- Unchanged. In icon-only mode the grouping disappears and all five icons
  (Wiki, Plot, AI, Design, Export) render individually, exactly as today.
  Grouping exists only in the expanded sidebar.

## Testing

Per the repo guardrails (browser test per feature, not per page), extend the
existing sidebar/editor browser test rather than adding a new file. Cover:

1. Publish is collapsed by default; Design and Export are not visible.
2. Clicking Publish expands it and reveals Design and Export.
3. Navigating to a route inside a collapsed group auto-expands that group.
4. A manually toggled collapse state survives a page reload (localStorage).

No backend changes; no controller or migration work.

## Out of scope

- Flyout/popover menus for groups in icon-only mode.
- Collapsing the General section (Settings, Library) or the whole Book section.
- Renaming or re-ordering the five existing destinations.
