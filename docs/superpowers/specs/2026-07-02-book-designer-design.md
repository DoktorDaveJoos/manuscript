# Book Designer (Typesetting) — Design

**Date:** 2026-07-02
**Status:** Draft — pending user review

## Summary

A new full-page "Book Designer" screen where users do all PDF typesetting work by creating and customizing **templates**. The Export page later slims down to: pick a template, pick front/back matter, order chapters, export. Typesetting knobs move out of Export's `CustomizePanel` into the designer.

Phase 1 (this spec): introduce the Book Designer page + template entity + live two-page preview. Phase 2 (separate spec): integrate templates into the Export page and remove the redundant customize controls.

## Decisions made

| Decision | Choice |
|---|---|
| Template scope | Global (app-wide) library, shared across books. A book references a template in `export_settings`. |
| Built-ins (Classic/Modern/Elegant) | Stay code-defined and read-only. Editing one auto-duplicates it ("Classic (Custom)") into a DB-backed custom template. |
| Preview | Client-side HTML/CSS two-page spread using real book text and real template values; page-turn re-slices content. mPDF preview on the Export page remains the fidelity truth. |
| Front/back matter | *Selection* stays in Export (content decision). *Styling* (chapter openings, TOC style, drop caps) is owned by the template. |
| Trim size | Becomes a template property (as in the mockup: `5" × 8" Classic Novel`). Export keeps only per-run flags (e.g. CMYK). |
| Kerning | Not a user knob — mPDF applies OpenType kerning automatically. |

## Data model

New table `design_templates` (both DBs — default + nativephp):

```
id          bigint pk
name        string          e.g. "Classic (Custom)"
based_on    string          built-in slug the custom started from: classic|modern|elegant
settings    json            full typesetting parameter set (below)
timestamps
```

No `book_id` — global library. No user FK (app has no auth).

`books.export_settings.template` continues to hold a string: a built-in slug (`classic`) **or** `custom:<id>`.

### Settings JSON shape

```jsonc
{
  "page": {
    "trim_size": "5x8",          // existing TrimSize enum values, or "custom"
    "custom_width_mm": null,
    "custom_height_mm": null,
    "bleed_mm": 3.2,
    "bleed_mode": "all",          // all | outer (existing BleedMode)
    "mirrored": true,             // facing pages with inner/outer margins
    "margin_top_mm": 20,
    "margin_bottom_mm": 20,
    "margin_inner_mm": 15,        // gutter
    "margin_outer_mm": 25
  },
  "body": {
    "font": "lato",               // from FontService's available fonts
    "size_pt": 12,
    "leading": 1.35,              // multiplier
    "tracking_em": 0,             // letter-spacing
    "alignment": "justify",       // justify | left
    "hyphenation": true,
    "first_line_indent": true,    // novel style; false = paragraph spacing instead
    "paragraph_spacing_pt": 0,
    "orphans": 2,
    "widows": 2
  },
  "headings": {
    "font": "play",
    "size_pt": 18,
    "style": "chapter",           // maps to existing ChapterHeading (none|number|full)
    "alignment": "center",
    "top_space": "quarter-page",  // quarter-page | third-page | fixed
    "leading": 1.8,
    "drop_caps": false,
    "scene_break_style": "asterisks",  // existing SceneBreakStyle
    "keep_baseline_grid": true    // "Register halten" — snap first text line to grid
  },
  "structure": {
    "running_heads": false,
    "running_head_content": "chapter",  // chapter | book-title | author
    "page_numbers": true,
    "page_number_position": "bottom-center",  // bottom-center | bottom-outer | top-outer
    "include_act_breaks": false
  }
}
```

Built-in templates expose the same shape via a new `ExportTemplate::designSettings(): array` method so the designer can load them as starting points.

## Backend

- **Model** `DesignTemplate` + factory + seeder (empty by default).
- **Controller** `BookDesignController` (new, gets `tests/Feature/BookDesignControllerTest.php` per guardrails):
  - `show(Book $book)` — Inertia page `books/design`, props: built-in templates (slug, name, settings), custom templates, current book's selected template, sample content (first chapter's text for the preview), available fonts + trim sizes.
  - `store` — create custom template (duplicate flow).
  - `update(DesignTemplate $template)` — save settings/name.
  - `destroy(DesignTemplate $template)` — delete custom template (books referencing it fall back to `based_on`).
  - "Apply" additionally PUTs the book's `export_settings.template` via the existing update endpoint.
