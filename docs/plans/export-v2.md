# Export v2: Compete with Reedsy & KDP

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upgrade the export system to competitive parity with Reedsy and Kindle Create, with a template pack architecture that lays the foundation for premium template sales and an eventual Vellum-competitive offering.

**Architecture:** A new Publish page handles per-book matter content and cover image upload. The Export page is refactored with a template card selector and collapsible customization panel. Three templates ship in a "Basic Pack" with distinct font pairings, scene break ornaments, and drop cap defaults. Content pipeline is fixed to preserve inline formatting. DOCX is upgraded to proper manuscript submission format.

**Tech Stack:** Laravel 12, Inertia.js v2 + React 19, TailwindCSS v4, mPDF (PDF), PhpWord (DOCX), ZipArchive (EPUB), Pest 4 (testing)

**Key Skills:** @pest-testing, @inertia-react-development, @tailwindcss-development, @design-system, @wayfinder-development

---

## File Structure

### New Files

```
# Backend - Enums
app/Enums/SceneBreakStyle.php              — Scene break ornament options
app/Enums/FontPairing.php                  — Curated font pairings (heading + body)

# Backend - Templates
app/Services/Export/Templates/ModernTemplate.php   — Modern template (sans-serif headings)
app/Services/Export/Templates/RomanceTemplate.php  — Romance template (decorative headings)

# Backend - Publish Page
app/Http/Controllers/PublishController.php         — Publish page controller
app/Http/Requests/UpdatePublishSettingsRequest.php — Validation for publish page form
app/Http/Requests/UploadCoverImageRequest.php      — Validation for cover upload

# Backend - Migrations
database/migrations/XXXX_add_publish_fields_to_books_table.php
database/migrations/XXXX_add_export_preferences_to_books_table.php

# Frontend - Publish Page
resources/js/pages/books/publish.tsx                        — Publish page
resources/js/components/publish/PublishMetadata.tsx          — Metadata section
resources/js/components/publish/PublishFrontMatter.tsx       — Front matter editing
resources/js/components/publish/PublishBackMatter.tsx        — Back matter editing
resources/js/components/publish/CoverImageUpload.tsx         — Cover image upload

# Frontend - Export Refactor
resources/js/components/export/TemplateSelector.tsx          — Template card grid
resources/js/components/export/TemplateCard.tsx               — Individual template card
resources/js/components/export/CustomizePanel.tsx             — Collapsed customize overrides

# Fonts
resources/fonts/CrimsonPro-Regular.ttf
resources/fonts/CrimsonPro-Italic.ttf
resources/fonts/CrimsonPro-Bold.ttf
resources/fonts/CrimsonPro-BoldItalic.ttf
resources/fonts/SourceSerif4-Regular.ttf
resources/fonts/SourceSerif4-Italic.ttf
resources/fonts/SourceSerif4-Bold.ttf
resources/fonts/SourceSerif4-BoldItalic.ttf
resources/fonts/SourceSans3-Regular.ttf
resources/fonts/SourceSans3-Bold.ttf
resources/fonts/CormorantGaramond-Regular.ttf
resources/fonts/CormorantGaramond-Italic.ttf
resources/fonts/CormorantGaramond-Bold.ttf
resources/fonts/CormorantGaramond-BoldItalic.ttf

# i18n
resources/js/i18n/en/publish.json

# Tests
tests/Feature/PublishControllerTest.php
tests/Feature/Export/TemplateSystemTest.php
tests/Feature/Export/InlineFormattingTest.php
tests/Feature/Export/DropCapTest.php
tests/Feature/Export/SceneBreakStyleTest.php
tests/Feature/Export/DocxExporterTest.php
tests/Feature/Export/CoverImageTest.php
tests/Feature/Export/MatterTypesTest.php
```

### Modified Files

```
# Backend
app/Enums/FrontMatterType.php              — Add Dedication, Epigraph cases
app/Enums/BackMatterType.php               — Add AlsoBy, Epilogue cases
app/Models/Book.php                        — Add publish fields, cover, export prefs
app/Services/Export/ExportService.php       — Template resolution, matter injection
app/Services/Export/ExportOptions.php       — New fields (font pairing, scene break, drop caps, cover, new matter)
app/Services/Export/ContentPreparer.php     — Fix inline formatting, activate drop caps, scene break styles
app/Services/Export/FontService.php         — Multiple font pairings support
app/Services/Export/Exporters/EpubExporter.php  — Cover, matter types, formatting, templates
app/Services/Export/Exporters/PdfExporter.php   — Templates, formatting, scene breaks, drop caps
app/Services/Export/Exporters/DocxExporter.php  — Proper manuscript format
app/Services/Export/Exporters/KdpExporter.php   — Cover requirement
app/Contracts/ExportTemplate.php            — Add sceneBreakHtml(), dropCapEnabled() methods
app/Http/Controllers/BookSettingsController.php — Pass template/font/scene-break data to export page
app/Http/Requests/ExportBookRequest.php     — New validation rules
routes/web.php                              — Publish page routes

# Frontend
resources/js/pages/books/export.tsx         — Template selector, customize panel, new settings
resources/js/components/export/ExportSettings.tsx   — Refactored with template cards + customize
resources/js/components/export/ExportReadingOrder.tsx — Epilogue in back matter, "Edit in Publish" links
resources/js/components/export/types.ts     — New types
resources/js/components/editor/Sidebar.tsx  — Add "Publish" nav item
resources/js/types/models.ts               — Book type updates
resources/js/i18n/en/export.json           — New translation keys
```

---

## Phase 1: Database & Enums

### Task 1: Database Migration — Book Publish Fields

Move matter content from global AppSettings to per-book fields. Add cover image, metadata, and chapter epilogue flag.

**Files:**
- Create: `database/migrations/XXXX_add_publish_fields_to_books_table.php`
- Modify: `app/Models/Book.php`
- Modify: `app/Models/Chapter.php`
- Test: `tests/Feature/Export/MatterTypesTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Export/MatterTypesTest.php
<?php

use App\Models\Book;
use App\Models\Chapter;

it('stores per-book publish fields', function () {
    $book = Book::factory()->create([
        'copyright_text' => '© 2026 Test Author',
        'dedication_text' => 'For my family',
        'epigraph_text' => '"All we have to decide..."',
        'epigraph_attribution' => '— J.R.R. Tolkien',
        'acknowledgment_text' => 'Thanks to everyone',
        'about_author_text' => 'Jane writes thrillers',
        'also_by_text' => 'Book One\nBook Two',
        'publisher_name' => 'Self Published',
        'isbn' => '978-3-16-148410-0',
    ]);

    expect($book->copyright_text)->toBe('© 2026 Test Author');
    expect($book->dedication_text)->toBe('For my family');
    expect($book->epigraph_text)->toBe('"All we have to decide..."');
    expect($book->epigraph_attribution)->toBe('— J.R.R. Tolkien');
    expect($book->acknowledgment_text)->toBe('Thanks to everyone');
    expect($book->about_author_text)->toBe('Jane writes thrillers');
    expect($book->also_by_text)->toBe("Book One\nBook Two");
    expect($book->publisher_name)->toBe('Self Published');
    expect($book->isbn)->toBe('978-3-16-148410-0');
});

it('stores cover image path on book', function () {
    $book = Book::factory()->create([
        'cover_image_path' => 'covers/book-1.jpg',
    ]);

    expect($book->cover_image_path)->toBe('covers/book-1.jpg');
});

it('marks a chapter as epilogue', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->create([
        'book_id' => $book->id,
        'is_epilogue' => true,
    ]);

    expect($chapter->is_epilogue)->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=MatterTypesTest`
Expected: FAIL — columns don't exist

- [ ] **Step 3: Create migration**

Run: `php artisan make:migration add_publish_fields_to_books_table --no-interaction`

```php
// database/migrations/XXXX_add_publish_fields_to_books_table.php
public function up(): void
{
    Schema::table('books', function (Blueprint $table) {
        $table->text('copyright_text')->nullable()->after('secondary_genres');
        $table->text('dedication_text')->nullable()->after('copyright_text');
        $table->text('epigraph_text')->nullable()->after('dedication_text');
        $table->string('epigraph_attribution')->nullable()->after('epigraph_text');
        $table->text('acknowledgment_text')->nullable()->after('epigraph_attribution');
        $table->text('about_author_text')->nullable()->after('acknowledgment_text');
        $table->text('also_by_text')->nullable()->after('about_author_text');
        $table->string('publisher_name')->nullable()->after('also_by_text');
        $table->string('isbn')->nullable()->after('publisher_name');
        $table->string('cover_image_path')->nullable()->after('isbn');
    });

    Schema::table('chapters', function (Blueprint $table) {
        $table->boolean('is_epilogue')->default(false)->after('status');
    });
}

public function down(): void
{
    Schema::table('books', function (Blueprint $table) {
        $table->dropColumn([
            'copyright_text', 'dedication_text', 'epigraph_text',
            'epigraph_attribution', 'acknowledgment_text', 'about_author_text',
            'also_by_text', 'publisher_name', 'isbn', 'cover_image_path',
        ]);
    });

    Schema::table('chapters', function (Blueprint $table) {
        $table->dropColumn('is_epilogue');
    });
}
```

- [ ] **Step 4: Run migration against both databases**

Run: `php artisan migrate --no-interaction && DB_DATABASE=database/nativephp.sqlite php artisan migrate --no-interaction`

- [ ] **Step 5: Update Book model casts**

Add to `app/Models/Book.php` casts() method:
```php
// No special casts needed — these are all string/text fields
// Just ensure they're not in $guarded (Book uses $guarded = [])
```

Add `is_epilogue` cast to `app/Models/Chapter.php`:
```php
'is_epilogue' => 'boolean',
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --compact --filter=MatterTypesTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add database/migrations/*_add_publish_fields_to_books_table.php app/Models/Book.php app/Models/Chapter.php tests/Feature/Export/MatterTypesTest.php
git commit -m "feat: add per-book publish fields and epilogue chapter flag"
```

---

### Task 2: Database Migration — Export Preferences

Store per-book template, font pairing, scene break, and drop cap preferences so users can customize and have it persist.

**Files:**
- Create: `database/migrations/XXXX_add_export_preferences_to_books_table.php`

- [ ] **Step 1: Create migration**

Run: `php artisan make:migration add_export_preferences_to_books_table --no-interaction`

```php
public function up(): void
{
    Schema::table('books', function (Blueprint $table) {
        $table->string('export_template')->nullable()->after('cover_image_path');
        $table->string('export_font_pairing')->nullable()->after('export_template');
        $table->string('export_scene_break_style')->nullable()->after('export_font_pairing');
        $table->boolean('export_drop_caps')->nullable()->after('export_scene_break_style');
    });
}

public function down(): void
{
    Schema::table('books', function (Blueprint $table) {
        $table->dropColumn([
            'export_template', 'export_font_pairing',
            'export_scene_break_style', 'export_drop_caps',
        ]);
    });
}
```

- [ ] **Step 2: Run migration against both databases**

Run: `php artisan migrate --no-interaction && DB_DATABASE=database/nativephp.sqlite php artisan migrate --no-interaction`

- [ ] **Step 3: Add cast for export_drop_caps**

In `app/Models/Chapter.php` — no, this is on Book. In `app/Models/Book.php` add to casts():
```php
'export_drop_caps' => 'boolean',
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/*_add_export_preferences_to_books_table.php app/Models/Book.php
git commit -m "feat: add per-book export preference fields"
```

---

### Task 3: New & Updated Enums

**Files:**
- Create: `app/Enums/SceneBreakStyle.php`
- Create: `app/Enums/FontPairing.php`
- Modify: `app/Enums/FrontMatterType.php`
- Modify: `app/Enums/BackMatterType.php`

- [ ] **Step 1: Create SceneBreakStyle enum**

