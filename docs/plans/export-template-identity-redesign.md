# Export Template Identity Redesign

## Goal

Differentiate the three export templates so each serves a distinct audience: commercial genre fiction, traditional publisher standard, and literary/decorative.

---

## Template Specifications

### Modern — "Commercial / Thriller"

**Chapter opening:**
- Number only (no "Chapter" word), left-aligned
- Title on separate line below number, left-aligned
- Number: bold, body font (Source Serif 4), ~1em
- Title: Source Sans 3, ~1.2em, regular weight
- Moderate chapter sink (~4–5em)

**Defaults (unchanged):**
- Scene breaks: Rule (`———`)
- Drop caps: off
- Running headers: normal style
- Font pairing: ModernMixed (Source Sans 3 headings / Source Serif 4 body)

---

### Classic — "Publisher Standard"

**Chapter opening:**
- `CHAPTER 1` label — uppercase, letter-spaced, centered, gray (current format)
- Title: Crimson Pro, 1.8em (bumped from 1.6em), normal weight, centered
- Full 1/3 page chapter sink (~8–10em, up from 2em)

**Defaults (unchanged):**
- Scene breaks: Asterisks (`* * *`)
- Drop caps: off
- Running headers: italic
- Font pairing: ClassicSerif (Crimson Pro throughout)

---

### Elegant — "Literary"

**Chapter opening:**
- `Chapter One` label — title case, no uppercase, no letter-spacing, centered
- Spelled-out English numbers up to ninety-nine; numeral fallback for other locales
- Title: Cormorant Garamond, 2.0em (unchanged), normal weight, centered
- Full 1/3 page chapter sink (~8–10em, up from 2em)

**Defaults changed:**
- Drop caps: ON by default, normal weight (not bold)

**Defaults unchanged:**
- Scene breaks: Flourish (`~❋~`)
- Running headers: italic
- Font pairing: ElegantSerif (Cormorant Garamond headings / Crimson Pro body)

---

## Architecture Changes

### 1. New `chapterHeaderHtml()` method

Add to `ExportTemplate` contract:

```php
public function chapterHeaderHtml(int $index, string $title, string $locale = 'en'): string;
```

Each template provides its own chapter opening markup. This replaces the hardcoded Blade template fragment.

### 2. Update `pdf.blade.php`

Replace the current chapter header block:

```php
{{-- Before --}}
@if ($options->includeChapterTitles)
    <p class="chapter-label" id="chapter-{{ $index }}">
        {{ __('Chapter :number', ['number' => $index + 1]) }}
    </p>
    <h1>{{ $chapter->title }}</h1>
@endif

{{-- After --}}
@if ($options->includeChapterTitles)
    {!! $template->chapterHeaderHtml($index, $chapter->title, app()->getLocale()) !!}
@endif
```

### 3. Number-to-word helper

English lookup array for 1–99. Used only by `ElegantTemplate::chapterHeaderHtml()`.

Location: a static method on `ElegantTemplate` or a small helper — no external package needed.

### 4. CSS changes per template

**ModernTemplate:**
- `.chapter-label`: remove uppercase/tracking, left-align, ~1em bold
- `h1`: left-align, shrink to ~1.2em, regular weight, sans-serif
- Chapter sink: ~4–5em top margin

**ClassicTemplate:**
- `h1`: bump from 1.6em → 1.8em
- `.chapter-label`: increase top margin to ~8–10em (1/3 page sink)

**ElegantTemplate:**
- `.chapter-label`: title case (handled in HTML), remove uppercase/tracking
- `.chapter-label`: increase top margin to ~8–10em (1/3 page sink)
- `.drop-cap`: change `font-weight` from `bold` to `normal`
- `defaultDropCaps()`: return `true`

### 5. Design tokens

Update `designTokens()` arrays to reflect new values so the frontend preview stays in sync.

### 6. EPUB CSS

Apply matching changes to `epubCss()` for each template to keep PDF and EPUB consistent.

---

## Out of Scope

- Font pairing renaming or new pairings
- Multi-language spelled-out numbers (numeral fallback is fine)
- New templates beyond these three
- Changes to scene break styles, running headers, or page number positioning
