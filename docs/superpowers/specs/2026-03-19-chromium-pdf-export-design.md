# Chromium-Based PDF Export & Pixel-Perfect Preview

**Date:** 2026-03-19
**Status:** Approved

## Problem

The current export preview uses a JavaScript character-width estimation algorithm (`usePreviewPages.ts`) that approximates page breaks, while actual PDF export uses mPDF (a PHP library) with its own rendering engine. These two systems inevitably drift — the preview is never pixel-perfect.

## Solution

Replace both systems with a single HTML/CSS template rendered by Chromium:

1. A **Blade template** is the single source of truth for PDF layout
2. **Preview**: Backend renders the Blade template to PDF via `System::printToPDF()` (NativePHP/Electron), frontend displays pages using pdf.js
3. **Export**: Same Blade template → same `System::printToPDF()` → download file

One rendering engine. Pixel-perfect preview. Zero drift.

## Architecture

```
┌──────────────────────────────────────────────────────┐
│  Export Page (React)                                  │
│                                                       │
│  ┌──────────┐  ┌──────────┐  ┌────────────────────┐  │
│  │ Reading  │  │ Settings │  │  Preview            │  │
│  │ Order    │  │ Panel    │  │  ┌────────────────┐ │  │
│  │          │  │          │  │  │ <canvas> pg 1  │ │  │
│  │          │  │          │  │  └────────────────┘ │  │
│  │          │  │          │  │  ┌────────────────┐ │  │
│  │          │  │          │  │  │ <canvas> pg 2  │ │  │
│  │          │  │          │  │  └────────────────┘ │  │
│  └──────────┘  └──────────┘  └────────────────────┘  │
└──────────────────────────────────────────────────────┘
         │                              ▲
         │ POST /export/preview         │ base64 PDF
         │ {trim_size, font_size, ...}  │
         ▼                              │
┌──────────────────────────────────────────────────────┐
│  Laravel Backend                                      │
│                                                       │
│  1. Render Blade template → HTML string               │
│  2. System::printToPDF($html, $settings) → PDF bytes  │
│  3. Return base64-encoded PDF                         │
└──────────────────────────────────────────────────────┘
```

## Chromium CSS Capabilities

Electron v38 ships Chromium 140, which supports (since Chrome 131):

- **`@page` margin boxes**: All 16 positions (`@top-left`, `@top-right`, `@bottom-left`, `@bottom-right`, etc.)
- **`@page :left` / `@page :right`**: Alternating styles for even/odd pages
- **`counter(page)` / `counter(pages)`**: Automatic page numbering in margin boxes
- **Named pages**: `@page chapter-1 { ... }` with `.chapter-1 { page: chapter-1; }` for per-chapter running headers
- **`widows` / `orphans`**: Native support
- **`break-before: page`**: Explicit page breaks

**Not supported**: `string-set` / `running()` — worked around via named `@page` rules per chapter.

## Components

### 1. Blade Template — `resources/views/export/pdf.blade.php`

Complete standalone HTML document. Receives: `$book`, `$chapters`, `$options` (ExportOptions).

**CSS structure:**
```css
@font-face { font-family: 'Spectral'; src: url('data:font/ttf;base64,...'); }
@font-face { font-family: 'Spectral'; font-style: italic; src: url('data:font/ttf;base64,...'); }

@page {
  size: {{ $trimWidth }}mm {{ $trimHeight }}mm;
}

/* Mirrored margins for book-style binding */
@page :left {
  margin: {{ $marginTop }}mm {{ $marginGutter }}mm {{ $marginBottom }}mm {{ $marginOuter }}mm;
}
@page :right {
  margin: {{ $marginTop }}mm {{ $marginOuter }}mm {{ $marginBottom }}mm {{ $marginGutter }}mm;
}

/* Alternating page numbers (conditional on showPageNumbers) */
@if($options->showPageNumbers)
@page :left  { @bottom-left  { content: counter(page); font-size: 8pt; color: #B5B5B5; } }
@page :right { @bottom-right { content: counter(page); font-size: 8pt; color: #B5B5B5; } }
@endif

/* Per-chapter named pages with running headers */
@foreach($chapters as $i => $chapter)
@page chapter-{{ $i }} {
  @top-right { content: "{{ cssEscape($chapter->title) }}"; font-size: 8pt; color: #B5B5B5; text-transform: uppercase; letter-spacing: 0.1em; }
}
@page chapter-{{ $i }}:left {
  @top-left { content: "{{ cssEscape($book->title) }}"; font-size: 8pt; color: #B5B5B5; text-transform: uppercase; letter-spacing: 0.1em; }
  @top-right { content: none; }
}
/* Suppress running header on first page of each chapter */
@page chapter-{{ $i }}:first {
  @top-left { content: none; }
  @top-right { content: none; }
}
@endforeach

/* Front/back matter: suppress headers and page numbers */
@page matter {
  @top-left { content: none; } @top-right { content: none; }
  @bottom-left { content: none; } @bottom-right { content: none; }
}

/* Typography */
body {
  font-family: 'Spectral', Georgia, serif;
  font-size: {{ $fontSize }}pt;
  line-height: 1.5;
  color: #4A4A4A;
  text-align: justify;
}
p { margin: 0; text-indent: 1.5em; widows: 2; orphans: 2; }
p:first-child, .scene-break + p, h1 + p, .act-break + p { text-indent: 0; }
```