```php
<?php

namespace App\Enums;

enum SceneBreakStyle: string
{
    case Asterisks = 'asterisks';         // * * *
    case Fleuron = 'fleuron';             // ❧
    case Flourish = 'flourish';           // ~❋~
    case Rule = 'rule';                   // thin horizontal line
    case Dots = 'dots';                   // • • •
    case Dashes = 'dashes';              // — — —
    case BlankSpace = 'blank-space';      // just vertical whitespace
    case Ornament = 'ornament';           // ✦

    public function label(): string
    {
        return match ($this) {
            self::Asterisks => '* * *',
            self::Fleuron => '❧',
            self::Flourish => '~❋~',
            self::Rule => '———',
            self::Dots => '• • •',
            self::Dashes => '— — —',
            self::BlankSpace => '(blank space)',
            self::Ornament => '✦',
        };
    }

    public function html(): string
    {
        return match ($this) {
            self::Asterisks => '<p class="scene-break scene-break--asterisks">*&nbsp;&nbsp;*&nbsp;&nbsp;*</p>',
            self::Fleuron => '<p class="scene-break scene-break--fleuron">❧</p>',
            self::Flourish => '<p class="scene-break scene-break--flourish">~❋~</p>',
            self::Rule => '<hr class="scene-break scene-break--rule" />',
            self::Dots => '<p class="scene-break scene-break--dots">•&nbsp;&nbsp;•&nbsp;&nbsp;•</p>',
            self::Dashes => '<p class="scene-break scene-break--dashes">—&nbsp;&nbsp;—&nbsp;&nbsp;—</p>',
            self::BlankSpace => '<div class="scene-break scene-break--blank">&nbsp;</div>',
            self::Ornament => '<p class="scene-break scene-break--ornament">✦</p>',
        };
    }

    public function xhtml(): string
    {
        return match ($this) {
            self::Rule => '<hr class="scene-break scene-break--rule" />',
            self::BlankSpace => '<div class="scene-break scene-break--blank">&#160;</div>',
            default => str_replace('&nbsp;', '&#160;', $this->html()),
        };
    }

    public function plainText(): string
    {
        return match ($this) {
            self::Asterisks => '* * *',
            self::Fleuron => '❧',
            self::Flourish => '~❋~',
            self::Rule => '---',
            self::Dots => '• • •',
            self::Dashes => '— — —',
            self::BlankSpace => '',
            self::Ornament => '✦',
        };
    }
}
```

- [ ] **Step 2: Create FontPairing enum**

```php
<?php

namespace App\Enums;

enum FontPairing: string
{
    case ClassicSerif = 'classic-serif';       // Crimson Pro for both
    case ModernMixed = 'modern-mixed';         // Source Sans 3 headings + Source Serif 4 body
    case ElegantSerif = 'elegant-serif';       // Cormorant Garamond headings + Crimson Pro body

    public function label(): string
    {
        return match ($this) {
            self::ClassicSerif => 'Classic Serif',
            self::ModernMixed => 'Modern Mixed',
            self::ElegantSerif => 'Elegant Serif',
        };
    }

    public function headingFont(): string
    {
        return match ($this) {
            self::ClassicSerif => 'Crimson Pro',
            self::ModernMixed => 'Source Sans 3',
            self::ElegantSerif => 'Cormorant Garamond',
        };
    }

    public function bodyFont(): string
    {
        return match ($this) {
            self::ClassicSerif => 'Crimson Pro',
            self::ModernMixed => 'Source Serif 4',
            self::ElegantSerif => 'Crimson Pro',
        };
    }

    public function headingFontFamily(): string
    {
        return match ($this) {
            self::ClassicSerif => "'Crimson Pro', Georgia, serif",
            self::ModernMixed => "'Source Sans 3', 'Helvetica Neue', sans-serif",
            self::ElegantSerif => "'Cormorant Garamond', Georgia, serif",
        };
    }

    public function bodyFontFamily(): string
    {
        return match ($this) {
            self::ClassicSerif => "'Crimson Pro', Georgia, serif",
            self::ModernMixed => "'Source Serif 4', Georgia, serif",
            self::ElegantSerif => "'Crimson Pro', Georgia, serif",
        };
    }

    /**
     * Returns array of font file basenames needed for this pairing.
     * Each entry: ['family' => string, 'file' => string, 'weight' => string, 'style' => string]
     */
    public function fontFiles(): array
    {
        return match ($this) {
            self::ClassicSerif => [
                ['family' => 'Crimson Pro', 'file' => 'CrimsonPro-Regular.ttf', 'weight' => 'normal', 'style' => 'normal'],
                ['family' => 'Crimson Pro', 'file' => 'CrimsonPro-Italic.ttf', 'weight' => 'normal', 'style' => 'italic'],
                ['family' => 'Crimson Pro', 'file' => 'CrimsonPro-Bold.ttf', 'weight' => 'bold', 'style' => 'normal'],
                ['family' => 'Crimson Pro', 'file' => 'CrimsonPro-BoldItalic.ttf', 'weight' => 'bold', 'style' => 'italic'],
            ],
            self::ModernMixed => [
                ['family' => 'Source Sans 3', 'file' => 'SourceSans3-Regular.ttf', 'weight' => 'normal', 'style' => 'normal'],
                ['family' => 'Source Sans 3', 'file' => 'SourceSans3-Bold.ttf', 'weight' => 'bold', 'style' => 'normal'],
                ['family' => 'Source Serif 4', 'file' => 'SourceSerif4-Regular.ttf', 'weight' => 'normal', 'style' => 'normal'],
                ['family' => 'Source Serif 4', 'file' => 'SourceSerif4-Italic.ttf', 'weight' => 'normal', 'style' => 'italic'],
                ['family' => 'Source Serif 4', 'file' => 'SourceSerif4-Bold.ttf', 'weight' => 'bold', 'style' => 'normal'],
                ['family' => 'Source Serif 4', 'file' => 'SourceSerif4-BoldItalic.ttf', 'weight' => 'bold', 'style' => 'italic'],
            ],
            self::ElegantSerif => [
                ['family' => 'Cormorant Garamond', 'file' => 'CormorantGaramond-Regular.ttf', 'weight' => 'normal', 'style' => 'normal'],
                ['family' => 'Cormorant Garamond', 'file' => 'CormorantGaramond-Italic.ttf', 'weight' => 'normal', 'style' => 'italic'],
                ['family' => 'Cormorant Garamond', 'file' => 'CormorantGaramond-Bold.ttf', 'weight' => 'bold', 'style' => 'normal'],
                ['family' => 'Cormorant Garamond', 'file' => 'CormorantGaramond-BoldItalic.ttf', 'weight' => 'bold', 'style' => 'italic'],
                ['family' => 'Crimson Pro', 'file' => 'CrimsonPro-Regular.ttf', 'weight' => 'normal', 'style' => 'normal'],
                ['family' => 'Crimson Pro', 'file' => 'CrimsonPro-Italic.ttf', 'weight' => 'normal', 'style' => 'italic'],
                ['family' => 'Crimson Pro', 'file' => 'CrimsonPro-Bold.ttf', 'weight' => 'bold', 'style' => 'normal'],
                ['family' => 'Crimson Pro', 'file' => 'CrimsonPro-BoldItalic.ttf', 'weight' => 'bold', 'style' => 'italic'],
            ],
        };
    }
}
```

- [ ] **Step 3: Update FrontMatterType enum**

```php
<?php

namespace App\Enums;

enum FrontMatterType: string
{
    case TitlePage = 'title-page';
    case Copyright = 'copyright';
    case Dedication = 'dedication';
    case Epigraph = 'epigraph';
    case Toc = 'toc';
}
```

- [ ] **Step 4: Update BackMatterType enum**

```php
<?php

namespace App\Enums;

enum BackMatterType: string
{
    case Epilogue = 'epilogue';
    case Acknowledgments = 'acknowledgments';
    case AboutAuthor = 'about-author';
    case AlsoBy = 'also-by';
}
```

- [ ] **Step 5: Update ExportBookRequest validation to accept new enum values**

In `app/Http/Requests/ExportBookRequest.php`, update the `template` rule:
```php
'template' => ['nullable', 'string', 'in:classic,modern,romance'],
```

Add new rules:
```php
'font_pairing' => ['nullable', 'string', Rule::enum(FontPairing::class)],
'scene_break_style' => ['nullable', 'string', Rule::enum(SceneBreakStyle::class)],
'drop_caps' => ['nullable', 'boolean'],
'include_cover' => ['nullable', 'boolean'],
```

- [ ] **Step 6: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 7: Commit**

```bash
git add app/Enums/ app/Http/Requests/ExportBookRequest.php
git commit -m "feat: add enums for font pairings, scene break styles, and new matter types"
```

---

## Phase 2: Template & Font System

### Task 4: Expand Font Service for Multiple Pairings

**Files:**
- Modify: `app/Services/Export/FontService.php`
- Test: `tests/Feature/Export/TemplateSystemTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Export/TemplateSystemTest.php
<?php

use App\Enums\FontPairing;
use App\Services\Export\FontService;

it('returns font data for classic serif pairing', function () {
    $service = new FontService();
    $data = $service->mPdfFontDataForPairing(FontPairing::ClassicSerif);

    expect($data)->toHaveKey('crimsonpro');
});

it('returns font data for modern mixed pairing', function () {
    $service = new FontService();
    $data = $service->mPdfFontDataForPairing(FontPairing::ModernMixed);

    expect($data)->toHaveKey('sourcesans3');
    expect($data)->toHaveKey('sourceserif4');
});

it('returns font data for elegant serif pairing', function () {
    $service = new FontService();
    $data = $service->mPdfFontDataForPairing(FontPairing::ElegantSerif);

    expect($data)->toHaveKey('cormorantgaramond');
    expect($data)->toHaveKey('crimsonpro');
});

it('generates epub font face css for a pairing', function () {
    $service = new FontService();
    $css = $service->epubFontFaceCssForPairing(FontPairing::ClassicSerif);

    expect($css)->toContain('@font-face');
    expect($css)->toContain('Crimson Pro');
});

it('returns epub font files for a pairing', function () {
    $service = new FontService();
    $files = $service->epubFontFilesForPairing(FontPairing::ClassicSerif);

    expect($files)->toBeArray();
    expect(count($files))->toBeGreaterThan(0);
    expect($files[0])->toHaveKeys(['path', 'filename']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=TemplateSystemTest`
Expected: FAIL — methods don't exist

- [ ] **Step 3: Download font files**

Download from Google Fonts and place in `resources/fonts/`:
- Crimson Pro: Regular, Italic, Bold, BoldItalic
- Source Sans 3: Regular, Bold
- Source Serif 4: Regular, Italic, Bold, BoldItalic
- Cormorant Garamond: Regular, Italic, Bold, BoldItalic

Run:
```bash
cd /Users/david/Workspace/manuscript/resources/fonts

# Crimson Pro
curl -L -o CrimsonPro-Regular.ttf "https://github.com/nicholasgross/Crimson-Pro/raw/master/fonts/ttf/CrimsonPro-Regular.ttf"
curl -L -o CrimsonPro-Italic.ttf "https://github.com/nicholasgross/Crimson-Pro/raw/master/fonts/ttf/CrimsonPro-Italic.ttf"
curl -L -o CrimsonPro-Bold.ttf "https://github.com/nicholasgross/Crimson-Pro/raw/master/fonts/ttf/CrimsonPro-Bold.ttf"
curl -L -o CrimsonPro-BoldItalic.ttf "https://github.com/nicholasgross/Crimson-Pro/raw/master/fonts/ttf/CrimsonPro-BoldItalic.ttf"

# Source Sans 3
curl -L -o SourceSans3-Regular.ttf "https://github.com/adobe-fonts/source-sans/raw/release/TTF/SourceSans3-Regular.ttf"
curl -L -o SourceSans3-Bold.ttf "https://github.com/adobe-fonts/source-sans/raw/release/TTF/SourceSans3-Bold.ttf"

# Source Serif 4
curl -L -o SourceSerif4-Regular.ttf "https://github.com/adobe-fonts/source-serif/raw/release/TTF/SourceSerif4-Regular.ttf"
curl -L -o SourceSerif4-Italic.ttf "https://github.com/adobe-fonts/source-serif/raw/release/TTF/SourceSerif4-Italic.ttf"
curl -L -o SourceSerif4-Bold.ttf "https://github.com/adobe-fonts/source-serif/raw/release/TTF/SourceSerif4-Bold.ttf"
curl -L -o SourceSerif4-BoldItalic.ttf "https://github.com/adobe-fonts/source-serif/raw/release/TTF/SourceSerif4-BoldItalic.ttf"

# Cormorant Garamond
curl -L -o CormorantGaramond-Regular.ttf "https://github.com/CatharsisFonts/Cormorant/raw/master/fonts/ttf/CormorantGaramond-Regular.ttf"
curl -L -o CormorantGaramond-Italic.ttf "https://github.com/CatharsisFonts/Cormorant/raw/master/fonts/ttf/CormorantGaramond-Italic.ttf"
curl -L -o CormorantGaramond-Bold.ttf "https://github.com/CatharsisFonts/Cormorant/raw/master/fonts/ttf/CormorantGaramond-Bold.ttf"
curl -L -o CormorantGaramond-BoldItalic.ttf "https://github.com/CatharsisFonts/Cormorant/raw/master/fonts/ttf/CormorantGaramond-BoldItalic.ttf"
```

