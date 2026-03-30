# Export Templates Hardening

**Date:** 2026-03-25
**Status:** Approved

## Goal

Harden the 3 export templates (Classic, Modern, Romance→Elegant) to be rock-solid, covering all edge cases that real book content produces. Make them a curated go-to package that works great for modern literature without being fancy.

## Decisions

### 1. Rename Romance → Elegant

Rename the `RomanceTemplate` class, slug, and all frontend references from "romance" to "elegant". The Cormorant Garamond + Crimson Pro pairing is versatile for literary fiction, historical fiction, memoirs — not just romance. The rename removes the genre pigeonhole.

**Files affected:**
- `app/Services/Export/Templates/RomanceTemplate.php` → rename file to `ElegantTemplate.php`, rename class + slug + name
- `app/Services/Export/ExportService.php` → update `resolveTemplate()` match and import. Keep `'romance'` as a fallback alias mapping to ElegantTemplate for backward compatibility.
- `app/Http/Requests/ExportBookRequest.php` → update validation rule from `'in:classic,modern,romance'` to `'in:classic,modern,elegant'`
- `app/Http/Controllers/BookSettingsController.php` → update import and template list construction
- `resources/js/components/export/TemplateSelector.tsx` / `TemplateCard.tsx` — update labels/slugs
- `resources/js/i18n/en/export.json` (and `de`, `es`) — update template name strings
- `resources/js/components/export/types.ts` — if template slugs are typed
- `tests/Feature/Export/ExportIntegrationTest.php` — update romance references in test data
- Database migration: `UPDATE books SET export_template = 'elegant' WHERE export_template = 'romance'` — run against both default and NativePHP databases

**Note:** `resources/js/components/onboarding/CreateBookDialog.tsx` has a "romance" reference — this is a Genre selector, not a template reference. Intentionally out of scope.

### 2. Drop Caps Off By Default

Change `defaultDropCaps()` to return `false` on Classic and Elegant (Modern already returns false). Drop caps remain available as an opt-in toggle — just not the default.

**Files affected:**
- `ClassicTemplate.php` — `defaultDropCaps()` returns `false`
- `RomanceTemplate.php` (Elegant) — `defaultDropCaps()` returns `false`

### 3. Add Blockquote Styling

TipTap StarterKit includes blockquotes. Currently zero CSS for them in PDF or EPUB. Add to all 3 templates' `baseCss()` and `epubCss()`:

```css
blockquote {
    margin: 1em 0 1em 2em;
    font-size: 0.95em;
    font-style: italic;
}
blockquote p {
    text-indent: 0;
}
```

### 4. Add List Styling

TipTap StarterKit includes `<ul>` and `<ol>`. Currently zero CSS for them. Add to all 3 templates' `baseCss()` and `epubCss()`:

```css
ul, ol {
    margin: 0.8em 0 0.8em 2em;
    padding: 0;
}
li {
    margin: 0.2em 0;
    text-indent: 0;
}
```

### 5. Add PDF Hyphenation

All 3 PDF templates use `text-align: justify` but lack hyphenation, causing rivers of whitespace — especially on narrow trim sizes. EPUB already has `hyphens: auto`. Add to `baseCss()` body rule for all 3 templates:

```css
body {
    /* existing properties... */
    hyphens: auto;
    -webkit-hyphens: auto;
}
```

### 6. Fix Drop Cap Regex for Inline Formatting

The `addDropCap()` method in `ContentPreparer.php` fails silently when the first paragraph starts with `<em>`, `<strong>`, or other inline tags. Extend the regex to skip past opening inline tags before matching the first letter, preserving the tag structure:

**Before:** `<p><em>The morning...</em></p>` → no drop cap (regex fails)
**After:** `<p><em><span class="drop-cap">T</span>he morning...</em></p>` → drop cap works

The regex should:
- Handle nested tags like `<p><em><strong>T...`
- Handle tags with attributes like `<em class="...">`
- Use a pattern like `(<[^>]+>)*` to skip any number of opening tags
- Inject the `<span class="drop-cap">` inside the innermost tag, wrapping only the first letter
- A test case for `<p><em>Text...</em></p>` should be added alongside the fix

### 7. Scene Break Page-Break Protection on Modern

Classic and Elegant have `page-break-before: avoid; page-break-after: avoid` on scene breaks in their `sceneBreakCss()`. Modern template is missing these. Add them for consistency — prevents scene break symbols from being stranded at page boundaries.

### 8. Drop Accent Colors

Remove the `accentColor` concept. Real books don't use colored accents in interiors. Align all 3 templates with professional publishing conventions:

- **Primary elements** (chapter labels, scene breaks, matter titles): use body text color
- **Secondary elements** (running headers, page numbers, copyright, author name on title page): use neutral gray

Color mapping per template:

| Element | Classic | Modern | Elegant |
|---------|---------|--------|---------|
| Body text | `#2a2a2a` | `#333333` | `#2a2a2a` |
| Headings | `#1a1a1a` | `#111111` | `#1a1a1a` |
| Chapter label | `#999999` | `#888888` | `#999999` |
| Scene break | `#999999` | `#888888` | `#999999` |
| Running header | `#999999` | `#aaaaaa` | `#999999` |
| Page numbers | `#999999` | `#aaaaaa` | `#999999` |
| Matter titles | `#999999` | `#888888` | `#999999` |
| Copyright text | `#999` | `#888` | `#999` |
| Author (title page) | `#999999` | `#888888` | `#999999` |
| Drop cap (when enabled) | `#1a1a1a` | N/A (no drop cap CSS) | `#1a1a1a` |

For Elegant, this means replacing **all** `#8b7355` references across all methods — `baseCss()`, `epubCss()`, `sceneBreakCss()`, and `dropCapCss()` — with the appropriate gray or heading color per the table above.

Remove the `accentColor` key from `designTokens()` in all 3 templates. If the frontend uses it for template card differentiation, replace with a non-CSS preview color or remove the dependency.

### 9. Front Matter Padding — No Change

Leave percentage-based padding as-is. Mass Market trim is rare for self-publishing. The current values work well for the 3 most common sizes (5×8, 5.5×8.5, 6×9).

## Scope Boundaries

**In scope:**
- All changes to the 3 PHP template classes
- ContentPreparer drop cap regex fix
- EPUB CSS consistency (same blockquote, list, hyphenation fixes)
- Frontend rename from Romance → Elegant
- i18n string updates
- Migration for stored `template = 'romance'` preferences

**Out of scope:**
- Code block styling (edge case for book writing)
- Small caps after drop caps (fancy, not needed)
- Conditional front matter padding per trim size
- New templates or font pairings
- Changes to the Blade template structure
- Genre selectors in onboarding (CreateBookDialog "romance" is a genre, not a template)