- **Export pipeline**: `ExportService::resolveTemplate()` recognizes `custom:<id>`, loads the row, and wraps it in a new `CustomTemplate implements ExportTemplate`. `CustomTemplate` delegates base CSS to the `based_on` built-in class and overrides via CSS generated from the settings JSON (page geometry via mPDF config, typography via generated CSS). Unknown/deleted id → fall back to `based_on` or `classic`.
- **Migration** runs against both databases (`php artisan migrate` + `DB_DATABASE=database/nativephp.sqlite php artisan migrate --no-interaction`).

## Frontend

- **Route/nav**: Sidebar `NavItem` "Typesetting" (icon e.g. `BookOpenText`), between Export and its neighbors, Wayfinder-routed to `books.design`.
- **Page** `resources/js/pages/books/design.tsx`, matching the mockup, all in design-system tokens (no pink accents from the mockup — use `accent` sparingly per rules; toggles/CTAs per no-accent-buttons memory: primary CTA = `Button variant="primary"`):
  - **Top-left**: template `Select` — built-ins group + custom group + "Duplicate current…" action.
  - **Left panel — "Page"** (`SectionLabel` + `Card`s): tabs Paper/Content (`ToggleGroup`). Paper: trim size `Select` with description, bleed `Input` + unit, mirrored pages `ToggleRow`, margins (top/bottom/inner/outer `Input`s with current-value hints). Content tab: structure options (running heads, page numbers, act breaks).
  - **Center — preview**: two-page spread component (`components/design/BookPreview.tsx`). Renders the book's first chapter text into page-shaped divs sized proportionally to trim size, with real margins, fonts, leading, alignment, drop caps, heading treatment. Pagination by client-side measurement (fill page 1, overflow to page 2, etc.). Page-turn arrows advance/rewind one spread. Page numbers + running heads rendered on the pages when enabled.
  - **Right panel — "Text"**: Headings group (font `Select`, size `Select`, style, spacing, leading, drop caps/scene break, baseline-grid `Toggle` with explanatory copy) and Body group (font, size, leading, indent toggle). "Aktuell: …" summary lines like the mockup.
  - **Footer actions**: "Open design in new tab" (mPDF preview via existing `previewPdf`, passing the template), "Back", "Apply" (persist template + set as book's template).
- **Editing a built-in**: first change prompts nothing — it silently creates "«Name» (Custom)", switches the selector to it, and shows a toast. Read-only built-ins otherwise.
- All controls are existing `components/ui/` pieces (Select, Input, Toggle, ToggleRow, ToggleGroup, Card, SectionLabel, Button, Kbd where relevant); shadcn only if a needed control is missing.

## Preview fidelity notes

The HTML preview is *approximate* pagination (browser text metrics ≠ mPDF). It's for design decisions, not proofing. The "Open design in new tab" mPDF render is the exact artifact. This is stated in the UI copy near the preview.

## Testing

- `BookDesignControllerTest` (feature): page renders with props; store/update/destroy; duplicate flow; validation of settings shape.
- `ExportService`/`CustomTemplate` unit tests: `custom:<id>` resolution, fallback on deleted template, CSS generation reflects settings (margins, font size, alignment, widows/orphans).
- Browser test `tests/Browser/BookDesignerTest.php` (new feature → one browser test): open page, change a margin, see preview update, apply, template persisted.
- Guardrails: no auth, controller test convention satisfied.

## Out of scope (Phase 2+)

- Export page slim-down (remove CustomizePanel typesetting knobs, template-only selection) — separate spec after designer ships.
- Template styling for EPUB (designer is PDF-first; custom templates fall back to `based_on` EPUB CSS).
- Template import/export/sharing, premium template packs.
- Front/back matter *content* editing (stays in Export).

## Open questions for review

1. Preview approach: I chose client-side HTML spread (fast, live). Alternative was mPDF-rendered page images (pixel-perfect, sluggish) or hybrid. Confirm or switch.
2. Menu label: "Typesetting", "Book Designer", or localized "Buchsatz"? (nav is i18n'd — needs en/de/es keys either way.)
3. Should "Apply" also be per-book only, or should the designer be reachable outside a book context? (Current design: book-scoped page like Export, since the preview needs a book's text.)