Note: If these exact URLs don't work, download manually from fonts.google.com. The key requirement is having the TTF files with the exact filenames listed above.

- [ ] **Step 4: Expand FontService with pairing-aware methods**

Add these methods to `app/Services/Export/FontService.php` while keeping existing methods for backwards compat:

```php
use App\Enums\FontPairing;

public function fontPathForFile(string $filename): string
{
    return resource_path("fonts/{$filename}");
}

public function fontsAvailableForPairing(FontPairing $pairing): bool
{
    foreach ($pairing->fontFiles() as $font) {
        if (! file_exists($this->fontPathForFile($font['file']))) {
            return false;
        }
    }

    return true;
}

public function mPdfFontDataForPairing(FontPairing $pairing): array
{
    $data = [];
    $grouped = [];

    foreach ($pairing->fontFiles() as $font) {
        $key = strtolower(str_replace(' ', '', $font['family']));
        $grouped[$key][$font['weight']][$font['style']] = $font['file'];
    }

    foreach ($grouped as $key => $weights) {
        $entry = [];
        $entry['R'] = $weights['normal']['normal'] ?? '';
        if (isset($weights['normal']['italic'])) {
            $entry['I'] = $weights['normal']['italic'];
        }
        if (isset($weights['bold']['normal'])) {
            $entry['B'] = $weights['bold']['normal'];
        }
        if (isset($weights['bold']['italic'])) {
            $entry['BI'] = $weights['bold']['italic'];
        }
        $data[$key] = $entry;
    }

    return $data;
}

public function epubFontFilesForPairing(FontPairing $pairing): array
{
    $files = [];

    foreach ($pairing->fontFiles() as $font) {
        $path = $this->fontPathForFile($font['file']);
        if (file_exists($path)) {
            $files[] = [
                'path' => $path,
                'filename' => $font['file'],
            ];
        }
    }

    return $files;
}

public function epubFontFaceCssForPairing(FontPairing $pairing): string
{
    $css = '';

    foreach ($pairing->fontFiles() as $font) {
        $css .= "@font-face {\n";
        $css .= "    font-family: '{$font['family']}';\n";
        $css .= "    src: url('Fonts/{$font['file']}');\n";
        $css .= "    font-weight: {$font['weight']};\n";
        $css .= "    font-style: {$font['style']};\n";
        $css .= "}\n\n";
    }

    return $css;
}

public function mPdfFontDirectories(): array
{
    return [resource_path('fonts/')];
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=TemplateSystemTest`
Expected: PASS

- [ ] **Step 6: Run Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Services/Export/FontService.php resources/fonts/ tests/Feature/Export/TemplateSystemTest.php
git commit -m "feat: expand font service with multi-pairing support"
```

---

### Task 5: Update ExportTemplate Contract & ClassicTemplate

**Files:**
- Modify: `app/Contracts/ExportTemplate.php`
- Modify: `app/Services/Export/Templates/ClassicTemplate.php`

- [ ] **Step 1: Update the ExportTemplate interface**

**IMPORTANT:** The existing interface defines `slug()` and `name()` as `public static function`. These must be changed to instance methods (`public function`) since templates are always instantiated before use (see `ExportService::resolveTemplate()`). Update `ClassicTemplate` accordingly — change its `public static function slug()` and `public static function name()` to `public function slug()` and `public function name()`.

Replace the full interface at `app/Contracts/ExportTemplate.php`:

```php
<?php

namespace App\Contracts;

use App\Enums\FontPairing;
use App\Enums\SceneBreakStyle;

interface ExportTemplate
{
    public function slug(): string;

    public function name(): string;

    /**
     * @return array<string, mixed>
     */
    public function designTokens(): array;

    public function defaultFontPairing(): FontPairing;

    public function defaultSceneBreakStyle(): SceneBreakStyle;

    public function defaultDropCaps(): bool;

    public function pdfCss(int $fontSize, ?FontPairing $fontPairing = null): string;

    public function ebookPreviewCss(int $fontSize, ?FontPairing $fontPairing = null): string;

    public function epubCss(string $fontFaceCss, ?FontPairing $fontPairing = null): string;

    public function sceneBreakCss(): string;

    public function dropCapCss(): string;
}
```

Then update `ClassicTemplate` to change `public static function slug()` → `public function slug()` and `public static function name()` → `public function name()`.

- [ ] **Step 2: Update ClassicTemplate to implement new methods**

Add to `app/Services/Export/Templates/ClassicTemplate.php`:

```php
use App\Enums\FontPairing;
use App\Enums\SceneBreakStyle;

public function defaultFontPairing(): FontPairing
{
    return FontPairing::ClassicSerif;
}

public function defaultSceneBreakStyle(): SceneBreakStyle
{
    return SceneBreakStyle::Asterisks;
}

public function defaultDropCaps(): bool
{
    return true;
}

public function sceneBreakCss(): string
{
    return <<<'CSS'
    .scene-break {
        text-align: center;
        margin: 1.5em 0;
        font-size: 1em;
        color: #999999;
        page-break-before: avoid;
        page-break-after: avoid;
    }
    .scene-break--rule {
        border: none;
        border-top: 1px solid #cccccc;
        width: 30%;
        margin: 1.5em auto;
    }
    .scene-break--blank {
        height: 2em;
    }
    CSS;
}

public function dropCapCss(): string
{
    return <<<'CSS'
    .drop-cap {
        float: left;
        font-size: 3.2em;
        line-height: 0.8;
        padding-right: 0.08em;
        margin-top: 0.05em;
        font-weight: bold;
        color: #1a1a1a;
    }
    CSS;
}
```

Update existing `pdfCss()`, `ebookPreviewCss()`, and `epubCss()` methods to accept a `FontPairing` parameter and use its font families. The heading and body font families should come from the pairing:

```php
public function pdfCss(int $fontSize, ?FontPairing $fontPairing = null): string
{
    $pairing = $fontPairing ?? $this->defaultFontPairing();
    $headingFamily = $pairing->headingFontFamily();
    $bodyFamily = $pairing->bodyFontFamily();
    // Use $headingFamily for h1 and $bodyFamily for body in the CSS
    // ...existing CSS logic with font families replaced...
}
```

**Note:** The exact CSS string replacement is long. The key change is replacing hardcoded `'Crimson Pro', Georgia, serif` with `$headingFamily` for headings and `$bodyFamily` for body text. Follow the same pattern for `epubCss()` and `ebookPreviewCss()`.

- [ ] **Step 3: Run Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Contracts/ExportTemplate.php app/Services/Export/Templates/ClassicTemplate.php
git commit -m "feat: expand template contract with font pairing, scene break, and drop cap support"
```

---

### Task 6: Create Modern & Romance Templates

**Files:**
- Create: `app/Services/Export/Templates/ModernTemplate.php`
- Create: `app/Services/Export/Templates/RomanceTemplate.php`
- Modify: `app/Services/Export/ExportService.php` (resolveTemplate)

- [ ] **Step 1: Create ModernTemplate**

```php
<?php

namespace App\Services\Export\Templates;

use App\Contracts\ExportTemplate;
use App\Enums\FontPairing;
use App\Enums\SceneBreakStyle;

class ModernTemplate implements ExportTemplate
{
    public function slug(): string
    {
        return 'modern';
    }

    public function name(): string
    {
        return 'Modern';
    }

    public function defaultFontPairing(): FontPairing
    {
        return FontPairing::ModernMixed;
    }

    public function defaultSceneBreakStyle(): SceneBreakStyle
    {
        return SceneBreakStyle::Rule;
    }

    public function defaultDropCaps(): bool
    {
        return false;
    }

    public function designTokens(): array
    {
        return [
            'bodyColor' => '#333333',
            'headingColor' => '#111111',
            'accentColor' => '#888888',
            'pdfLineHeight' => 1.4,
            'epubLineHeight' => 1.6,
            'chapterLabelSizeEm' => 0.65,
            'titleSizeEm' => 1.8,
            'titleWeight' => 'bold',
            'runningHeaderStyle' => 'normal',
            'runningHeaderColor' => '#aaaaaa',
            'runningHeaderSizePt' => 7,
            'pageNumberColor' => '#aaaaaa',
            'pageNumberSizePt' => 8,
            'pageNumberPosition' => 'alternating',
        ];
    }

    // pdfCss(), ebookPreviewCss(), epubCss(), sceneBreakCss(), dropCapCss()
    // Follow same pattern as ClassicTemplate but with Modern design tokens
    // Key differences:
    // - Sans-serif headings (Source Sans 3), serif body (Source Serif 4)
    // - Bold chapter titles (vs normal weight in Classic)
    // - Cleaner, more minimal scene break CSS
    // - Drop cap CSS still defined but defaultDropCaps() returns false
}
```

- [ ] **Step 2: Create RomanceTemplate**

```php
<?php

namespace App\Services\Export\Templates;

use App\Contracts\ExportTemplate;
use App\Enums\FontPairing;
use App\Enums\SceneBreakStyle;

class RomanceTemplate implements ExportTemplate
{
    public function slug(): string
    {
        return 'romance';
    }

    public function name(): string
    {
        return 'Romance';
    }

    public function defaultFontPairing(): FontPairing
    {
        return FontPairing::ElegantSerif;
    }

    public function defaultSceneBreakStyle(): SceneBreakStyle
    {
        return SceneBreakStyle::Flourish;
    }

    public function defaultDropCaps(): bool
    {
        return true;
    }

    public function designTokens(): array
    {
        return [
            'bodyColor' => '#2a2a2a',
            'headingColor' => '#1a1a1a',
            'accentColor' => '#8b7355',
            'pdfLineHeight' => 1.4,
            'epubLineHeight' => 1.55,
            'chapterLabelSizeEm' => 0.65,
            'titleSizeEm' => 2.0,
            'titleWeight' => 'normal',
            'runningHeaderStyle' => 'italic',
            'runningHeaderColor' => '#8b7355',
            'runningHeaderSizePt' => 8,
            'pageNumberColor' => '#8b7355',
            'pageNumberSizePt' => 8,
            'pageNumberPosition' => 'alternating',
        ];
    }

    // pdfCss(), ebookPreviewCss(), epubCss(), sceneBreakCss(), dropCapCss()
    // Follow same pattern as ClassicTemplate but with Romance design tokens
    // Key differences:
    // - Cormorant Garamond headings (elegant, slightly decorative serif)
    // - Warm accent color (#8b7355) for scene breaks, headers, page numbers
    // - Larger title size (2.0em)
    // - Flourish scene breaks (~❋~) as default
    // - Decorative drop caps with accent color
}
```

- [ ] **Step 3: Update ExportService::resolveTemplate()**

In `app/Services/Export/ExportService.php`:

```php
use App\Services\Export\Templates\ModernTemplate;
use App\Services\Export\Templates\RomanceTemplate;

protected function resolveTemplate(string $slug): ExportTemplate
{
    return match ($slug) {
        'modern' => new ModernTemplate(),
        'romance' => new RomanceTemplate(),
        default => new ClassicTemplate(),
    };
}
```

- [ ] **Step 4: Run Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Services/Export/Templates/ app/Services/Export/ExportService.php
git commit -m "feat: add Modern and Romance export templates"
```

---

## Phase 3: Content Pipeline Fixes

### Task 7: Fix Inline Formatting Preservation

**Critical fix:** Currently all inline formatting (bold, italic, strikethrough, blockquotes) is stripped during export. This must be preserved.

**Files:**
- Modify: `app/Services/Export/ContentPreparer.php`
- Test: `tests/Feature/Export/InlineFormattingTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Export/InlineFormattingTest.php
<?php

use App\Services\Export\ContentPreparer;

it('preserves bold in PDF HTML', function () {
    $preparer = new ContentPreparer();
    $html = '<p>This is <strong>bold</strong> text.</p>';

    $result = $preparer->toPdfHtml($html);

    expect($result)->toContain('<strong>bold</strong>');
});

it('preserves italic in PDF HTML', function () {
    $preparer = new ContentPreparer();
    $html = '<p>This is <em>italic</em> text.</p>';

    $result = $preparer->toPdfHtml($html);

    expect($result)->toContain('<em>italic</em>');
});

it('preserves strikethrough in PDF HTML', function () {
    $preparer = new ContentPreparer();
    $html = '<p>This is <s>struck</s> text.</p>';

    $result = $preparer->toPdfHtml($html);

    expect($result)->toContain('<s>struck</s>');
});

