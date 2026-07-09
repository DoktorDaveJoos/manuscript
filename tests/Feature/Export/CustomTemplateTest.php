<?php

use App\Enums\FontPairing;
use App\Enums\SceneBreakStyle;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\DesignTemplate;
use App\Models\Scene;
use App\Services\Export\Exporters\PdfExporter;
use App\Services\Export\ExportOptions;
use App\Services\Export\ExportService;
use App\Services\Export\Templates\ClassicTemplate;
use App\Services\Export\Templates\CustomTemplate;
use App\Services\Export\Templates\ElegantTemplate;

function customSettings(array $overrides = []): array
{
    return array_replace_recursive((new ClassicTemplate)->designSettings(), $overrides);
}

it('resolves a custom template slug to a CustomTemplate', function () {
    $row = DesignTemplate::factory()->create(['name' => 'My Novel Look', 'based_on' => 'elegant']);

    $template = ExportService::resolveTemplate('custom:'.$row->id);

    expect($template)->toBeInstanceOf(CustomTemplate::class)
        ->and($template->name())->toBe('My Novel Look')
        ->and($template->slug())->toBe('custom:'.$row->id);
});

it('falls back to classic when the custom template row is gone', function () {
    expect(ExportService::resolveTemplate('custom:99999'))->toBeInstanceOf(ClassicTemplate::class);
});

it('uses settings for template defaults', function () {
    $template = new CustomTemplate(new ElegantTemplate, customSettings([
        'typography' => ['font_pairing' => 'modern-mixed'],
        'headings' => ['drop_caps' => true, 'scene_break_style' => 'rule'],
    ]), 'Custom');

    expect($template->defaultFontPairing())->toBe(FontPairing::ModernMixed)
        ->and($template->defaultDropCaps())->toBeTrue()
        ->and($template->defaultSceneBreakStyle())->toBe(SceneBreakStyle::Rule);
});

it('appends override css for typography settings', function () {
    $template = new CustomTemplate(new ClassicTemplate, customSettings([
        'typography' => ['line_height' => 1.6, 'alignment' => 'left', 'first_line_indent' => false, 'paragraph_spacing_em' => 0.5],
        'headings' => ['heading_scale_em' => 2.2, 'heading_top_space_em' => 6.0],
    ]), 'Custom');

    $css = $template->pdfCss(11);

    expect($css)->toContain('line-height: 1.6')
        ->and($css)->toContain('text-align: left')
        ->and($css)->toContain('text-indent: 0')
        ->and($css)->toContain('font-size: 2.2em')
        ->and($css)->toContain('margin: 6em 0 0.25em');
});

it('merges page geometry overrides into export options', function () {
    $row = DesignTemplate::factory()->create(['settings' => customSettings([
        'page' => ['trim_size' => '6x9', 'bleed' => 3.2, 'margin_top' => 30, 'margin_bottom' => 31, 'margin_inner' => 22, 'margin_outer' => 28],
        'typography' => ['font_size' => 12],
    ])]);

    $options = ['format' => 'pdf', 'template' => 'custom:'.$row->id];
    ExportService::applyDesignTemplate($options);

    expect($options['trim_size'])->toBe('6x9')
        ->and($options['bleed'])->toBe(3.2)
        ->and($options['font_size'])->toBe(12)
        ->and($options['margin_top'])->toBe(30);
});

it('applies margin overrides in pdf geometry', function () {
    $options = ExportOptions::fromArray([
        'trim_size' => '6x9',
        'margin_top' => 30.0,
        'margin_bottom' => 31.0,
        'margin_inner' => 22.0,
        'margin_outer' => 28.0,
    ]);

    $geometry = PdfExporter::resolveGeometry($options);

    expect($geometry['margins'])->toBe(['top' => 30.0, 'bottom' => 31.0, 'outer' => 28.0, 'gutter' => 22.0]);
});

it('accepts a custom template slug in saved export settings', function () {
    $book = Book::factory()->create();
    $row = DesignTemplate::factory()->create();

    $response = $this->putJson(route('books.settings.export-settings.update', $book), [
        'settings' => ['format' => 'pdf', 'template' => 'custom:'.$row->id],
    ]);

    $response->assertOk();
    expect($book->fresh()->export_settings['template'])->toBe('custom:'.$row->id);
});

it('renders a pdf preview with a custom template', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Hello world.</p>']);
    $row = DesignTemplate::factory()->create();

    $response = $this->postJson(route('books.export.preview', $book), [
        'format' => 'pdf',
        'scope' => 'full',
        'template' => 'custom:'.$row->id,
    ]);

    $response->assertOk()->assertJsonStructure(['pdf']);
});

/**
 * The value the CSS cascade ends up applying for a selector/property: the
 * LAST declaration in source order (all rules involved share specificity).
 */
function winningCssValue(string $css, string $selector, string $property): ?string
{
    preg_match_all(
        '/(?<![\w.+-])'.preg_quote($selector, '/').'\s*\{[^}]*?'.preg_quote($property, '/').':\s*([^;]+);/s',
        $css,
        $matches,
    );

    if ($matches[1] === []) {
        return null;
    }

    // Normalize numeric formatting ("2.0em" and "2em" are the same CSS value).
    return preg_replace('/(\d+)\.0(?=\D|$)/', '$1', trim((string) end($matches[1])));
}

it('renders a duplicated built-in template identically to its base', function (string $slug) {
    $base = ExportService::resolveTemplate($slug);
    // Exactly what the Book Designer stores when a built-in is first edited:
    // the base template's own reported design settings.
    $duplicate = new CustomTemplate($base, $base->designSettings(), 'Copy');

    $baseCss = $base->pdfCss(11);
    $duplicateCss = $duplicate->pdfCss(11);

    expect(winningCssValue($duplicateCss, '.chapter-label', 'margin'))
        ->toBe(winningCssValue($baseCss, '.chapter-label', 'margin'))
        ->and(winningCssValue($duplicateCss, 'h1', 'font-size'))
        ->toBe(winningCssValue($baseCss, 'h1', 'font-size'))
        ->and(winningCssValue($duplicateCss, 'body', 'line-height'))
        ->toBe(winningCssValue($baseCss, 'body', 'line-height'));
})->with(['classic', 'modern', 'elegant']);

it('reports the heading top space that matches each template\'s actual chapter-label CSS', function (string $slug, float $expected) {
    $settings = ExportService::resolveTemplate($slug)->designSettings();

    expect($settings['headings']['heading_top_space_em'])->toBe($expected);
})->with([
    'classic' => ['classic', 9.0],
    'modern' => ['modern', 2.0],
    'elegant' => ['elegant', 9.0],
]);
