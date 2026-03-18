# Export Preview Rendering — Audit & Improvement Plan

## Current Architecture Summary

The export preview is a **hand-rolled, client-side pagination engine** with no external libraries (no Paged.js, pdf.js, or similar). It renders fixed-size `<div>` page shells (340×473px) inside a scrollable container.

### How It Works Today

1. **`usePreviewPages.ts`** — Character-based line estimation to split content into pages:
   - Estimates `charsPerLine = floor(264px / (bodySize × 0.55))`
   - Estimates `paraLines = ceil(text.length / charsPerLine)`
   - Splits paragraphs at word boundaries when they overflow a page
   - No actual DOM measurement — purely arithmetic

2. **`ExportPreview.tsx`** — Renders each page as a fixed-size div with Tailwind classes:
   - Hardcoded 340×473px page shells, no aspect-ratio scaling from trim size
   - Running headers (PDF only), chapter titles, drop caps, page numbers
   - All pages rendered in DOM at once — no virtualization

3. **PDF Export (`PdfExporter.php`)** — mPDF library with CSS for page breaks, widows/orphans
4. **EPUB Export (`EpubExporter.php`)** — Manual ZIP builder, EPUB3 spec
5. **DOCX/TXT** — PhpWord and plain text formatters

---

## Issues Found (Ranked by Impact)

### 1. CRITICAL: Preview pagination diverges from actual export

**Problem:** The preview uses character-count heuristics (`text.length / charsPerLine`) while the actual PDF uses mPDF's real text layout engine with font metrics, kerning, and word-wrap. This means the preview page breaks **will not match** the exported PDF.

- Character width estimated at `0.55 × fontSize` — this assumes monospaced-like character widths, but Literata is a proportional serif font where "m" is ~2× wider than "i"
- No consideration of hyphenation, justified text reflow, or kerning
- Line height `1.75` in preview vs `1.5` in PDF CSS — **these don't even match**
- Preview shows N pages but exported PDF may show N±20% different page count

**Industry standard:** Tools like Vellum, Atticus, and Reedsy either (a) use the same rendering engine for preview and export, or (b) use CSS Paged Media (Paged.js) in the browser that closely matches their export engine.

**Recommendation:** Either:
- **(A) Use Paged.js** for browser-side pagination — it uses real DOM measurement and CSS `@page` rules, so page breaks match what a CSS-based PDF engine would produce. This is the approach used by tools like Editoria, Cabbage Tree Labs, and several open-source publishing platforms.
- **(B) Render preview server-side** — Generate actual PDF pages via mPDF, convert to images, display in preview. More accurate but slower.
- **(C) Keep the heuristic approach but label it** as "approximate preview" and improve accuracy (see below).

### 2. HIGH: Line height mismatch between preview and PDF

**Problem:**
- Preview: `line-height: 1.75` (in `ExportPreview.tsx` line 226 and `usePreviewPages.ts` line 195)
- PDF CSS: `line-height: 1.5` (in `PdfExporter.php` line 78)
- EPUB CSS: `line-height: 1.6` (in `EpubExporter.php` line 74)

Three different line heights across three outputs. The preview is the loosest at 1.75, meaning text will appear more spacious than in the actual export.

**Recommendation:** Standardize on a single line height value, or at least make the preview match the target format's line height.

### 3. HIGH: Trim size is ignored in preview layout