it('preserves blockquotes in PDF HTML', function () {
    $preparer = new ContentPreparer();
    $html = '<blockquote><p>A quoted passage.</p></blockquote>';

    $result = $preparer->toPdfHtml($html);

    expect($result)->toContain('<blockquote>');
});

it('preserves bold in XHTML for EPUB', function () {
    $preparer = new ContentPreparer();
    $html = '<p>This is <strong>bold</strong> text.</p>';

    $result = $preparer->toXhtml($html);

    expect($result)->toContain('<strong>bold</strong>');
});

it('preserves italic in XHTML for EPUB', function () {
    $preparer = new ContentPreparer();
    $html = '<p>This is <em>italic</em> text.</p>';

    $result = $preparer->toXhtml($html);

    expect($result)->toContain('<em>italic</em>');
});

it('strips disallowed tags but keeps allowed ones', function () {
    $preparer = new ContentPreparer();
    $html = '<p>Text with <script>alert("xss")</script> and <strong>bold</strong>.</p>';

    $result = $preparer->toPdfHtml($html);

    expect($result)->not->toContain('<script>');
    expect($result)->toContain('<strong>bold</strong>');
});

it('preserves formatting in DOCX segments', function () {
    $preparer = new ContentPreparer();
    $html = '<p>This is <strong>bold</strong> and <em>italic</em>.</p>';

    $result = $preparer->toFormattedSegments($html);

    expect($result)->toBeArray();
    // Returns structured segments with type indicators
    $types = collect($result)->pluck('type')->unique()->toArray();
    expect($types)->toContain('paragraph-start');
    expect($types)->toContain('text');

    // Text segments carry formatting metadata
    $boldSegment = collect($result)->first(fn ($s) => ($s['text'] ?? '') === 'bold');
    expect($boldSegment['bold'])->toBeTrue();

    $italicSegment = collect($result)->first(fn ($s) => ($s['text'] ?? '') === 'italic');
    expect($italicSegment['italic'])->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=InlineFormattingTest`
Expected: FAIL

- [ ] **Step 3: Update ContentPreparer**

The key change is in `toChapterHtml()` / `toPdfHtml()` and `toXhtml()`. Instead of stripping all tags, use `strip_tags()` with an allowlist:

```php
private const ALLOWED_TAGS = '<p><br><hr><strong><em><b><i><s><del><blockquote><ul><ol><li>';

public function toChapterHtml(string $html): string
{
    // Normalize HRs to scene breaks
    $html = preg_replace('/<hr\s*\/?>/i', '<!--SCENE_BREAK-->', $html);

    // Strip disallowed tags but keep formatting
    $html = strip_tags($html, self::ALLOWED_TAGS);

    // Restore scene breaks
    $html = str_replace('<!--SCENE_BREAK-->', '<p class="scene-break">*&nbsp;&nbsp;*&nbsp;&nbsp;*</p>', $html);

    // Remove empty paragraphs
    $html = preg_replace('/<p>\s*<\/p>/', '', $html);

    return trim($html);
}
```

For XHTML (EPUB), similar approach but with self-closing tags:
```php
public function toXhtml(string $html): string
{
    $html = strip_tags($html, self::ALLOWED_TAGS);

    // Convert to XHTML self-closing
    $html = preg_replace('/<br\s*\/?>/i', '<br />', $html);
    $html = preg_replace('/<hr\s*\/?>/i', '<hr />', $html);

    // Remove empty paragraphs
    $html = preg_replace('/<p>\s*<\/p>/', '', $html);

    // Encode entities for XML
    // Note: don't double-encode content inside tags
    return trim($html);
}
```

For DOCX, add a new method that returns structured segments for PhpWord. The return format uses `type` indicators so the DocxExporter can distinguish paragraphs, scene breaks, and text runs:

```php
/**
 * Parse HTML into structured segments with formatting metadata for PhpWord.
 *
 * Returns array of segments, each with a 'type' key:
 * - ['type' => 'paragraph-start'] — start of a new paragraph
 * - ['type' => 'text', 'text' => string, 'bold' => bool, 'italic' => bool, 'strikethrough' => bool]
 * - ['type' => 'scene-break'] — scene separator
 */
public function toFormattedSegments(string $html): array
{
    $segments = [];
    $dom = new \DOMDocument();
    @$dom->loadHTML('<body>' . mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8') . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $body = $dom->getElementsByTagName('body')->item(0);
    if (! $body) {
        return $segments;
    }

    foreach ($body->childNodes as $child) {
        if ($child->nodeType !== XML_ELEMENT_NODE) {
            continue;
        }

        if ($child->nodeName === 'hr') {
            $segments[] = ['type' => 'scene-break'];
            continue;
        }

        if (in_array($child->nodeName, ['p', 'blockquote'])) {
            // Skip empty paragraphs
            if (trim($child->textContent) === '') {
                continue;
            }

            $segments[] = ['type' => 'paragraph-start'];
            $this->extractTextSegments($child, $segments, [
                'bold' => false,
                'italic' => $child->nodeName === 'blockquote',
                'strikethrough' => false,
            ]);
        }
    }

    return $segments;
}

private function extractTextSegments(\DOMNode $node, array &$segments, array $formatting): void
{
    foreach ($node->childNodes as $child) {
        if ($child->nodeType === XML_TEXT_NODE) {
            $text = $child->textContent;
            if ($text !== '') {
                $segments[] = array_merge(['type' => 'text', 'text' => $text], $formatting);
            }
        } elseif ($child->nodeType === XML_ELEMENT_NODE) {
            $childFormatting = $formatting;
            match ($child->nodeName) {
                'strong', 'b' => $childFormatting['bold'] = true,
                'em', 'i' => $childFormatting['italic'] = true,
                's', 'del' => $childFormatting['strikethrough'] = true,
                'p' => null, // nested p inside blockquote
                default => null,
            };
            $this->extractTextSegments($child, $segments, $childFormatting);
        }
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=InlineFormattingTest`
Expected: PASS

- [ ] **Step 5: Run Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Services/Export/ContentPreparer.php tests/Feature/Export/InlineFormattingTest.php
git commit -m "fix: preserve bold, italic, strikethrough, blockquotes in all export formats"
```

---

### Task 8: Implement Drop Caps

**Files:**
- Modify: `app/Services/Export/ContentPreparer.php`
- Test: `tests/Feature/Export/DropCapTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Export/DropCapTest.php
<?php

use App\Services\Export\ContentPreparer;

it('adds drop cap to first paragraph', function () {
    $preparer = new ContentPreparer();
    $html = '<p>The story begins here.</p><p>Second paragraph.</p>';

    $result = $preparer->addDropCap($html);

    expect($result)->toContain('<span class="drop-cap">T</span>');
    expect($result)->toContain('he story begins here.');
});

it('handles punctuation before first letter', function () {
    $preparer = new ContentPreparer();
    $html = '<p>"Hello," she said.</p>';

    $result = $preparer->addDropCap($html);

    // Drop cap should span the quote mark + first letter
    expect($result)->toContain('<span class="drop-cap">"H</span>');
});

it('handles single-quote punctuation', function () {
    $preparer = new ContentPreparer();
    $html = "<p>'Twas the night.</p>";

    $result = $preparer->addDropCap($html);

    expect($result)->toContain("<span class=\"drop-cap\">'T</span>");
});

it('does not add drop cap to empty paragraphs', function () {
    $preparer = new ContentPreparer();
    $html = '<p></p><p>Real content.</p>';

    $result = $preparer->addDropCap($html);

    expect($result)->toContain('<span class="drop-cap">R</span>');
});

it('only affects first paragraph', function () {
    $preparer = new ContentPreparer();
    $html = '<p>First paragraph.</p><p>Second paragraph.</p>';

    $result = $preparer->addDropCap($html);

    // Count drop-cap spans — should be exactly 1
    expect(substr_count($result, 'drop-cap'))->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=DropCapTest`
Expected: FAIL (addDropCap exists but doesn't handle punctuation properly)

- [ ] **Step 3: Update addDropCap() in ContentPreparer**

Replace the existing `addDropCap()` method with a robust regex that handles leading punctuation + first letter:

```php
public function addDropCap(string $html): string
{
    // Match first <p> with content, capturing leading punctuation + first letter
    $pattern = '/(<p[^>]*>)([\s]*)(["\'"\'\xC2\xAB\xC2\xBF\xC2\xA1\(\[]*)([\p{L}\p{N}])/u';

    return preg_replace_callback(
        $pattern,
        function ($matches) {
            $openTag = $matches[1];
            $whitespace = $matches[2];
            $punctuation = $matches[3];
            $letter = $matches[4];

            $dropCapContent = $punctuation . $letter;

            return "{$openTag}{$whitespace}<span class=\"drop-cap\">{$dropCapContent}</span>";
        },
        $html,
        1 // Only first match
    );
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=DropCapTest`
Expected: PASS

- [ ] **Step 5: Run Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Services/Export/ContentPreparer.php tests/Feature/Export/DropCapTest.php
git commit -m "feat: implement drop caps with smart punctuation handling"
```

---

### Task 9: Scene Break Style Rendering

**Files:**
- Modify: `app/Services/Export/ContentPreparer.php`
- Test: `tests/Feature/Export/SceneBreakStyleTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Export/SceneBreakStyleTest.php
<?php

use App\Enums\SceneBreakStyle;
use App\Services\Export\ContentPreparer;

it('renders asterisks scene break in HTML', function () {
    $preparer = new ContentPreparer();
    $html = '<p>Before.</p><hr><p>After.</p>';

    $result = $preparer->toChapterHtml($html, SceneBreakStyle::Asterisks);

    expect($result)->toContain('scene-break--asterisks');
    expect($result)->toContain('*');
});

it('renders fleuron scene break in HTML', function () {
    $preparer = new ContentPreparer();
    $html = '<p>Before.</p><hr><p>After.</p>';

    $result = $preparer->toChapterHtml($html, SceneBreakStyle::Fleuron);

    expect($result)->toContain('scene-break--fleuron');
    expect($result)->toContain('❧');
});

it('renders rule scene break in HTML', function () {
    $preparer = new ContentPreparer();
    $html = '<p>Before.</p><hr><p>After.</p>';

    $result = $preparer->toChapterHtml($html, SceneBreakStyle::Rule);

    expect($result)->toContain('scene-break--rule');
});

it('renders blank space scene break in HTML', function () {
    $preparer = new ContentPreparer();
    $html = '<p>Before.</p><hr><p>After.</p>';

    $result = $preparer->toChapterHtml($html, SceneBreakStyle::BlankSpace);

    expect($result)->toContain('scene-break--blank');
});

it('renders scene break in XHTML for EPUB', function () {
    $preparer = new ContentPreparer();
    $html = '<p>Before.</p><hr><p>After.</p>';

    $result = $preparer->toXhtml($html, SceneBreakStyle::Flourish);

    expect($result)->toContain('scene-break--flourish');
});

it('renders scene break in plain text', function () {
    $preparer = new ContentPreparer();
    $html = '<p>Before.</p><hr><p>After.</p>';

    $result = $preparer->toPlainText($html, SceneBreakStyle::Fleuron);

    expect($result)->toContain('❧');
});

it('defaults to asterisks when no style specified', function () {
    $preparer = new ContentPreparer();
    $html = '<p>Before.</p><hr><p>After.</p>';

    $result = $preparer->toChapterHtml($html);

    expect($result)->toContain('*');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SceneBreakStyleTest`
Expected: FAIL — methods don't accept SceneBreakStyle parameter

- [ ] **Step 3: Update ContentPreparer methods to accept SceneBreakStyle**

Update method signatures:

```php
public function toChapterHtml(string $html, ?SceneBreakStyle $sceneBreak = null): string
{
    $sceneBreak = $sceneBreak ?? SceneBreakStyle::Asterisks;

    // Normalize HRs to scene break placeholder
    $html = preg_replace('/<hr\s*\/?>/i', '<!--SCENE_BREAK-->', $html);

    // Strip disallowed tags
    $html = strip_tags($html, self::ALLOWED_TAGS);

    // Replace placeholder with styled scene break
    $html = str_replace('<!--SCENE_BREAK-->', $sceneBreak->html(), $html);

    // Remove empty paragraphs
    $html = preg_replace('/<p>\s*<\/p>/', '', $html);

    return trim($html);
}

public function toPdfHtml(string $html, ?SceneBreakStyle $sceneBreak = null): string
{
    return $this->toChapterHtml($html, $sceneBreak);
}

public function toXhtml(string $html, ?SceneBreakStyle $sceneBreak = null): string
{
    $sceneBreak = $sceneBreak ?? SceneBreakStyle::Asterisks;

    $html = preg_replace('/<hr\s*\/?>/i', '<!--SCENE_BREAK-->', $html);
    $html = strip_tags($html, self::ALLOWED_TAGS);
    $html = str_replace('<!--SCENE_BREAK-->', $sceneBreak->xhtml(), $html);

    // XHTML self-closing
    $html = preg_replace('/<br\s*\/?>/i', '<br />', $html);
    $html = preg_replace('/<p>\s*<\/p>/', '', $html);

    return trim($html);
}

public function toPlainText(string $html, ?SceneBreakStyle $sceneBreak = null): string
{
    $sceneBreak = $sceneBreak ?? SceneBreakStyle::Asterisks;

    $html = preg_replace('/<hr\s*\/?>/i', "\n\n" . $sceneBreak->plainText() . "\n\n", $html);
    $html = strip_tags($html);
    $html = preg_replace('/\n{3,}/', "\n\n", $html);

    return trim($html);
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=SceneBreakStyleTest`
Expected: PASS

- [ ] **Step 5: Run Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Services/Export/ContentPreparer.php tests/Feature/Export/SceneBreakStyleTest.php
git commit -m "feat: scene break style rendering with 8 ornament options"
```

---

## Phase 4: Publish Page

### Task 10: Publish Page Backend

**Files:**
- Create: `app/Http/Controllers/PublishController.php`
- Create: `app/Http/Requests/UpdatePublishSettingsRequest.php`
- Create: `app/Http/Requests/UploadCoverImageRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/PublishControllerTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/PublishControllerTest.php
<?php

use App\Models\Book;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->book = Book::factory()->create();
    $this->actingAs($this->user);
});

it('renders the publish page', function () {
    $response = $this->get(route('books.publish', $this->book));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('books/publish')
        ->has('book')
    );
});

it('updates publish settings', function () {
    $response = $this->put(route('books.publish.update', $this->book), [
        'copyright_text' => '© 2026 Test Author. All rights reserved.',
        'dedication_text' => 'For my family',
        'epigraph_text' => 'A great quote',
        'epigraph_attribution' => '— Famous Author',
        'acknowledgment_text' => 'Thanks to everyone',
        'about_author_text' => 'I write books.',
        'also_by_text' => "Book One\nBook Two",
        'publisher_name' => 'Self Published',
        'isbn' => '978-3-16-148410-0',
    ]);

    $response->assertRedirect();

    $this->book->refresh();
    expect($this->book->copyright_text)->toBe('© 2026 Test Author. All rights reserved.');
    expect($this->book->dedication_text)->toBe('For my family');
    expect($this->book->publisher_name)->toBe('Self Published');
});

it('uploads a cover image', function () {
    Storage::fake('local');

    $file = UploadedFile::fake()->image('cover.jpg', 2560, 1600);

    $response = $this->post(route('books.publish.cover', $this->book), [
        'cover_image' => $file,
    ]);

    $response->assertRedirect();

    $this->book->refresh();
    expect($this->book->cover_image_path)->not->toBeNull();
    Storage::disk('local')->assertExists($this->book->cover_image_path);
});

it('deletes a cover image', function () {
    $this->book->update(['cover_image_path' => 'covers/old.jpg']);

    $response = $this->delete(route('books.publish.cover.delete', $this->book));

    $response->assertRedirect();

    $this->book->refresh();
    expect($this->book->cover_image_path)->toBeNull();
});

it('marks a chapter as epilogue', function () {
    $chapter = \App\Models\Chapter::factory()->create(['book_id' => $this->book->id]);

    $response = $this->put(route('books.publish.epilogue', $this->book), [
        'chapter_id' => $chapter->id,
    ]);

    $response->assertRedirect();

    $chapter->refresh();
    expect($chapter->is_epilogue)->toBeTrue();
});

it('unmarks epilogue when null chapter_id sent', function () {
    $chapter = \App\Models\Chapter::factory()->create([
        'book_id' => $this->book->id,
        'is_epilogue' => true,
    ]);

    $response = $this->put(route('books.publish.epilogue', $this->book), [
        'chapter_id' => null,
    ]);

    $response->assertRedirect();

    $chapter->refresh();
    expect($chapter->is_epilogue)->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=PublishControllerTest`
Expected: FAIL — routes/controller don't exist

- [ ] **Step 3: Create the controller**

Run: `php artisan make:controller PublishController --no-interaction`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePublishSettingsRequest;
use App\Http\Requests\UploadCoverImageRequest;
use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PublishController extends Controller
{
    public function show(Book $book): Response
    {
        $book->load(['chapters' => fn ($q) => $q->orderBy('reader_order'), 'storylines']);

        return Inertia::render('books/publish', [
            'book' => $book->only(
                'id', 'title', 'author', 'language',
                'copyright_text', 'dedication_text', 'epigraph_text', 'epigraph_attribution',
                'acknowledgment_text', 'about_author_text', 'also_by_text',
                'publisher_name', 'isbn', 'cover_image_path',
            ),
            'chapters' => $book->chapters->map(fn ($ch) => [
                'id' => $ch->id,
                'title' => $ch->title,
                'is_epilogue' => $ch->is_epilogue,
            ]),
        ]);
    }

    public function update(UpdatePublishSettingsRequest $request, Book $book): RedirectResponse
    {
        $book->update($request->validated());

        return back();
    }

    public function uploadCover(UploadCoverImageRequest $request, Book $book): RedirectResponse
    {
        if ($book->cover_image_path) {
            Storage::disk('local')->delete($book->cover_image_path);
        }

        $path = $request->file('cover_image')->store("covers/{$book->id}", 'local');

        $book->update(['cover_image_path' => $path]);

        return back();
    }

    public function deleteCover(Book $book): RedirectResponse
    {
        if ($book->cover_image_path) {
            Storage::disk('local')->delete($book->cover_image_path);
            $book->update(['cover_image_path' => null]);
        }

        return back();
    }

    public function updateEpilogue(Book $book): RedirectResponse
    {
        $chapterId = request('chapter_id');

        // Clear all epilogue flags for this book
        $book->chapters()->update(['is_epilogue' => false]);

        // Set the new epilogue chapter
        if ($chapterId) {
            $book->chapters()->where('id', $chapterId)->update(['is_epilogue' => true]);
        }

        return back();
    }
}
```

- [ ] **Step 4: Create form requests**

Run: `php artisan make:request UpdatePublishSettingsRequest --no-interaction`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePublishSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'copyright_text' => ['nullable', 'string', 'max:2000'],
            'dedication_text' => ['nullable', 'string', 'max:2000'],
            'epigraph_text' => ['nullable', 'string', 'max:2000'],
            'epigraph_attribution' => ['nullable', 'string', 'max:255'],
            'acknowledgment_text' => ['nullable', 'string', 'max:5000'],
            'about_author_text' => ['nullable', 'string', 'max:5000'],
            'also_by_text' => ['nullable', 'string', 'max:5000'],
            'publisher_name' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:20'],
        ];
    }
}
```

Run: `php artisan make:request UploadCoverImageRequest --no-interaction`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadCoverImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cover_image' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
        ];
    }
}
```

- [ ] **Step 5: Add routes**

In `routes/web.php`, add **after line 190** (after the existing export routes, outside any middleware group). The publish page is for content authoring, not an AI/Pro-gated feature, so it sits at the same level as the export routes:

```php
// Publish page — book metadata and matter content
Route::get('/books/{book}/publish', [PublishController::class, 'show'])->name('books.publish');
Route::put('/books/{book}/publish', [PublishController::class, 'update'])->name('books.publish.update');
Route::post('/books/{book}/publish/cover', [PublishController::class, 'uploadCover'])->name('books.publish.cover');
Route::delete('/books/{book}/publish/cover', [PublishController::class, 'deleteCover'])->name('books.publish.cover.delete');
Route::put('/books/{book}/publish/epilogue', [PublishController::class, 'updateEpilogue'])->name('books.publish.epilogue');
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --compact --filter=PublishControllerTest`
Expected: PASS

- [ ] **Step 7: Run Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Http/Controllers/PublishController.php app/Http/Requests/UpdatePublishSettingsRequest.php app/Http/Requests/UploadCoverImageRequest.php routes/web.php tests/Feature/PublishControllerTest.php
git commit -m "feat: add Publish page backend with cover upload and epilogue support"
```

---

### Task 11: Publish Page Frontend

**Files:**
- Create: `resources/js/pages/books/publish.tsx`
- Create: `resources/js/components/publish/PublishMetadata.tsx`
- Create: `resources/js/components/publish/PublishFrontMatter.tsx`
- Create: `resources/js/components/publish/PublishBackMatter.tsx`
- Create: `resources/js/components/publish/CoverImageUpload.tsx`
- Create: `resources/js/i18n/en/publish.json`
- Modify: `resources/js/components/editor/Sidebar.tsx`
- Modify: `resources/js/types/models.ts`

**Note:** Before implementing, activate @design-system and @inertia-react-development skills. Check `docs/design-system.md` for color tokens, spacing, and component patterns. Match existing page layouts (e.g., `resources/js/pages/books/export.tsx` for structure).

- [ ] **Step 1: Add TypeScript types**

In `resources/js/types/models.ts`, update the `Book` type to include new publish fields:

```typescript
// Add to Book type
copyright_text?: string | null;
dedication_text?: string | null;
epigraph_text?: string | null;
epigraph_attribution?: string | null;
acknowledgment_text?: string | null;
about_author_text?: string | null;
also_by_text?: string | null;
publisher_name?: string | null;
isbn?: string | null;
cover_image_path?: string | null;
```

Add to `Chapter` type:
```typescript
is_epilogue?: boolean;
```

- [ ] **Step 2: Create i18n strings**

Create `resources/js/i18n/en/publish.json` using **flat dot-notation keys** (matching the existing pattern in `export.json`):
```json
{
    "title": "Publish",
    "subtitle": "Prepare your book for publishing",
    "pageTitle": "Publish — {{title}}",
    "metadata.title": "Book Metadata",
    "metadata.publisherName": "Publisher Name",
    "metadata.publisherNamePlaceholder": "e.g., Self Published",
    "metadata.isbn": "ISBN",
    "metadata.isbnPlaceholder": "e.g., 978-3-16-148410-0",
    "metadata.isbnHint": "Optional. Leave blank if you don't have one yet.",
    "cover.title": "Cover Image",
    "cover.description": "Upload your book cover. Recommended: 2560 × 1600px, JPG or PNG.",
    "cover.upload": "Upload Cover",
    "cover.replace": "Replace Cover",
    "cover.remove": "Remove",
    "cover.noCover": "No cover image uploaded",
    "frontMatter.title": "Front Matter",
    "frontMatter.copyright": "Copyright",
    "frontMatter.copyrightPlaceholder": "© {{year}} {{author}}. All rights reserved.",
    "frontMatter.dedication": "Dedication",
    "frontMatter.dedicationPlaceholder": "For...",
    "frontMatter.epigraph": "Epigraph",
    "frontMatter.epigraphPlaceholder": "Opening quote...",
    "frontMatter.epigraphAttribution": "Attribution",
    "frontMatter.epigraphAttributionPlaceholder": "— Author Name",
    "backMatter.title": "Back Matter",
    "backMatter.acknowledgments": "Acknowledgments",
    "backMatter.acknowledgmentsPlaceholder": "I would like to thank...",
    "backMatter.aboutAuthor": "About the Author",
    "backMatter.aboutAuthorPlaceholder": "Write a short bio...",
    "backMatter.alsoBy": "Also By",
    "backMatter.alsoByPlaceholder": "List your other books, one per line...",
    "backMatter.epilogue": "Epilogue Chapter",
    "backMatter.epilogueHint": "Select a chapter to be treated as an epilogue in your export. It will appear in the back matter instead of the main body.",
    "backMatter.noEpilogue": "None",
    "saved": "Changes saved"
}
```

- [ ] **Step 3: Create the Publish page component**

Create `resources/js/pages/books/publish.tsx` following the pattern of `export.tsx`:
- Layout: `Sidebar` (left) + scrollable content area (center, max-w-2xl)
- Sections: Cover Image Upload → Metadata → Front Matter → Back Matter
- Use `useForm` from Inertia for auto-saving via `put` to the update route
- Each section uses `SectionLabel` + form fields from UI components
- Debounce form submission (500ms) on field changes for auto-save UX

- [ ] **Step 4: Create component stubs**

Create the four sub-components:

**CoverImageUpload.tsx** — Image upload zone with preview. Uses a file input with drag-and-drop. Shows current cover thumbnail if exists. Delete button. Posts to cover upload route via Inertia `router.post()` with FormData.

**PublishMetadata.tsx** — Publisher name (Input) and ISBN (Input) fields. Simple form fields.

**PublishFrontMatter.tsx** — Copyright (Textarea), Dedication (Textarea), Epigraph (Textarea) + Attribution (Input).

**PublishBackMatter.tsx** — Acknowledgments (Textarea), About the Author (Textarea), Also By (Textarea), Epilogue chapter selector (Select dropdown of chapters, including "None" option).

- [ ] **Step 5: Add Publish to sidebar navigation**

In `resources/js/components/editor/Sidebar.tsx`:

Add active state detection:
```typescript
const isPublish = currentUrl.includes('/publish');
```

Add NavItem between "Export" and any other items, or before "Export":
```tsx
<NavItem
    label={t('publish.title')}
    icon={BookOpen}
    href={publishMethod.url(book)}
    isActive={isPublish}
/>
```

Import the route from Wayfinder after running `php artisan wayfinder:generate`.

- [ ] **Step 6: Generate Wayfinder types**

Run: `php artisan wayfinder:generate --no-interaction`

- [ ] **Step 7: Build frontend and verify**

Run: `npm run build`

- [ ] **Step 8: Commit**

```bash
git add resources/js/pages/books/publish.tsx resources/js/components/publish/ resources/js/i18n/en/publish.json resources/js/components/editor/Sidebar.tsx resources/js/types/models.ts resources/js/actions/ resources/js/routes/
git commit -m "feat: add Publish page with cover upload, metadata, and matter editing"
```

---

## Phase 5: Exporter Upgrades

### Task 12: Update ExportOptions & ExportService

**Files:**
- Modify: `app/Services/Export/ExportOptions.php`
- Modify: `app/Services/Export/ExportService.php`

- [ ] **Step 1: Expand ExportOptions**

Add new properties to the readonly class:

```php
use App\Enums\FontPairing;
use App\Enums\SceneBreakStyle;

// Add to constructor
public readonly ?FontPairing $fontPairing = null,
public readonly ?SceneBreakStyle $sceneBreakStyle = null,
public readonly bool $dropCaps = false,
public readonly bool $includeCover = true,
public readonly string $dedicationText = '',
public readonly string $epigraphText = '',
public readonly string $epigraphAttribution = '',
public readonly string $alsoByText = '',
public readonly ?int $epilogueChapterId = null,
```

Update `fromArray()` to populate these from the request data.

- [ ] **Step 2: Update ExportService::injectMatterText()**

Change from reading AppSetting to reading Book fields:

```php
protected function injectMatterText(array &$options, Book $book): void
{
    $options['copyright_text'] = $book->copyright_text ?? "© " . date('Y') . " {$book->author}. All rights reserved.";
    $options['acknowledgment_text'] = $book->acknowledgment_text ?? '';
    $options['about_author_text'] = $book->about_author_text ?? '';
    $options['dedication_text'] = $book->dedication_text ?? '';
    $options['epigraph_text'] = $book->epigraph_text ?? '';
    $options['epigraph_attribution'] = $book->epigraph_attribution ?? '';
    $options['also_by_text'] = $book->also_by_text ?? '';
    $options['cover_image_path'] = $book->cover_image_path;
}
```

- [ ] **Step 3: Update resolveChapters to handle epilogue**

Epilogue chapters should be excluded from the main body and included in back matter:

```php
protected function resolveChapters(Book $book, array $options): Collection
{
    // Existing logic for chapter resolution...
    $chapters = // existing query

    // Separate epilogue chapters — they'll be handled as back matter
    if (in_array('epilogue', $options['back_matter'] ?? [])) {
        $chapters = $chapters->reject(fn ($ch) => $ch->is_epilogue);
    }

    return $chapters;
}
```

Add a method to get the epilogue chapter:
```php
public function resolveEpilogueChapter(Book $book): ?Chapter
{
    return $book->chapters()->where('is_epilogue', true)->first();
}
```

- [ ] **Step 4: Run Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Services/Export/ExportOptions.php app/Services/Export/ExportService.php
git commit -m "feat: expand export options with font pairing, scene breaks, drop caps, and new matter types"
```

---

### Task 13: EPUB Exporter Upgrades

**Files:**
- Modify: `app/Services/Export/Exporters/EpubExporter.php`
- Test: `tests/Feature/Export/CoverImageTest.php`

Key changes:
- Embed cover image as first item in EPUB
- Add dedication and epigraph to front matter
- Add also-by and epilogue to back matter
- Use FontPairing for font embedding instead of hardcoded Crimson Pro
- Use SceneBreakStyle for scene break rendering
- Apply drop caps to first paragraph of each chapter
- Use template-specific CSS

- [ ] **Step 1: Write failing test for cover image**

```php
// tests/Feature/Export/CoverImageTest.php
<?php

use App\Models\Book;
use App\Services\Export\ExportOptions;
use App\Services\Export\Exporters\EpubExporter;
use App\Services\Export\FontService;
use App\Services\Export\Templates\ClassicTemplate;
use Illuminate\Support\Facades\Storage;

it('includes cover image in EPUB when present', function () {
    Storage::fake('local');

    // Create a fake cover image (no binary fixture needed)
    $coverPath = 'covers/test-cover.jpg';
    Storage::disk('local')->put($coverPath, UploadedFile::fake()->image('cover.jpg', 100, 100)->getContent());

    $book = Book::factory()->create(['cover_image_path' => $coverPath]);
    $chapters = $book->chapters()->get();

    // Constructor order: ContentPreparer, FontService, ExportTemplate
    $contentPreparer = new ContentPreparer();
    $fontService = new FontService();
    $template = new ClassicTemplate();
    $exporter = new EpubExporter($contentPreparer, $fontService, $template);

    $options = ExportOptions::fromArray([
        'include_cover' => true,
        'front_matter' => ['title-page'],
        'back_matter' => [],
    ]);

    $path = $exporter->export($book, $chapters, $options);

    $zip = new ZipArchive();
    $zip->open($path);

    // Cover image should be in the EPUB
    expect($zip->locateName('OEBPS/Images/cover.jpg'))->not->toBeFalse();

    // Cover XHTML page should exist
    expect($zip->locateName('OEBPS/cover.xhtml'))->not->toBeFalse();

    $zip->close();
    unlink($path);
});
```

**Note:** Uses `UploadedFile::fake()->image()` to create test images — no binary fixture files needed. Add `use Illuminate\Http\UploadedFile;` and `use Illuminate\Support\Facades\Storage;` at the top of the test file.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CoverImageTest`
Expected: FAIL

- [ ] **Step 3: Update EpubExporter**

Add cover image support to the EPUB generation. Key changes to the `export()` method:

1. **Cover XHTML page** — add a `cover.xhtml` file with the cover image
2. **Cover image file** — copy the image into `OEBPS/Images/cover.jpg`
3. **OPF manifest** — add cover image and cover page to the manifest
4. **OPF spine** — add cover page as first item
5. **OPF metadata** — add `<meta name="cover" content="cover-image" />`

Add new front matter pages:
- `dedication.xhtml` — centered text
- `epigraph.xhtml` — italic text with attribution

Add new back matter pages:
- `also-by.xhtml` — list of titles
- `epilogue.xhtml` — full chapter content, treated as back matter

Update font embedding to use `FontPairing` via `FontService::epubFontFilesForPairing()`.

Update content processing to use `ContentPreparer` with the selected `SceneBreakStyle`.

Add drop cap processing: if `$options->dropCaps`, call `$contentPreparer->addDropCap()` on each chapter's HTML.

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact --filter=CoverImageTest`
Expected: PASS

- [ ] **Step 5: Run Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Services/Export/Exporters/EpubExporter.php tests/Feature/Export/CoverImageTest.php tests/fixtures/
git commit -m "feat: EPUB exporter with cover image, new matter types, font pairings, and scene break styles"
```

---

### Task 14: PDF Exporter Upgrades

**Files:**
- Modify: `app/Services/Export/Exporters/PdfExporter.php`

Key changes:
- Accept `FontPairing` and configure mPDF with the correct fonts
- Use template's `sceneBreakCss()` and `dropCapCss()` in the stylesheet
- Pass `SceneBreakStyle` to `ContentPreparer` methods
- Apply drop caps when enabled
- Add cover page as first PDF page (full-bleed image)
- Render new matter types (dedication, epigraph, also-by, epilogue)

- [ ] **Step 1: Update PdfExporter to use font pairing**

In `buildMpdf()`, update the font configuration:

```php
$fontPairing = $options->fontPairing ?? $this->template->defaultFontPairing();
$fontService = $this->fontService;

$fontData = $fontService->mPdfFontDataForPairing($fontPairing);
$fontDirs = $fontService->mPdfFontDirectories();

// Get the body font key for mPDF default
$bodyFontKey = strtolower(str_replace(' ', '', $fontPairing->bodyFont()));

$mpdf = new Mpdf([
    // ...existing config...
    'fontDir' => array_merge((new MpdfConfig())->fontDir, $fontDirs),
    'fontdata' => $fontData,
    'default_font' => $bodyFontKey,
]);
```

- [ ] **Step 2: Update renderHtml() for templates, scene breaks, drop caps**

Pass scene break style through to ContentPreparer:
```php
$sceneBreak = $options->sceneBreakStyle ?? $this->template->defaultSceneBreakStyle();
$dropCaps = $options->dropCaps ?? $this->template->defaultDropCaps();

foreach ($chapters as $index => $chapter) {
    $html = $contentPreparer->toPdfHtml($chapter->getContentWithSceneBreaks(), $sceneBreak);

    if ($dropCaps) {
        $html = $contentPreparer->addDropCap($html);
    }

    // ...render chapter HTML...
}
```

Add template CSS for scene breaks and drop caps:
```php
$css = $this->template->pdfCss($options->fontSize, $fontPairing);
$css .= $this->template->sceneBreakCss();
if ($dropCaps) {
    $css .= $this->template->dropCapCss();
}
```

- [ ] **Step 3: Add cover page rendering**

If cover image exists and `include_cover` is true, add it as the first page:
```php
if ($options->includeCover && $book->cover_image_path) {
    $coverPath = Storage::disk('local')->path($book->cover_image_path);
    if (file_exists($coverPath)) {
        $mpdf->AddPage();
        $mpdf->Image($coverPath, 0, 0, $trimWidth, $trimHeight, '', '', true);
    }
}
```

- [ ] **Step 4: Add new matter pages**

Render dedication, epigraph, also-by pages following the existing pattern for copyright/acknowledgments.

- [ ] **Step 5: Run existing export tests to verify no regressions**

Run: `php artisan test --compact --filter=Export`
Expected: PASS

- [ ] **Step 6: Run Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Services/Export/Exporters/PdfExporter.php
git commit -m "feat: PDF exporter with template fonts, scene break styles, drop caps, and cover page"
```

---

### Task 15: DOCX Manuscript Format Upgrade

**Files:**
- Modify: `app/Services/Export/Exporters/DocxExporter.php`
- Test: `tests/Feature/Export/DocxExporterTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Export/DocxExporterTest.php
<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Scene;
use App\Services\Export\ExportOptions;
use App\Services\Export\Exporters\DocxExporter;
use App\Services\Export\Templates\ClassicTemplate;

it('generates a valid DOCX file', function () {
    $book = Book::factory()->create(['title' => 'Test Book', 'author' => 'Test Author']);
    $chapter = Chapter::factory()->create(['book_id' => $book->id, 'title' => 'Chapter One']);
    Scene::factory()->create([
        'chapter_id' => $chapter->id,
        'content' => '<p>This is <strong>bold</strong> and <em>italic</em> text.</p>',
    ]);

    $exporter = new DocxExporter(new ContentPreparer(), new ClassicTemplate());
    $options = ExportOptions::fromArray([
        'include_chapter_titles' => true,
        'front_matter' => ['title-page', 'copyright'],
        'copyright_text' => '© 2026 Test Author',
    ]);

    $path = $exporter->export($book, $book->chapters()->with('scenes')->get(), $options);

    expect(file_exists($path))->toBeTrue();
    expect(filesize($path))->toBeGreaterThan(0);

    // Verify it's a valid ZIP (DOCX is a ZIP)
    $zip = new ZipArchive();
    expect($zip->open($path))->toBe(true);
    expect($zip->locateName('word/document.xml'))->not->toBeFalse();
    $zip->close();

    unlink($path);
});

it('includes front matter in DOCX', function () {
    $book = Book::factory()->create([
        'title' => 'My Novel',
        'author' => 'Jane Doe',
        'copyright_text' => '© 2026 Jane Doe',
    ]);
    $chapter = Chapter::factory()->create(['book_id' => $book->id]);
    Scene::factory()->create(['chapter_id' => $chapter->id, 'content' => '<p>Content.</p>']);

    $exporter = new DocxExporter(new ContentPreparer(), new ClassicTemplate());
    $options = ExportOptions::fromArray([
        'front_matter' => ['title-page', 'copyright'],
        'copyright_text' => '© 2026 Jane Doe',
    ]);

    $path = $exporter->export($book, $book->chapters()->with('scenes')->get(), $options);

    // Read the DOCX content to verify title page exists
    $zip = new ZipArchive();
    $zip->open($path);
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    expect($xml)->toContain('My Novel');
    expect($xml)->toContain('Jane Doe');

    unlink($path);
});

it('preserves bold and italic formatting', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->create(['book_id' => $book->id]);
    Scene::factory()->create([
        'chapter_id' => $chapter->id,
        'content' => '<p>This is <strong>bold</strong> and <em>italic</em>.</p>',
    ]);

    $exporter = new DocxExporter(new ContentPreparer(), new ClassicTemplate());
    $options = ExportOptions::fromArray([]);

    $path = $exporter->export($book, $book->chapters()->with('scenes')->get(), $options);

    $zip = new ZipArchive();
    $zip->open($path);
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    // PhpWord uses <w:b/> for bold and <w:i/> for italic in the XML
    expect($xml)->toContain('bold');
    expect($xml)->toContain('italic');

    unlink($path);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=DocxExporterTest`
Expected: FAIL (current DOCX strips formatting)

- [ ] **Step 3: Rewrite DocxExporter**

Replace the export method to produce a proper manuscript-format DOCX:

```php
<?php

namespace App\Services\Export\Exporters;

use App\Contracts\Exporter;
use App\Contracts\ExportTemplate;
use App\Models\Book;
use App\Services\Export\ContentPreparer;
use App\Services\Export\ExportOptions;
use Illuminate\Support\Collection;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;

class DocxExporter implements Exporter
{
    public function __construct(
        private ContentPreparer $contentPreparer,
        private ExportTemplate $template,
    ) {}

    public function export(Book $book, Collection $chapters, ExportOptions $options): string
    {
        $phpWord = new PhpWord();

        // Define styles - industry manuscript format
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(12);

        $phpWord->addParagraphStyle('Normal', [
            'lineHeight' => 2.0, // Double-spaced
            'spaceAfter' => 0,
            'spaceBefore' => 0,
            'indentation' => ['firstLine' => 720], // 0.5 inch first-line indent
        ]);

        $phpWord->addParagraphStyle('NoIndent', [
            'lineHeight' => 2.0,
            'spaceAfter' => 0,
            'spaceBefore' => 0,
        ]);

        $phpWord->addParagraphStyle('ChapterTitle', [
            'lineHeight' => 2.0,
            'spaceAfter' => 240,
            'spaceBefore' => 2400,
            'alignment' => Jc::CENTER,
        ]);

        $phpWord->addParagraphStyle('SceneBreak', [
            'lineHeight' => 2.0,
            'spaceAfter' => 240,
            'spaceBefore' => 240,
            'alignment' => Jc::CENTER,
        ]);

        $phpWord->addParagraphStyle('MatterTitle', [
            'lineHeight' => 2.0,
            'spaceAfter' => 480,
            'spaceBefore' => 2400,
            'alignment' => Jc::CENTER,
        ]);

        $phpWord->addParagraphStyle('MatterBody', [
            'lineHeight' => 2.0,
            'spaceAfter' => 0,
            'spaceBefore' => 0,
            'alignment' => Jc::CENTER,
        ]);

        // Create document section with 1-inch margins
        $section = $phpWord->addSection([
            'marginTop' => 1440,    // 1 inch in twips
            'marginBottom' => 1440,
            'marginLeft' => 1440,
            'marginRight' => 1440,
        ]);

        // Front matter
        if (in_array('title-page', $options->frontMatter)) {
            $section->addTextBreak(8);
            $section->addText($book->title, ['bold' => true, 'size' => 24], ['alignment' => Jc::CENTER]);
            $section->addTextBreak(2);
            $section->addText($book->author, ['size' => 16], ['alignment' => Jc::CENTER]);
            $section->addPageBreak();
        }

        if (in_array('copyright', $options->frontMatter) && $options->copyrightText) {
            $section->addTextBreak(12);
            foreach (explode("\n", $options->copyrightText) as $line) {
                $section->addText(trim($line), ['size' => 10], ['alignment' => Jc::CENTER]);
            }
            $section->addPageBreak();
        }

        if (in_array('dedication', $options->frontMatter) && $options->dedicationText) {
            $section->addTextBreak(8);
            $section->addText($options->dedicationText, ['italic' => true, 'size' => 12], ['alignment' => Jc::CENTER]);
            $section->addPageBreak();
        }

        if (in_array('epigraph', $options->frontMatter) && $options->epigraphText) {
            $section->addTextBreak(8);
            $section->addText($options->epigraphText, ['italic' => true, 'size' => 12], ['alignment' => Jc::CENTER]);
            if ($options->epigraphAttribution) {
                $section->addTextBreak(1);
                $section->addText($options->epigraphAttribution, ['size' => 11], ['alignment' => Jc::CENTER]);
            }
            $section->addPageBreak();
        }

        // Chapters
        foreach ($chapters as $chapter) {
            if ($options->includeChapterTitles && $chapter->title) {
                $section->addText(
                    htmlspecialchars($chapter->title),
                    ['bold' => true, 'size' => 16],
                    'ChapterTitle'
                );
            }

            $content = $chapter->getContentWithSceneBreaks();
            $segments = $this->contentPreparer->toFormattedSegments($content);

            $isFirstParagraph = true;
            $currentRun = null;

            foreach ($segments as $segment) {
                if ($segment['type'] === 'scene-break') {
                    $section->addText('* * *', ['italic' => true], 'SceneBreak');
                    $isFirstParagraph = true;
                    $currentRun = null;
                } elseif ($segment['type'] === 'paragraph-start') {
                    $style = $isFirstParagraph ? 'NoIndent' : 'Normal';
                    $currentRun = $section->addTextRun($style);
                    $isFirstParagraph = false;
                } elseif ($segment['type'] === 'text' && $currentRun) {
                    $fontStyle = [];
                    if ($segment['bold']) {
                        $fontStyle['bold'] = true;
                    }
                    if ($segment['italic']) {
                        $fontStyle['italic'] = true;
                    }
                    if ($segment['strikethrough']) {
                        $fontStyle['strikethrough'] = true;
                    }
                    $currentRun->addText(htmlspecialchars($segment['text']), $fontStyle);
                }
            }

            $section->addPageBreak();
        }

        // Back matter
        if (in_array('epilogue', $options->backMatter)) {
            // Epilogue chapter content rendered here — fetch from ExportService
        }

        if (in_array('acknowledgments', $options->backMatter) && $options->acknowledgmentText) {
            $section->addText('Acknowledgments', ['bold' => true, 'size' => 16], 'MatterTitle');
            foreach (explode("\n", $options->acknowledgmentText) as $line) {
                if (trim($line)) {
                    $section->addText(trim($line), ['size' => 12], 'NoIndent');
                }
            }
            $section->addPageBreak();
        }

        if (in_array('about-author', $options->backMatter) && $options->aboutAuthorText) {
            $section->addText('About the Author', ['bold' => true, 'size' => 16], 'MatterTitle');
            foreach (explode("\n", $options->aboutAuthorText) as $line) {
                if (trim($line)) {
                    $section->addText(trim($line), ['size' => 12], 'NoIndent');
                }
            }
            $section->addPageBreak();
        }

        if (in_array('also-by', $options->backMatter) && $options->alsoByText) {
            $section->addText('Also By ' . $book->author, ['bold' => true, 'size' => 16], 'MatterTitle');
            foreach (explode("\n", $options->alsoByText) as $line) {
                if (trim($line)) {
                    $section->addText(trim($line), ['italic' => true, 'size' => 12], ['alignment' => Jc::CENTER]);
                }
            }
        }

        // Save
        $path = storage_path('app/export-' . \Illuminate\Support\Str::uuid() . '.docx');
        $phpWord->save($path, 'Word2007');

        return $path;
    }
}
```

- [ ] **Step 4: Update ExportService::resolveExporter() for new DocxExporter signature**

The DocxExporter now takes `(ContentPreparer, ExportTemplate)` instead of just `(ContentPreparer)`. Update `app/Services/Export/ExportService.php` line 122:

```php
ExportFormat::Docx => new DocxExporter($contentPreparer, $template),
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --compact --filter=DocxExporterTest`
Expected: PASS

- [ ] **Step 5: Run Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Services/Export/Exporters/DocxExporter.php tests/Feature/Export/DocxExporterTest.php
git commit -m "feat: upgrade DOCX exporter to proper manuscript submission format with formatting preservation"
```

---

### Task 16: Update KDP Exporter

**Files:**
- Modify: `app/Services/Export/Exporters/KdpExporter.php`

- [ ] **Step 1: Forward all new ExportOptions properties**

The existing KdpExporter manually constructs a new `ExportOptions` at line 22, but only forwards a subset of properties. Update it to forward ALL properties including the new ones (fontPairing, sceneBreakStyle, dropCaps, includeCover, new matter texts):

```php
public function export(Book $book, Collection $chapters, ExportOptions $options): string
{
    $this->validateMetadata($book);

    // KDP enforces stricter requirements — override only what's needed
    $kdpOptions = new ExportOptions(
        includeChapterTitles: true,
        includeActBreaks: $options->includeActBreaks,
        includeTableOfContents: true,
        showPageNumbers: $options->showPageNumbers,
        trimSize: $options->trimSize,
        fontSize: $options->fontSize,
        frontMatter: $options->frontMatter,
        backMatter: $options->backMatter,
        copyrightText: $options->copyrightText,
        acknowledgmentText: $options->acknowledgmentText,
        aboutAuthorText: $options->aboutAuthorText,
        template: $options->template,
        // Forward new v2 properties
        fontPairing: $options->fontPairing,
        sceneBreakStyle: $options->sceneBreakStyle,
        dropCaps: $options->dropCaps,
        includeCover: $options->includeCover,
        dedicationText: $options->dedicationText,
        epigraphText: $options->epigraphText,
        epigraphAttribution: $options->epigraphAttribution,
        alsoByText: $options->alsoByText,
    );

    return $this->epubExporter->export($book, $chapters, $kdpOptions);
}
```

- [ ] **Step 2: Add cover image validation warning**

```php
private function validateMetadata(Book $book): void
{
    // Existing validation...

    if (! $book->cover_image_path) {
        logger()->info("KDP export for book {$book->id} missing cover image");
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Services/Export/Exporters/KdpExporter.php
git commit -m "feat: update KDP exporter with cover image awareness"
```

---

## Phase 6: Export Page Refactor

### Task 17: Export Page Frontend Updates

**Files:**
- Create: `resources/js/components/export/TemplateSelector.tsx`
- Create: `resources/js/components/export/TemplateCard.tsx`
- Create: `resources/js/components/export/CustomizePanel.tsx`
- Modify: `resources/js/components/export/ExportSettings.tsx`
- Modify: `resources/js/components/export/ExportReadingOrder.tsx`
- Modify: `resources/js/components/export/types.ts`
- Modify: `resources/js/pages/books/export.tsx`
- Modify: `resources/js/i18n/en/export.json`
- Modify: `app/Http/Controllers/BookSettingsController.php`

**Note:** Before implementing, activate @design-system, @inertia-react-development, and @tailwindcss-development skills. Match the existing export page layout patterns.

- [ ] **Step 1: Update types**

In `resources/js/components/export/types.ts`:

```typescript
export type TemplateDef = {
    slug: string;
    name: string;
    pack: string;
    defaultFontPairing: string;
    defaultSceneBreakStyle: string;
    defaultDropCaps: boolean;
    headingFont: string;
    bodyFont: string;
};

export type FontPairingDef = {
    value: string;
    label: string;
    headingFont: string;
    bodyFont: string;
};

export type SceneBreakStyleDef = {
    value: string;
    label: string;
};

// Update FRONT_MATTER_TYPES to include new types
export const FRONT_MATTER_TYPES = [
    'title-page', 'copyright', 'dedication', 'epigraph', 'toc'
] as const;

export const BACK_MATTER_TYPES = [
    'epilogue', 'acknowledgments', 'about-author', 'also-by'
] as const;
```

- [ ] **Step 2: Update BookSettingsController::export()**

Pass template, font pairing, and scene break data to the frontend:

```php
use App\Enums\FontPairing;
use App\Enums\SceneBreakStyle;
use App\Services\Export\Templates\ClassicTemplate;
use App\Services\Export\Templates\ModernTemplate;
use App\Services\Export\Templates\RomanceTemplate;

// In the export() method, add to the Inertia::render data:
'templates' => collect([new ClassicTemplate(), new ModernTemplate(), new RomanceTemplate()])
    ->map(fn ($t) => [
        'slug' => $t->slug(),
        'name' => $t->name(),
        'pack' => 'Basic',
        'defaultFontPairing' => $t->defaultFontPairing()->value,
        'defaultSceneBreakStyle' => $t->defaultSceneBreakStyle()->value,
        'defaultDropCaps' => $t->defaultDropCaps(),
        'headingFont' => $t->defaultFontPairing()->headingFont(),
        'bodyFont' => $t->defaultFontPairing()->bodyFont(),
    ]),
'fontPairings' => collect(FontPairing::cases())->map(fn ($fp) => [
    'value' => $fp->value,
    'label' => $fp->label(),
    'headingFont' => $fp->headingFont(),
    'bodyFont' => $fp->bodyFont(),
]),
'sceneBreakStyles' => collect(SceneBreakStyle::cases())->map(fn ($s) => [
    'value' => $s->value,
    'label' => $s->label(),
]),
'book' => array_merge($book->only('id', 'title', 'author'), [
    'export_template' => $book->export_template,
    'export_font_pairing' => $book->export_font_pairing,
    'export_scene_break_style' => $book->export_scene_break_style,
    'export_drop_caps' => $book->export_drop_caps,
    'cover_image_path' => $book->cover_image_path,
]),
```

- [ ] **Step 3: Create TemplateCard component**

`resources/js/components/export/TemplateCard.tsx`:

A card that renders:
- Template name in the template's heading font (using `style={{ fontFamily: headingFont }}`)
- "Aa Bb Cc" sample text in the template's body font
- Pack badge ("Basic")
- Selected state (ring/border highlight)
- Locked state for future paid packs

Load the web fonts by adding `<link>` tags for Google Fonts (Crimson Pro, Source Sans 3, Source Serif 4, Cormorant Garamond) in the page head, or use `@fontsource` npm packages.

- [ ] **Step 4: Create TemplateSelector component**

`resources/js/components/export/TemplateSelector.tsx`:

Grid of TemplateCard components. On selection, calls `onChange(templateSlug)` which:
1. Updates the selected template state
2. Resets font/scene break/drop cap to template defaults
3. Triggers live preview refresh

- [ ] **Step 5: Create CustomizePanel component**

`resources/js/components/export/CustomizePanel.tsx`:

Collapsible panel (using Accordion or Collapsible UI component) containing:
- Font pairing selector (Select dropdown showing "Classic Serif", "Modern Mixed", "Elegant Serif")
- Scene break style selector (Select dropdown showing ornament labels)
- Drop caps toggle (Toggle component)

When any value changes, the template display switches to "Custom".

- [ ] **Step 6: Update ExportSettings**

Refactor `resources/js/components/export/ExportSettings.tsx`:
- Replace the old template "Coming Soon" text with `<TemplateSelector>`
- Add `<CustomizePanel>` below the template selector (collapsed by default)
- Add cover image toggle for visual formats
- Pass new options (font_pairing, scene_break_style, drop_caps, include_cover) to the export and preview requests

- [ ] **Step 7: Update ExportReadingOrder**

In `resources/js/components/export/ExportReadingOrder.tsx`:
- Add new front matter items: Dedication, Epigraph
- Add new back matter items: Also By, Epilogue (only shown if a chapter is tagged as epilogue)
- Add "Edit in Publish" link next to matter items that links to the publish page
- Epilogue chapter should NOT appear in the chapters list when it's enabled in back matter

- [ ] **Step 8: Update i18n strings**

Add to `resources/js/i18n/en/export.json`:
```json
{
    "template": "Template",
    "customize": "Customize",
    "customizeDescription": "Override template defaults",
    "customLabel": "Custom",
    "fontPairing": "Font Pairing",
    "sceneBreakStyle": "Scene Break Style",
    "dropCaps": "Drop Caps",
    "includeCover": "Include Cover",
    "noCoverUploaded": "No cover uploaded",
    "editInPublish": "Edit in Publish",
    "frontMatter.dedication": "Dedication",
    "frontMatter.epigraph": "Epigraph",
    "backMatter.epilogue": "Epilogue",
    "backMatter.alsoBy": "Also By"
}
```

- [ ] **Step 9: Generate Wayfinder types and build**

Run: `php artisan wayfinder:generate --no-interaction && npm run build`

- [ ] **Step 10: Commit**

```bash
git add resources/js/components/export/ resources/js/pages/books/export.tsx resources/js/i18n/en/export.json app/Http/Controllers/BookSettingsController.php
git commit -m "feat: export page with template cards, customize panel, and new matter types"
```

---

## Phase 7: Integration & Polish

### Task 18: Update ExportBookRequest Validation

**Files:**
- Modify: `app/Http/Requests/ExportBookRequest.php`

- [ ] **Step 1: Update validation rules**

```php
use App\Enums\FontPairing;
use App\Enums\SceneBreakStyle;

public function rules(): array
{
    return [
        'format' => ['required', Rule::enum(ExportFormat::class)],
        'scope' => ['required_without:chapter_ids', 'in:full,chapter,storyline'],
        'chapter_id' => ['nullable', 'integer', 'exists:chapters,id'],
        'storyline_id' => ['nullable', 'integer', 'exists:storylines,id'],
        'chapter_ids' => ['nullable', 'array'],
        'chapter_ids.*' => ['integer', 'exists:chapters,id'],
        'include_chapter_titles' => ['boolean'],
        'include_act_breaks' => ['boolean'],
        'include_table_of_contents' => ['boolean'],
        'show_page_numbers' => ['boolean'],
        'trim_size' => ['nullable', Rule::enum(TrimSize::class)],
        'font_size' => ['nullable', 'integer', 'in:10,11,12,13,14'],
        'front_matter' => ['nullable', 'array'],
        'front_matter.*' => ['string', Rule::enum(FrontMatterType::class)],
        'back_matter' => ['nullable', 'array'],
        'back_matter.*' => ['string', Rule::enum(BackMatterType::class)],
        'template' => ['nullable', 'string', 'in:classic,modern,romance'],
        'font_pairing' => ['nullable', 'string', Rule::enum(FontPairing::class)],
        'scene_break_style' => ['nullable', 'string', Rule::enum(SceneBreakStyle::class)],
        'drop_caps' => ['nullable', 'boolean'],
        'include_cover' => ['nullable', 'boolean'],
    ];
}
```

- [ ] **Step 2: Run Pint and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Http/Requests/ExportBookRequest.php
git commit -m "feat: update export validation for templates, fonts, scene breaks, drop caps, and cover"
```

---

### Task 19: End-to-End Export Integration Test

**Files:**
- Create: `tests/Feature/Export/ExportIntegrationTest.php`

- [ ] **Step 1: Write integration tests**

```php
// tests/Feature/Export/ExportIntegrationTest.php
<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Scene;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->book = Book::factory()->create([
        'title' => 'Test Novel',
        'author' => 'Test Author',
        'language' => 'en',
        'copyright_text' => '© 2026 Test Author',
        'dedication_text' => 'For testing',
    ]);
    $chapter = Chapter::factory()->create([
        'book_id' => $this->book->id,
        'title' => 'Chapter One',
    ]);
    Scene::factory()->create([
        'chapter_id' => $chapter->id,
        'content' => '<p>This is <strong>bold</strong> and <em>italic</em> text.</p><hr><p>After the break.</p>',
    ]);
    $this->actingAs($this->user);
});

it('exports EPUB with classic template', function () {
    $response = $this->post(route('books.settings.export.run', $this->book), [
        'format' => 'epub',
        'scope' => 'full',
        'template' => 'classic',
        'front_matter' => ['title-page', 'copyright', 'dedication'],
        'back_matter' => [],
        'include_chapter_titles' => true,
    ]);

    $response->assertOk();
    $response->assertDownload('Test Novel.epub');
});

it('exports EPUB with modern template', function () {
    $response = $this->post(route('books.settings.export.run', $this->book), [
        'format' => 'epub',
        'scope' => 'full',
        'template' => 'modern',
        'front_matter' => ['title-page'],
        'back_matter' => [],
    ]);

    $response->assertOk();
    $response->assertDownload('Test Novel.epub');
});

it('exports PDF with romance template and drop caps', function () {
    $response = $this->post(route('books.settings.export.run', $this->book), [
        'format' => 'pdf',
        'scope' => 'full',
        'template' => 'romance',
        'drop_caps' => true,
        'trim_size' => 'us-trade',
        'font_size' => 11,
        'front_matter' => ['title-page'],
        'back_matter' => [],
    ]);

    $response->assertOk();
    $response->assertDownload('Test Novel.pdf');
});

it('exports DOCX with formatting preserved', function () {
    $response = $this->post(route('books.settings.export.run', $this->book), [
        'format' => 'docx',
        'scope' => 'full',
        'front_matter' => ['title-page', 'copyright'],
        'back_matter' => [],
    ]);

    $response->assertOk();
    $response->assertDownload('Test Novel.docx');
});

it('exports with custom font pairing override', function () {
    $response = $this->post(route('books.settings.export.run', $this->book), [
        'format' => 'epub',
        'scope' => 'full',
        'template' => 'classic',
        'font_pairing' => 'modern-mixed',
        'front_matter' => [],
        'back_matter' => [],
    ]);

    $response->assertOk();
    $response->assertDownload('Test Novel.epub');
});

it('exports with custom scene break style', function () {
    $response = $this->post(route('books.settings.export.run', $this->book), [
        'format' => 'epub',
        'scope' => 'full',
        'template' => 'classic',
        'scene_break_style' => 'fleuron',
        'front_matter' => [],
        'back_matter' => [],
    ]);

    $response->assertOk();
    $response->assertDownload('Test Novel.epub');
});
```

- [ ] **Step 2: Run all export tests**

Run: `php artisan test --compact --filter=Export`
Expected: ALL PASS

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Export/ExportIntegrationTest.php
git commit -m "test: add end-to-end export integration tests for all templates and options"
```

---

## Explicitly Deferred (Out of Scope)

These items were discussed and explicitly deferred for future work:

- **Inline images in chapters** — no image embedding in chapter content
- **Footnotes/endnotes** — niche non-fiction feature
- **Custom CSS injection** — power user / Vellum-attack feature
- **Saveable custom presets** — "My Thriller Style" named presets (Option C from grilling)
- **Cross-book matter copying** — "copy dedication from Book 1"
- **Cover design tools** — upload only, no built-in creator
- **Fixed-layout EPUB** — illustrated/children's books
- **ISBN barcode generation** — text field only
- **Print bleed/trim marks on PDF** — professional print prep
- **Paid template packs** — Basic Pack only ships with this release
- **Template pack store/marketplace UI** — future commerce feature
- **Font subsetting** — full TTF files embedded (optimization later)

## Architecture Decisions Record

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Templates are presets, not sealed units | Mix assets across owned packs | User freedom; "Custom" label on any override |
| Template label on override | Show "Custom" (not "Classic modified") | Simplicity for v1 |
| Font pairings, not individual font selection | Heading + body as atomic choice | Ensures good combinations; curated design |
| Scene breaks: template default + user override | Override from any owned pack | More flexibility than Reedsy/KDP (locked to theme) |
| Drop caps: template default + user toggle | Classic/Romance: on, Modern: off | Matches genre expectations |
| Matter content: per-book, not global | Simplicity | Can add "copy from" later |
| Epilogue: tagged chapter, not text area | Epilogue is narrative content | Excluded from body, rendered in back matter |
| Publish page separate from Export page | Content authoring ≠ file generation | Different mindsets, different frequencies |
| DOCX: manuscript submission format | 12pt Times, double-spaced, proper styles | What agents/editors expect; free tier showcase |
| Customize panel: collapsed by default | Most users just pick template + export | Power users expand for overrides |
| Cover: upload only, no creator | Cover design is solved (Canva etc.) | Cover embedding is table stakes |