**HTML structure:**
```html
<section class="matter"><!-- Title page --></section>
<section class="matter"><!-- Copyright page --></section>
<section class="matter"><!-- TOC with anchor links (if enabled) --></section>

@foreach($chapters as $i => $chapter)
  {{-- Act break page (if enabled and act changed) --}}
  @if($options->includeActBreaks && $actChanged)
  <section class="matter">
    <div class="act-break">{{ $actTitle }}</div>
  </section>
  @endif

  <section class="chapter-{{ $i }}" style="break-before: page;">
    @if($options->includeChapterTitles)
      <p class="chapter-label" id="chapter-{{ $i }}">Chapter {{ $i + 1 }}</p>
      <h1>{{ $chapter->title }}</h1>
    @endif
    {{-- Drop cap applied to first paragraph of first scene --}}
    {!! $chapter->prepared_content !!}
  </section>
@endforeach

<section class="matter" style="break-before: page;"><!-- Back matter --></section>
```

**Note on `includeTableOfContents`:** The existing `ExportOptions` has both a standalone `includeTableOfContents` boolean and a `frontMatter` array that can contain `FrontMatterType::Toc`. The Blade template uses only the `frontMatter` array approach. The standalone boolean is deprecated — the export page UI already manages TOC via the front matter checklist.

**Font embedding:** Both Spectral regular and italic TTF files (~519KB raw, ~692KB base64) are embedded inline via `@font-face` data URLs to ensure the hidden BrowserWindow can access them. Note: the entire HTML is then base64-encoded again by `System::printToPDF()` for the IPC `data:` URL, resulting in ~1.4MB overhead for fonts alone. This is acceptable for a desktop app but should be validated with a 700-page book to confirm no IPC/data URL limits are hit. If limits are encountered, an alternative is writing the HTML to a temp file and loading via `file://` protocol (would require a NativePHP enhancement or custom Electron handler).

**Drop caps:** The `ContentPreparer::toChapterHtml()` method (moved from the old `PdfExporter::addDropCap()`) wraps the first character of the first paragraph in each chapter with `<span class="drop-cap">`, styled via CSS (`float: left; font-size: Xpt; line-height: 0.8;`).

