# Export Templates Hardening — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Harden the 3 export templates to be rock-solid for modern literature — rename Romance→Elegant, fix drop caps, add missing CSS, remove accent colors, and cover all edge cases.

**Architecture:** Changes are isolated to the export template classes (CSS generation), ContentPreparer (drop cap regex), one validation rule, one controller import, one migration, and one test file. No Blade template or ExportService logic changes beyond the rename.

**Tech Stack:** PHP 8.4, Pest 4, mPDF, TipTap HTML output, Laravel 12

**Spec:** `docs/superpowers/specs/2026-03-25-export-templates-hardening.md`

**Scope note:** The spec lists frontend files (`TemplateSelector.tsx`, `TemplateCard.tsx`, `types.ts`) and i18n JSON files as affected. After inspection, these require no changes — template names and slugs are passed dynamically via Inertia props from `BookSettingsController`, and there are no hardcoded "romance" strings in the frontend or i18n files.

---

### Task 1: Rename RomanceTemplate → ElegantTemplate

**Files:**
- Rename: `app/Services/Export/Templates/RomanceTemplate.php` → `app/Services/Export/Templates/ElegantTemplate.php`
- Modify: `app/Services/Export/ExportService.php:14-16,131-137`
- Modify: `app/Http/Controllers/BookSettingsController.php:18,138`
- Modify: `app/Http/Requests/ExportBookRequest.php:38`
- Modify: `tests/Feature/Export/ExportIntegrationTest.php:68,72`

- [ ] **Step 1: Rename the file and update class internals**

Copy `app/Services/Export/Templates/RomanceTemplate.php` to `app/Services/Export/Templates/ElegantTemplate.php`. Update the class name, slug, and name:

```php
class ElegantTemplate implements ExportTemplate
{
    public function slug(): string
    {
        return 'elegant';
    }

    public function name(): string
    {
        return 'Elegant';
    }
    // ... rest unchanged for now
}
```

Delete the old `RomanceTemplate.php` file.

- [ ] **Step 2: Update ExportService imports and resolveTemplate()**

In `app/Services/Export/ExportService.php`:

Replace import line 16:
```php
use App\Services\Export\Templates\ElegantTemplate;
```

Update `resolveTemplate()` (line 131-137) — keep `'romance'` as a backward-compat alias:
```php
public static function resolveTemplate(string $slug): ExportTemplate
{
    return match ($slug) {
        'modern' => new ModernTemplate,
        'elegant', 'romance' => new ElegantTemplate,
        default => new ClassicTemplate,
    };
}
```

- [ ] **Step 3: Update BookSettingsController**

In `app/Http/Controllers/BookSettingsController.php`:

Replace import (line 18):
```php
use App\Services\Export\Templates\ElegantTemplate;
```

Replace template list (line 138):
```php
'templates' => collect([new ClassicTemplate, new ModernTemplate, new ElegantTemplate])
```

- [ ] **Step 4: Update validation rule**

In `app/Http/Requests/ExportBookRequest.php` (line 38) — include `'romance'` for backward compat (matching the alias in `resolveTemplate()`):
```php
'template' => ['nullable', 'string', 'in:classic,modern,elegant,romance'],
```

- [ ] **Step 5: Update integration test**

In `tests/Feature/Export/ExportIntegrationTest.php`:

Line 68 — rename test:
```php
it('exports EPUB with elegant template and drop caps', function () {
```

Line 72 — update template slug:
```php
'template' => 'elegant',
```

- [ ] **Step 6: Run affected tests**

Run: `php artisan test --compact --filter=ExportIntegrationTest`
Expected: All tests PASS (including the renamed elegant template test)

- [ ] **Step 7: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 8: Commit**

```bash
git add app/Services/Export/Templates/ElegantTemplate.php app/Services/Export/ExportService.php app/Http/Controllers/BookSettingsController.php app/Http/Requests/ExportBookRequest.php tests/Feature/Export/ExportIntegrationTest.php
git rm app/Services/Export/Templates/RomanceTemplate.php
git commit -m "refactor: rename RomanceTemplate to ElegantTemplate

The Cormorant Garamond + Crimson Pro pairing is versatile for
literary fiction, not just romance. Keep 'romance' as a backward-
compatible alias in resolveTemplate()."
```

