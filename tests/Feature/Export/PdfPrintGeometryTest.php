<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\License;
use App\Models\Scene;
use App\Models\Storyline;
use App\Services\Export\ContentPreparer;
use App\Services\Export\Exporters\PdfExporter;
use App\Services\Export\ExportOptions;
use App\Services\Export\FontService;
use App\Services\Export\Templates\ClassicTemplate;

/**
 * Extract every /MediaBox from a PDF and return each as a sorted [w, h] pair in
 * whole millimetres (PDF points → mm at 72/25.4).
 *
 * @return array<int, array{0: int, 1: int}>
 */
function pdfMediaBoxesMm(string $pdf): array
{
    preg_match_all('/\/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)\s*\]/', $pdf, $matches, PREG_SET_ORDER);

    $boxes = [];
    foreach ($matches as $match) {
        $pair = [
            (int) round(((float) $match[1]) / 2.83464567),
            (int) round(((float) $match[2]) / 2.83464567),
        ];
        sort($pair);
        $boxes[] = $pair;
    }

    return $boxes;
}

function geometryTestBook(): array
{
    $book = Book::factory()->create(['title' => 'Geometry', 'author' => 'Author', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'One', 'reader_order' => 1]);
    Scene::factory()->for($chapter)->create(['content' => '<p>Body text.</p>', 'sort_order' => 1]);

    return [$book, $book->chapters()->with(['scenes' => fn ($q) => $q->orderBy('sort_order'), 'act'])->orderBy('reader_order')->get()];
}

beforeEach(function () {
    License::factory()->create();
});

test('resolveGeometry returns preset dimensions and margins unchanged with no bleed', function () {
    $geo = PdfExporter::resolveGeometry(ExportOptions::fromArray(['trim_size' => '13x19cm']));

    expect($geo['width'])->toEqual(130);
    expect($geo['height'])->toEqual(190);
    expect($geo['margins'])->toEqual(['top' => 16, 'bottom' => 18, 'outer' => 14, 'gutter' => 19]);
});

test('resolveGeometry adds bleed to both dimensions and every margin', function () {
    $geo = PdfExporter::resolveGeometry(ExportOptions::fromArray(['trim_size' => '13x19cm', 'bleed' => 3]));

    expect($geo['width'])->toEqual(136);  // 130 + 2 * 3
    expect($geo['height'])->toEqual(196); // 190 + 2 * 3
    expect($geo['margins'])->toEqual(['top' => 19, 'bottom' => 21, 'outer' => 17, 'gutter' => 22]);
});

test('resolveGeometry uses custom dimensions over any preset', function () {
    $geo = PdfExporter::resolveGeometry(ExportOptions::fromArray(['custom_width' => 120, 'custom_height' => 180]));

    expect($geo['width'])->toEqual(120);
    expect($geo['height'])->toEqual(180);
    expect($geo['margins'])->toHaveKeys(['top', 'bottom', 'outer', 'gutter']);
});

test('resolveGeometry combines custom dimensions with bleed', function () {
    $geo = PdfExporter::resolveGeometry(ExportOptions::fromArray(['custom_width' => 120, 'custom_height' => 180, 'bleed' => 5]));

    expect($geo['width'])->toEqual(130);  // 120 + 2 * 5
    expect($geo['height'])->toEqual(190); // 180 + 2 * 5
});

test('export options parses bleed and custom dimensions', function () {
    $options = ExportOptions::fromArray(['bleed' => 3, 'custom_width' => 120, 'custom_height' => 180]);

    expect($options->bleed)->toEqual(3.0);
    expect($options->customWidth)->toEqual(120.0);
    expect($options->customHeight)->toEqual(180.0);

    $defaults = ExportOptions::fromArray([]);
    expect($defaults->bleed)->toEqual(0.0);
    expect($defaults->customWidth)->toBeNull();
    expect($defaults->customHeight)->toBeNull();
});

test('a bleed export produces a PDF media box at trim size plus bleed', function () {
    [$book, $chapters] = geometryTestBook();

    $bytes = (new PdfExporter(new ContentPreparer, new FontService, new ClassicTemplate))
        ->generatePdfString($book, $chapters, ExportOptions::fromArray([
            'trim_size' => '13x19cm',
            'bleed' => 3,
        ]));

    expect(in_array([136, 196], pdfMediaBoxesMm($bytes), true))->toBeTrue();
});

test('a custom-format export produces a PDF media box at the custom size', function () {
    [$book, $chapters] = geometryTestBook();

    $bytes = (new PdfExporter(new ContentPreparer, new FontService, new ClassicTemplate))
        ->generatePdfString($book, $chapters, ExportOptions::fromArray([
            'custom_width' => 120,
            'custom_height' => 180,
        ]));

    expect(in_array([120, 180], pdfMediaBoxesMm($bytes), true))->toBeTrue();
});