**CSS string escaping:** The `cssEscape()` helper is defined as a global PHP helper in `app/helpers.php` (or as a Blade directive `@cssEscape`). It escapes `"` → `\"`, `\` → `\\`, and strips newlines — specifically for injecting user strings into CSS `content: "..."` properties.

### 2. Preview Endpoint — `POST /books/{book}/export/preview`

**Controller:** `BookSettingsController::previewPdf`

**Request body:** Same fields as `ExportBookRequest` (format, chapter_ids, trim_size, font_size, include_chapter_titles, etc.)

**Response:**
```json
{
  "pdf": "<base64-encoded PDF>"
}
```

**Implementation:**
```php
public function previewPdf(ExportBookRequest $request, Book $book): JsonResponse
{
    $chapters = ExportService::resolveChapters($book, $request->validated());
    $options = ExportOptions::fromArray($request->validated());

    // Inject AppSetting content for front/back matter
    ExportService::injectMatterText($options, $request->validated());

    $html = view('export.pdf', [
        'book' => $book,
        'chapters' => $chapters,
        'options' => $options,
    ])->render();

    $pdfBase64 = System::printToPDF($html, [
        'preferCSSPageSize' => true,
        'printBackground' => true,
        'margins' => ['top' => 0, 'bottom' => 0, 'left' => 0, 'right' => 0],
    ]);

    return response()->json([
        'pdf' => $pdfBase64,
    ]);
}
```

Note: `margins` set to 0 and `preferCSSPageSize` set to true so `@page` CSS rules control everything.

### 3. Export Endpoint — `POST /books/{book}/settings/export`

**Refactored:** For PDF format, uses the same Blade template + `System::printToPDF()` instead of mPDF.

```php
// In the refactored PdfExporter (now Chromium-based)
$html = view('export.pdf', [...])->render();
$pdfBase64 = System::printToPDF($html, [
    'preferCSSPageSize' => true,
    'printBackground' => true,
    'margins' => ['top' => 0, 'bottom' => 0, 'left' => 0, 'right' => 0],
]);
$pdfBinary = base64_decode($pdfBase64);
file_put_contents($tempPath, $pdfBinary);
return response()->download($tempPath, $filename)->deleteFileAfterSend();
```

### 4. Shared Backend Logic — `ExportService` refactoring

Extract two currently-private methods to public static methods so both preview and export endpoints can use them without duplication:

- `ExportService::resolveChapters(Book $book, array $options): Collection` — chapter loading with ordering and filtering
- `ExportService::injectMatterText(array &$options): void` — mutates the raw options array to inject AppSetting values (copyright, dedication, acknowledgment, about-author text) before `ExportOptions::fromArray()` is called. Note: `ExportOptions` is `final readonly` so mutation must happen on the raw array, not the constructed object.

The existing `ExportService::export()` method must also be updated to call `self::resolveChapters(...)` and `self::injectMatterText(...)` instead of the old private instance methods.

### 5. Frontend — `ExportPreview.tsx` (rewritten)

**Dependencies:** `pdfjs-dist` (npm package)

**Behavior:**
1. On mount and on settings change (debounced 500ms), sends POST to preview endpoint
2. Receives base64 PDF, loads it into pdf.js
3. Renders each page to a `<canvas>` element
4. Displays canvases in a `Virtuoso` virtual list with shadows and gaps

**Request cancellation:** Use `AbortController` to cancel in-flight preview requests when new settings arrive. Only apply the response if it corresponds to the most recent request, preventing stale responses from overwriting fresher ones.

**Page rendering:**
```tsx
// For each page:
const page = await pdfDoc.getPage(pageNum);
const viewport = page.getViewport({ scale: scaleFactor });
const canvas = canvasRef.current;
canvas.width = viewport.width;
canvas.height = viewport.height;
await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
```

**Scale factor:** Pages are rendered at a preview width of 340px.
```
pageWidthPt = trimWidthMm / 25.4 * 72   // convert mm to PDF points
scaleFactor = 340 / pageWidthPt
```

**Page visualization (shadows + gaps):**
```tsx
<div className="flex justify-center px-7 pb-3">
  <div className="overflow-hidden rounded-[3px] shadow-[0_6px_20px_#00000014]"
       style={{ width: 340, height: pageHeight, backgroundColor: '#FFFEFA' }}>
    <canvas ref={canvasRef} className="w-full h-full" />
  </div>
</div>
```

### 6. Loading States

| State | UI |
|-------|-----|
| Initial load (no PDF yet) | Page-shaped skeleton rectangles with pulse animation |
| Settings changed, waiting for debounce | No change (keep showing current pages) |
| Request in flight | Semi-transparent overlay on existing pages + subtle spinner in header |
| PDF received, rendering pages | Progressive — show pages as they render |
| Error | Toast notification, keep showing last successful preview |

### 7. Route

```php
// routes/web.php
Route::post('/books/{book}/export/preview', [BookSettingsController::class, 'previewPdf'])
    ->name('books.export.preview');