---

### Task 2: Drop Caps Off By Default

**Files:**
- Modify: `app/Services/Export/Templates/ClassicTemplate.php:57`
- Modify: `app/Services/Export/Templates/ElegantTemplate.php:57`

- [ ] **Step 1: Update ClassicTemplate**

In `app/Services/Export/Templates/ClassicTemplate.php`, change `defaultDropCaps()`:
```php
public function defaultDropCaps(): bool
{
    return false;
}
```

- [ ] **Step 2: Update ElegantTemplate**

In `app/Services/Export/Templates/ElegantTemplate.php`, change `defaultDropCaps()`:
```php
public function defaultDropCaps(): bool
{
    return false;
}
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --compact --filter=ExportIntegrationTest`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Services/Export/Templates/ClassicTemplate.php app/Services/Export/Templates/ElegantTemplate.php
git commit -m "fix: make drop caps opt-in, not default

Drop caps are used sparingly in modern literature. All 3 templates
now default to false — users can still enable them via the toggle."
```

---

### Task 3: Fix Drop Cap Regex for Inline Formatting

**Files:**
- Modify: `app/Services/Export/ContentPreparer.php:61-81`
- Create: `tests/Feature/Export/DropCapTest.php`

- [ ] **Step 1: Create the test file**

Run: `php artisan make:test --pest Export/DropCapTest`

Write the test file at `tests/Feature/Export/DropCapTest.php`:

```php
<?php

use App\Services\Export\ContentPreparer;

it('adds drop cap to plain paragraph', function () {
    $preparer = new ContentPreparer;
    $html = '<p>The morning was cold.</p>';
    $result = $preparer->addDropCap($html);

    expect($result)->toBe('<p><span class="drop-cap">T</span>he morning was cold.</p>');
});

it('adds drop cap with leading punctuation', function () {
    $preparer = new ContentPreparer;
    $html = '<p>"The morning was cold."</p>';
    $result = $preparer->addDropCap($html);

    expect($result)->toContain('<span class="drop-cap">"T</span>');
});

it('adds drop cap with curly quote', function () {
    $preparer = new ContentPreparer;
    $html = '<p>' . "\u{201C}" . 'The morning was cold.' . "\u{201D}" . '</p>';
    $result = $preparer->addDropCap($html);

    expect($result)->toContain('class="drop-cap"');
});

it('adds drop cap inside em tag', function () {
    $preparer = new ContentPreparer;
    $html = '<p><em>The morning was cold.</em></p>';
    $result = $preparer->addDropCap($html);

    expect($result)->toBe('<p><em><span class="drop-cap">T</span>he morning was cold.</em></p>');
});

it('adds drop cap inside nested em and strong tags', function () {
    $preparer = new ContentPreparer;
    $html = '<p><em><strong>The morning was cold.</strong></em></p>';
    $result = $preparer->addDropCap($html);

    expect($result)->toBe('<p><em><strong><span class="drop-cap">T</span>he morning was cold.</strong></em></p>');
});

it('adds drop cap inside em with leading quote', function () {
    $preparer = new ContentPreparer;
    $html = '<p><em>' . "\u{201C}" . 'The morning was cold.</em></p>';
    $result = $preparer->addDropCap($html);

    expect($result)->toContain('class="drop-cap"');
    expect($result)->toContain("\u{201C}T</span>");
});

it('skips drop cap when no letter found', function () {
    $preparer = new ContentPreparer;
    $html = '<p>   </p>';
    $result = $preparer->addDropCap($html);

    expect($result)->toBe('<p>   </p>');
});

