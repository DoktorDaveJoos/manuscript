<?php

use App\Enums\BleedMode;
use App\Enums\TrimSize;
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
    expect($geo['margins'])->toEqual(['top' => 18, 'bottom' => 19, 'outer' => 15, 'gutter' => 20]);
});

test('resolveGeometry adds bleed to both dimensions and every margin', function () {
    $geo = PdfExporter::resolveGeometry(ExportOptions::fromArray(['trim_size' => '13x19cm', 'bleed' => 3]));

    expect($geo['width'])->toEqual(136);  // 130 + 2 * 3
    expect($geo['height'])->toEqual(196); // 190 + 2 * 3
    expect($geo['margins'])->toEqual(['top' => 21, 'bottom' => 22, 'outer' => 18, 'gutter' => 23]);
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

/*
 * Bug: imperial trim presets were stored rounded to whole millimetres (6×9″ as
 * 152×229 instead of 152.4×228.6), so every export was up to 0.5 mm off the
 * size the printer's template expects. KDP matches the PDF page size against
 * the chosen trim with a tight tolerance; IngramSpark prints files as sent.
 */
test('imperial trim presets use exact inch-derived dimensions, not whole-millimetre roundings', function () {
    expect(TrimSize::MassMarket->dimensions())->toEqual(['width' => 107.95, 'height' => 174.5])
        ->and(TrimSize::Pocket->dimensions())->toEqual(['width' => 127.0, 'height' => 203.2])
        ->and(TrimSize::Digest->dimensions())->toEqual(['width' => 133.35, 'height' => 203.2])
        ->and(TrimSize::SmallTrade->dimensions())->toEqual(['width' => 139.7, 'height' => 215.9])
        ->and(TrimSize::UsTrade->dimensions())->toEqual(['width' => 152.4, 'height' => 228.6])
        ->and(TrimSize::Royal->dimensions())->toEqual(['width' => 177.8, 'height' => 254.0])
        ->and(TrimSize::Manuscript->dimensions())->toEqual(['width' => 215.9, 'height' => 279.4]);
});

test('metric trim presets keep their exact metric dimensions', function () {
    expect(TrimSize::Novel13x19->dimensions())->toEqual(['width' => 130.0, 'height' => 190.0])
        ->and(TrimSize::A5->dimensions())->toEqual(['width' => 148.0, 'height' => 210.0])
        ->and(TrimSize::A4->dimensions())->toEqual(['width' => 210.0, 'height' => 297.0]);
});

test('export options parses the bleed mode and defaults to all edges', function () {
    expect(ExportOptions::fromArray(['bleed_mode' => 'outer'])->bleedMode)->toBe(BleedMode::Outer)
        ->and(ExportOptions::fromArray(['bleed_mode' => 'all'])->bleedMode)->toBe(BleedMode::All)
        ->and(ExportOptions::fromArray([])->bleedMode)->toBe(BleedMode::All);
});

/*
 * KDP and IngramSpark forbid bleed on the binding edge: the sheet must be
 * trim + 1×bleed wide (bleed on the outside edge only, which alternates
 * recto/verso via the mirrored @page margins) and trim + 2×bleed tall.
 * The gutter margin therefore must NOT shift — only top/bottom/outer do.
 */
test('resolveGeometry with outer bleed grows the width by a single bleed and never shifts the gutter', function () {
    $geo = PdfExporter::resolveGeometry(ExportOptions::fromArray([
        'trim_size' => '13x19cm',
        'bleed' => 3,
        'bleed_mode' => 'outer',
    ]));

    expect($geo['width'])->toEqual(133)   // 130 + 1 * 3 — outside edge only
        ->and($geo['height'])->toEqual(196) // 190 + 2 * 3
        ->and($geo['margins'])->toEqual(['top' => 21, 'bottom' => 22, 'outer' => 18, 'gutter' => 20]);
});

test('an outer-bleed export produces a KDP-style media box: one bleed wide, two bleeds tall', function () {
    [$book, $chapters] = geometryTestBook();

    $bytes = (new PdfExporter(new ContentPreparer, new FontService, new ClassicTemplate))
        ->generatePdfString($book, $chapters, ExportOptions::fromArray([
            'trim_size' => '13x19cm',
            'bleed' => 3,
            'bleed_mode' => 'outer',
        ]));

    expect(in_array([133, 196], pdfMediaBoxesMm($bytes), true))->toBeTrue();
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

/*
 * Bug: the PDF preview reported far fewer pages than the actual export (e.g. 412
 * vs 456). The preview is rendered at bleed 0 (the preview request never sends a
 * bleed), the export with the user's bleed. Root cause: the @page CSS in
 * export/pdf.blade.php emitted the RAW trim size and margins, while the mPDF
 * constructor sized the sheet from resolveGeometry() (trim grown by bleed, margins
 * shifted by bleed). That mismatch shrank the text block whenever bleed > 0,
 * silently reflowing the body onto more pages. Bleed extends the sheet edges for
 * print trim — it must never change how many pages the book occupies.
 */
test('bleed does not change the page count', function () {
    if (! trim((string) shell_exec('command -v pdfinfo'))) {
        $this->markTestSkipped('pdfinfo not available');
    }

    // Enough body copy to span many pages, so any per-page reflow caused by a
    // shrunken text block compounds into a visible page-count difference.
    $para = '<p>'.trim(str_repeat('The light fell at an angle through the blinds and laid '
        .'bright stripes across the worn table while outside the city slowly woke. ', 12)).'</p>';
    $longScene = str_repeat($para, 8);

    $book = Book::factory()->create(['title' => 'Bleed', 'author' => 'Author', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    foreach (range(1, 6) as $order) {
        $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => "Chapter $order", 'reader_order' => $order]);
        Scene::factory()->for($chapter)->create(['content' => $longScene, 'sort_order' => 1]);
    }
    $chapters = $book->chapters()->with(['scenes' => fn ($q) => $q->orderBy('sort_order'), 'act'])->orderBy('reader_order')->get();

    $exporter = new PdfExporter(new ContentPreparer, new FontService, new ClassicTemplate);
    $pageCountFor = function (float $bleed, string $bleedMode = 'all') use ($exporter, $book, $chapters): int {
        $bytes = $exporter->generatePdfString($book, $chapters, ExportOptions::fromArray([
            'trim_size' => 'voyage',
            'font_size' => 11,
            'bleed' => $bleed,
            'bleed_mode' => $bleedMode,
        ]));

        $pdf = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
        file_put_contents($pdf, $bytes);
        $info = (string) shell_exec('pdfinfo '.escapeshellarg($pdf).' 2>/dev/null');
        @unlink($pdf);

        return preg_match('/Pages:\s+(\d+)/', $info, $m) ? (int) $m[1] : -1;
    };

    $noBleed = $pageCountFor(0);

    expect($noBleed)->toBeGreaterThan(1)
        ->and($pageCountFor(3))->toBe($noBleed)
        ->and($pageCountFor(5))->toBe($noBleed)
        ->and($pageCountFor(3, 'outer'))->toBe($noBleed);
});

/*
 * Guards the root cause directly: the @page CSS must mirror the bleed-adjusted
 * geometry the mPDF constructor uses, so the two can never disagree again.
 */
test('the @page CSS uses the same bleed-adjusted geometry as the mPDF constructor', function (string $bleedMode) {
    $book = Book::factory()->create(['language' => 'en']);

    $options = ExportOptions::fromArray([
        'trim_size' => 'voyage',
        'bleed' => 3,
        'bleed_mode' => $bleedMode,
    ]);

    $html = (new PdfExporter(new ContentPreparer, new FontService, new ClassicTemplate))
        ->renderHtml($book, collect(), $options, false);
    $geometry = PdfExporter::resolveGeometry($options);

    // Sheet size matches the constructor's bleed-grown trim.
    expect($html)->toContain(sprintf('size: %smm %smm', $geometry['width'], $geometry['height']));

    // Recto (:right) margins carry the bleed shift: top right(gutter) bottom left(outer).
    expect($html)->toContain(sprintf(
        'margin: %smm %smm %smm %smm',
        $geometry['margins']['top'],
        $geometry['margins']['gutter'],
        $geometry['margins']['bottom'],
        $geometry['margins']['outer'],
    ));
})->with(['all', 'outer']);