```

## Files Changed

### New files
- `resources/views/export/pdf.blade.php` — Blade template for PDF layout

### Modified files
- `app/Http/Controllers/BookSettingsController.php` — add `previewPdf`, refactor `doExport`
- `app/Services/Export/ExportService.php` — extract `resolveChapters` and `injectMatterText` to public static, use Chromium for PDF format
- `app/Services/Export/ContentPreparer.php` — rename `toPdfHtml` → `toChapterHtml`, add drop cap logic, update docstrings
- `resources/js/components/export/ExportPreview.tsx` — rewrite with pdf.js
- `resources/js/pages/books/export.tsx` — remove usePreviewPages props, add preview fetching
- `routes/web.php` — add preview route

### Replaced files
- `app/Services/Export/Exporters/PdfExporter.php` — rewritten to use `System::printToPDF()` + the Blade template instead of mPDF. Same class name and interface (`Exporter`), new implementation. `ExportService::resolveExporter()` continues to dispatch `ExportFormat::Pdf` to `PdfExporter` unchanged.

### Removed files
- `resources/js/components/export/usePreviewPages.ts` — JS estimation logic

### Removed dependencies
- `mpdf/mpdf` (composer)

### New dependencies
- `pdfjs-dist` (npm)

## What Stays Unchanged

- `ExportSettings.tsx` — settings panel UI
- `ExportReadingOrder.tsx` — reading order panel UI
- `app/Services/Export/Exporters/EpubExporter.php` — EPUB export (future improvement)
- `app/Services/Export/Exporters/TxtExporter.php` — plain text export
- `app/Services/Export/Exporters/DocxExporter.php` — DOCX export
- `app/Services/Export/Templates/ClassicTemplate.php` — design tokens still useful for future template system

## Non-NativePHP Detection

`System::printToPDF()` only works inside Electron. To detect whether the app is running in NativePHP, check `app()->bound(\Native\Desktop\Contracts\System::class)` or the presence of the `NATIVEPHP_RUNNING` environment variable. When not in NativePHP:

- Preview endpoint returns `{ "error": "PDF preview requires the desktop app" }` with HTTP 422
- Frontend shows a placeholder message instead of the preview panel
- Export falls back gracefully (show message suggesting desktop app)

## Testing Strategy

- **Blade template rendering**: Unit test that the view renders valid HTML given known book/chapter/options inputs — testable without Electron
- **ExportService logic**: Feature tests for `resolveChapters`, `injectMatterText` — no Electron dependency
- **Preview endpoint**: Feature tests that mock `System::printToPDF` to return a known base64 PDF, then assert JSON response structure
- **CSS string escaping**: Unit test `cssEscape()` with titles containing `"`, `\`, newlines, special characters
- **Frontend**: Manual testing of pdf.js rendering, loading states, AbortController cancellation
- **End-to-end**: Full integration test requires running inside NativePHP (manual or CI with Electron)

## Edge Cases

- **Empty book (no chapters selected)**: Show "No content" placeholder, don't generate PDF
- **Very large books (700+ pages)**: PDF generation may take a few seconds; loading state handles this. Validate that IPC payload size (double-base64 fonts + content) does not hit limits.
- **Titles with special characters**: `cssEscape()` helper handles `"`, `\`, newlines in CSS content strings
- **Font loading failure**: Fall back to `Georgia, serif` in the font stack

## Performance Expectations

- **Chromium PDF rendering**: ~2-5s for a 700-page book (vs 40-60s with mPDF)
- **pdf.js page rendering**: ~50-100ms per page, virtualized so only visible pages render
- **Debounce**: 500ms after last settings change before triggering re-render
- **Total perceived latency**: 3-6s for a large book, <1s for typical books (50-100 pages)

## Known Issues & Risks

**Named page `:first` pseudo-class:** The spec uses `@page chapter-0:first { ... }` to suppress running headers on chapter opener pages. While Chrome 131+ supports `@page :first`, the compound form with a named page should be validated during implementation. If unsupported, fallback is to use a separate named page for opener pages.

**Vendor MIME type bug:** The NativePHP Electron plugin has a malformed MIME type in the data URL at `electron-plugin/src/server/api/system.ts` line 120:
```js
await printWindow.loadURL(`data:text/html;base64;charset=UTF-8,${html}`);
```
The correct format is `data:text/html;charset=UTF-8;base64,${html}`. This should be verified during implementation — if it causes issues, patch locally or submit upstream PR.