it('only applies drop cap to first paragraph', function () {
    $preparer = new ContentPreparer;
    $html = '<p>First paragraph.</p><p>Second paragraph.</p>';
    $result = $preparer->addDropCap($html);

    expect($result)->toContain('<span class="drop-cap">F</span>');
    expect(substr_count($result, 'drop-cap'))->toBe(1);
});
```

- [ ] **Step 2: Run tests to verify failures**

Run: `php artisan test --compact --filter=DropCapTest`
Expected: The `em tag` and `nested em and strong` tests FAIL (others should pass since the existing regex handles plain text and quotes).

- [ ] **Step 3: Update the addDropCap regex**

In `app/Services/Export/ContentPreparer.php`, replace the `addDropCap` method (lines 61-81):

```php
/**
 * Add a drop cap to the first letter of the first non-empty paragraph,
 * capturing any leading punctuation (quotes, brackets) into the drop cap span.
 * Handles inline tags (em, strong, span) wrapping the first letter.
 */
public function addDropCap(string $html): string
{
    // Match first <p> tag, then any number of opening inline tags, then optional
    // punctuation, then the first letter. Captures:
    // 1: <p...>
    // 2: optional whitespace
    // 3: zero or more opening inline tags like <em>, <strong>, <span class="...">
    // 4: leading punctuation (quotes, brackets)
    // 5: first letter
    $pattern = '/(<p[^>]*>)([\s]*)((?:<[^\/][^>]*>)*)(["\'"\'\x{201C}\x{201D}\x{2018}\x{2019}\x{00AB}\x{00BF}\x{00A1}\(\[]*)([\p{L}\p{N}])/u';

    return preg_replace_callback(
        $pattern,
        function ($matches) {
            $openTag = $matches[1];
            $whitespace = $matches[2];
            $inlineTags = $matches[3];
            $punctuation = $matches[4];
            $letter = $matches[5];

            $dropCapContent = $punctuation.$letter;

            return "{$openTag}{$whitespace}{$inlineTags}<span class=\"drop-cap\">{$dropCapContent}</span>";
        },
        $html,
        1,
    );
}
```

- [ ] **Step 4: Run tests to verify all pass**

Run: `php artisan test --compact --filter=DropCapTest`
Expected: All 8 tests PASS

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add app/Services/Export/ContentPreparer.php tests/Feature/Export/DropCapTest.php
git commit -m "fix: handle inline tags in drop cap detection

The drop cap regex now skips past opening <em>, <strong>, <span>
tags before matching the first letter, so chapters starting with
italic text or bold text get drop caps correctly."
```

---

### Task 4: Drop Accent Colors From All Templates

This is the largest task — replace all accent color references across all 3 templates in `baseCss()`, `epubCss()`, `sceneBreakCss()`, `dropCapCss()`, and `designTokens()`.

**Files:**
- Modify: `app/Services/Export/Templates/ClassicTemplate.php`
- Modify: `app/Services/Export/Templates/ModernTemplate.php`
- Modify: `app/Services/Export/Templates/ElegantTemplate.php`

**Color mapping (from spec):**
- Classic: accent was `#999999` → stays `#999999` (already correct, just remove `accentColor` token)
- Modern: accent was `#888888` → stays `#888888` (already correct, just remove `accentColor` token)
- Elegant: accent was `#8b7355` → replace with `#999999` (secondary gray) or `#1a1a1a` (heading color for drop caps)

- [ ] **Step 1: Update ClassicTemplate — remove accentColor from designTokens()**

In `app/Services/Export/Templates/ClassicTemplate.php`, remove the `accentColor` line from `designTokens()`:

```php
public function designTokens(): array
{
    return [
        'bodyColor' => '#2a2a2a',
        'headingColor' => '#1a1a1a',
        'pdfLineHeight' => 1.35,
        'epubLineHeight' => 1.5,
        'chapterLabelSizeEm' => 0.7,
        'titleSizeEm' => 1.6,
        'titleWeight' => 'normal',
        'runningHeaderStyle' => 'italic',
        'runningHeaderColor' => '#999999',
        'runningHeaderSizePt' => 8,
        'pageNumberColor' => '#999999',
        'pageNumberSizePt' => 8,
        'pageNumberPosition' => 'alternating',
    ];
}
```

No CSS changes needed for Classic — its accent color was already the same neutral gray used everywhere.

- [ ] **Step 2: Update ModernTemplate — remove accentColor from designTokens()**