**Problem:** The `trimSize` prop is passed to `usePreviewPages` but **never used** — it only appears in the `useMemo` dependency array. The page dimensions are hardcoded at 340×473px regardless of whether the user selects Pocket (5×8"), US Trade (6×9"), or Manuscript (8.5×11").

- Pocket is 5:8 ratio (0.625), preview is 340:473 ratio (0.719) — significant distortion
- US Trade is 6:9 ratio (0.667) — closer but still wrong
- Manuscript 8.5:11 is (0.773) — totally different proportions

The user changes trim size and sees zero visual feedback in the preview.

**Recommendation:** Scale `PAGE_WIDTH` / `PAGE_HEIGHT` to match the selected trim size's aspect ratio. The width can stay at 340px, but the height should be `340 × (trimHeight / trimWidth)`. Similarly, margin proportions should reflect the actual trim size margins.

### 4. HIGH: No virtualization — all pages rendered in DOM

**Problem:** Every page shell is rendered as a real DOM element. For a 100-chapter novel at ~250 words/page, a 80,000-word book could produce 320+ page shells all in the DOM simultaneously. Each with shadows, text rendering, etc.

**Impact:** On a MacBook this is probably fine, but since this is a NativePHP/Electron app, memory pressure matters. Also, every state change re-runs the entire `useMemo` and re-renders all page shells.

**Industry standard:** Virtual scrolling (react-window, react-virtuoso) renders only visible pages. Google Docs and Overleaf both virtualize their page views.

**Recommendation:** Use `react-virtuoso` or `react-window` to only render pages in/near the viewport. The fixed page height makes this straightforward.

### 5. MEDIUM: No widows/orphans control in preview

**Problem:** The preview's `splitParagraphsIntoPages` has a simple 30% minimum fragment check, but no widow/orphan awareness:
- A single line at the top of a new page (widow) is not detected
- A single line left at the bottom before a page break (orphan) is not detected
- The PDF export CSS has `widows: 2; orphans: 2;` — but the preview doesn't replicate this

**Recommendation:** Add widow/orphan enforcement to the splitting algorithm: if a page break would leave ≤1 line on either side, pull 1-2 more lines to the next page.

### 6. MEDIUM: Preview doesn't show justified text

**Problem:** The preview renders with default `text-align: start` (left-aligned), but the PDF and EPUB both use `text-align: justify`. Justified text reflows differently — it can produce different line breaks, especially with longer words.

**Recommendation:** Add `text-align: justify` to body text paragraphs in the preview, and consider enabling CSS `hyphens: auto` to match professional typesetting.

### 7. MEDIUM: Drop cap implementation is fragile

**Problem:** The drop cap uses a floated `<span>` with arbitrary sizing (`44pt * scale`). Issues:
- Only shows on the first paragraph of the **first** chapter (`page.isFirst && i === 0`) — most book interiors have a drop cap on every chapter's opening paragraph
- No drop cap height calculation relative to line count (professional drop caps span exactly N lines)
- `float: left` with `mt-0.5 mr-1` is imprecise — proper drop caps align to the baseline of the Nth line

**Industry standard:** Professional tools use `initial-letter` CSS property (Safari) or precise calculations to align the drop cap's baseline with the 2nd or 3rd body text line.

**Recommendation:** If drop caps are a feature, they should appear at every chapter opening and use precise baseline alignment.

### 8. MEDIUM: PDF headers/footers are simplistic

**Problem:**
- Header: book title centered, italic — but professional books have alternating recto/verso headers (book title on left pages, chapter title on right pages)
- Footer: centered page number — professional books place page numbers in the outer margin
- First page of each chapter should suppress the running header (already partially handled by mPDF defaults)
- No support for front matter roman numeral page numbering

**Recommendation:** Use mPDF's `SetHTMLHeaderByName` / odd/even header support for proper alternating headers. Suppress headers on chapter-opening pages.

### 9. MEDIUM: EPUB missing semantic markup

**Problem:** The EPUB chapters don't use `epub:type` attributes for semantic enrichment:
- No `epub:type="bodymatter"`, `frontmatter`, `backmatter`
- No `epub:type="chapter"` on chapter sections
- Scene breaks use `<hr class="scene-break">` instead of `<hr epub:type="pagebreak">` or similar
- Missing cover page / title page / copyright XHTML files in EPUB

**Recommendation:** Add semantic EPUB3 `epub:type` attributes. Many e-readers use these for navigation, accessibility, and skipping front matter.

### 10. LOW: Content preparer strips all inline formatting

**Problem:** `ContentPreparer::toPlainText()` strips all tags, meaning bold, italic, and other inline formatting is lost even for PDF and EPUB where it should be preserved. The `toXhtml()` and `toPdfHtml()` methods preserve HTML tags but:
- No `<em>` / `<strong>` normalization
- TipTap's `<mark>`, `<code>`, `<u>` tags pass through without EPUB/PDF-aware styling

**Recommendation:** Ensure inline formatting (`<em>`, `<strong>`, `<a>`) is preserved and styled in all export formats.

### 11. LOW: Font handling is limited

**Problem:**
- Only two font files: Literata Regular and Italic — no Bold or Bold Italic variants
- The preview uses browser `font-serif` (likely Times New Roman) — doesn't load Literata at all
- Mismatch: preview shows system serif font, export shows Literata

**Recommendation:** Load Literata via `@font-face` in the preview CSS so WYSIWYG is maintained. Add Bold/BoldItalic variants if the editor supports bold text.

### 12. LOW: No front/back matter content in export

**Problem:** Front and back matter pages (title page, copyright, dedication, acknowledgments, about author, also by) appear in the preview as placeholder text but have **no implementation in the actual exporters**. The exporters only handle chapters.

**Recommendation:** Wire up front/back matter content to the export pipeline. At minimum, add a title page and copyright page to PDF and EPUB exports.

---

## Quick Wins (Minimal effort, meaningful improvement)

| # | Fix | Effort | Files |
|---|-----|--------|-------|
| 1 | Match line-height across preview/PDF/EPUB (use 1.5) | 5 min | `usePreviewPages.ts`, `ExportPreview.tsx` |
| 2 | Scale page shell to trim size aspect ratio | 15 min | `ExportPreview.tsx`, `usePreviewPages.ts` |
| 3 | Add `text-align: justify` to preview paragraphs | 2 min | `ExportPreview.tsx` |
| 4 | Load Literata font in preview via @font-face | 10 min | CSS / Tailwind config |
| 5 | Label preview as "approximate" if keeping heuristic | 2 min | `ExportPreview.tsx` |

## Medium-Term Improvements

| # | Fix | Effort | Files |
|---|-----|--------|-------|
| 6 | Add react-virtuoso for page virtualization | 1-2 hr | `ExportPreview.tsx` |
| 7 | Widow/orphan enforcement in pagination | 1 hr | `usePreviewPages.ts` |
| 8 | Alternating recto/verso headers in PDF | 1 hr | `PdfExporter.php` |
| 9 | Add epub:type semantic attributes | 30 min | `EpubExporter.php` |
| 10 | Drop cap on every chapter opening | 30 min | `ExportPreview.tsx`, `usePreviewPages.ts` |

## Longer-Term / Architectural Improvements

| # | Fix | Effort | Impact |
|---|-----|--------|--------|
| 11 | Replace heuristic pagination with Paged.js | 1-2 days | Eliminates preview/export divergence |
| 12 | Replace mPDF with Paged.js + `webContents.printToPDF()` | 2-3 days | Same Chromium engine for preview AND export = zero divergence |
| 13 | Front/back matter in actual exports | 2-4 hr | Complete export feature |
| 14 | Font subsetting for EPUB file size | 2-4 hr | Smaller EPUB files |

### The Electron Advantage (Key Architectural Insight)

Since Manuscript is a NativePHP/Electron app, there's a unique opportunity most web apps don't have:

1. Render HTML + CSS Paged Media (via Paged.js) in a **hidden BrowserWindow**
2. For **preview**: display those pages in the export sidebar (via iframe or captured images)
3. For **PDF export**: call `webContents.printToPDF()` on the same rendered content

This means **preview and export use the identical Chromium rendering engine** — same font metrics, same line breaks, same page breaks. Zero divergence by construction. This eliminates mPDF entirely and is the approach Atticus uses (Electron + Chromium for both preview and export).

The hidden BrowserWindow approach also means pagination doesn't block the main UI thread.

---

## Industry Comparison

| Feature | Manuscript (current) | Vellum | Atticus | Reedsy | Scrivener |
|---------|---------------------|--------|---------|--------|-----------|
| Preview engine | Char-count heuristic | Native macOS text layout | Electron + Chromium | Server-side PDF | Cocoa text system |
| Preview matches export | No — divergent | Yes — same engine | Close — same Chromium | Yes — actual PDF | Approximate |
| Trim size in preview | Ignored | Yes | Yes | Yes | N/A |
| Widows/orphans | No (preview), Yes (PDF) | Yes | Yes | Yes | Yes |
| Justified text | No (preview), Yes (export) | Yes | Yes | Yes | Yes |
| Virtualized pages | No | Yes (native) | Unknown | N/A (thumbnail) | Native |
| Drop caps | First chapter only | All chapters | All chapters | Configurable | No |
| Running headers | Centered title only | Alternating recto/verso | Alternating | Full control | Basic |
| Front/back matter export | Preview only | Full | Full | Full | Manual |

---

## Recommended Priority Order

1. **Quick wins 1-5** — Immediate visual accuracy improvements, trivial effort
2. **Trim size scaling (#3 above)** — Users expect visual feedback when changing trim size
3. **Virtualization (#6)** — Important for NativePHP memory constraints
4. **Widow/orphan + headers (#7, #8)** — Professional quality
5. **Paged.js migration (#11)** — Eliminates the fundamental preview/export accuracy gap, but requires significant refactoring