In `app/Services/Export/Templates/ModernTemplate.php`, remove the `accentColor` line from `designTokens()`:

```php
public function designTokens(): array
{
    return [
        'bodyColor' => '#333333',
        'headingColor' => '#111111',
        'pdfLineHeight' => 1.4,
        'epubLineHeight' => 1.6,
        'chapterLabelSizeEm' => 0.65,
        'titleSizeEm' => 1.8,
        'titleWeight' => 'bold',
        'runningHeaderStyle' => 'normal',
        'runningHeaderColor' => '#aaaaaa',
        'runningHeaderSizePt' => 7,
        'pageNumberColor' => '#aaaaaa',
        'pageNumberSizePt' => 7,
        'pageNumberPosition' => 'alternating',
    ];
}
```

No CSS changes needed for Modern — its accent color matched its existing grays.

- [ ] **Step 3: Update ElegantTemplate — remove accentColor and replace all #8b7355**

In `app/Services/Export/Templates/ElegantTemplate.php`:

**designTokens()** — remove `accentColor`:
```php
public function designTokens(): array
{
    return [
        'bodyColor' => '#2a2a2a',
        'headingColor' => '#1a1a1a',
        'pdfLineHeight' => 1.4,
        'epubLineHeight' => 1.55,
        'chapterLabelSizeEm' => 0.65,
        'titleSizeEm' => 2.0,
        'titleWeight' => 'normal',
        'runningHeaderStyle' => 'italic',
        'runningHeaderColor' => '#999999',
        'runningHeaderSizePt' => 8,
        'pageNumberColor' => '#999999',
        'pageNumberSizePt' => 8,
        'pageNumberPosition' => 'alternating',
    ];
}
```

**baseCss()** — replace all `#8b7355` with `#999999`:
- `.chapter-label` color → `#999999`
- `.scene-break` color → `#999999`
- `.matter-title` color → `#999999`
- `.title-page-author` color → `#999999`
- `.copyright-text` color → `#999`

**epubCss()** — replace all `#8b7355` with `#999999`:
- `hr.scene-break::after` color → `#999999`
- `.title-page .author` color → `#999999`
- `.copyright-page` color → `#999`
- `.matter-title` color → `#999999`

**sceneBreakCss()** — full replacement:
```php
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
        text-indent: 0;
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
```

**dropCapCss()** — replace `#8b7355` with `#1a1a1a` (heading color, matching Classic):
```php
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

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact --filter=ExportIntegrationTest`
Expected: All PASS

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add app/Services/Export/Templates/ClassicTemplate.php app/Services/Export/Templates/ModernTemplate.php app/Services/Export/Templates/ElegantTemplate.php
git commit -m "refactor: drop accent colors from all export templates

Real books use body-color text and neutral grays, not colored
accents. Replace Elegant's brown #8b7355 with neutral grays
matching Classic/Modern. Remove accentColor from designTokens()."
```

---

### Task 5: Add Blockquote, List, and Hyphenation CSS to All Templates

Add the missing CSS rules to `baseCss()` and `epubCss()` across all 3 templates.

**Files:**
- Modify: `app/Services/Export/Templates/ClassicTemplate.php`
- Modify: `app/Services/Export/Templates/ModernTemplate.php`
- Modify: `app/Services/Export/Templates/ElegantTemplate.php`

The CSS additions are identical across all 3 templates (they share the same structural rules, only colors/fonts differ).

- [ ] **Step 1: Add hyphenation to baseCss() body rule — all 3 templates**

In each template's `baseCss()`, add to the `body` CSS block after `color`:
```css
hyphens: auto;
-webkit-hyphens: auto;
```

- [ ] **Step 2: Add blockquote and list CSS to baseCss() — all 3 templates**

In each template's `baseCss()`, add after the `.chapter-section` rule at the end:
```css
blockquote {
    margin: 1em 0 1em 2em;
    font-size: 0.95em;
    font-style: italic;
}
blockquote p {
    text-indent: 0;
}
ul, ol {
    margin: 0.8em 0 0.8em 2em;
    padding: 0;
}
li {
    margin: 0.2em 0;
    text-indent: 0;
}
```

- [ ] **Step 3: Add blockquote and list CSS to epubCss() — all 3 templates**

In each template's `epubCss()`, add after the `.matter-body p` rule at the end:
```css
blockquote {
    margin: 1em 0 1em 2em;
    font-size: 0.95em;
    font-style: italic;
}
blockquote p {
    text-indent: 0;
}
ul, ol {
    margin: 0.8em 0 0.8em 2em;
    padding: 0;
}
li {
    margin: 0.2em 0;
    text-indent: 0;
}
```

Note: EPUB already has `hyphens: auto` in the body — no change needed there.

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact --filter=ExportIntegrationTest`
Expected: All PASS

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add app/Services/Export/Templates/ClassicTemplate.php app/Services/Export/Templates/ModernTemplate.php app/Services/Export/Templates/ElegantTemplate.php
git commit -m "fix: add blockquote, list styling, and PDF hyphenation

TipTap StarterKit allows blockquotes and lists that had no CSS
in PDF or EPUB exports. Also add hyphens:auto to PDF to prevent
rivers of whitespace in justified text."
```

---

### Task 6: Scene Break Page-Break Protection on Modern Template

**Files:**
- Modify: `app/Services/Export/Templates/ModernTemplate.php:330-347`

- [ ] **Step 1: Add page-break rules to Modern sceneBreakCss()**

In `app/Services/Export/Templates/ModernTemplate.php`, update `sceneBreakCss()`:

```php
public function sceneBreakCss(): string
{
    return <<<'CSS'
    .scene-break {
        margin: 1.5em 0;
        text-indent: 0;
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
```

- [ ] **Step 2: Run tests**

Run: `php artisan test --compact --filter=ExportIntegrationTest`
Expected: All PASS

- [ ] **Step 3: Commit**

```bash
git add app/Services/Export/Templates/ModernTemplate.php
git commit -m "fix: add page-break protection to Modern scene breaks

Classic and Elegant already had page-break-before/after: avoid on
scene breaks. Modern was missing this, allowing scene break symbols
to be stranded at page boundaries."
```

---

### Task 7: Database Migration for Romance → Elegant

**Files:**
- Create: `database/migrations/XXXX_XX_XX_XXXXXX_rename_romance_to_elegant_template.php`

- [ ] **Step 1: Create the migration**

Run: `php artisan make:migration rename_romance_to_elegant_template --no-interaction`

Write the migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('books')
            ->where('export_template', 'romance')
            ->update(['export_template' => 'elegant']);
    }

    public function down(): void
    {
        DB::table('books')
            ->where('export_template', 'elegant')
            ->update(['export_template' => 'romance']);
    }
};
```

- [ ] **Step 2: Run the migration (default database)**

Run: `php artisan migrate --no-interaction`
Expected: Migration runs successfully

- [ ] **Step 3: Run the migration (NativePHP database)**

Run: `DB_DATABASE=database/nativephp.sqlite php artisan migrate --no-interaction`
Expected: Migration runs successfully

- [ ] **Step 4: Commit**

```bash
git add database/migrations/*rename_romance_to_elegant_template*
git commit -m "chore: migrate stored romance template preferences to elegant"
```

---

### Task 8: Final Verification

- [ ] **Step 1: Run full export test suite**

Run: `php artisan test --compact --filter=Export`
Expected: All export tests PASS

- [ ] **Step 2: Run drop cap tests**

Run: `php artisan test --compact --filter=DropCapTest`
Expected: All 8 tests PASS

- [ ] **Step 3: Run Pint on all changed files**

Run: `vendor/bin/pint --dirty --format agent`
Expected: No formatting issues

- [ ] **Step 4: Verify no remaining romance references in export code**

Run: `grep -r "romance" app/Services/Export/ --include="*.php"`
Expected: No matches (the backward-compat alias in ExportService uses the string `'romance'` in the match — that's expected and fine)

Run: `grep -r "#8b7355" app/ --include="*.php"`
Expected: No matches
