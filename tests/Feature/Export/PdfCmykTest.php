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
 * Decompress every content stream in a PDF and concatenate the result so we
 * can inspect the colour operators mPDF emitted (rg = RGB, k = CMYK, g = grey).
 */
function pdfColorOperators(string $pdf): string
{
    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $matches);

    $content = '';
    foreach ($matches[1] as $stream) {
        $inflated = @gzuncompress($stream);
        $content .= $inflated !== false ? $inflated : $stream;
    }

    return $content;
}

function cmykTestBook(): array
{
    $book = Book::factory()->create(['title' => 'Print Test', 'author' => 'Author', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Chapter One', 'reader_order' => 1]);
    Scene::factory()->for($chapter)->create(['content' => '<p>The body text that should print as solid black.</p>', 'sort_order' => 1]);

    $chapters = $book->chapters()->with(['scenes' => fn ($q) => $q->orderBy('sort_order'), 'act'])->orderBy('reader_order')->get();

    return [$book, $chapters];
}

beforeEach(function () {
    License::factory()->create();
});

test('cmyk pdf export renders body text as K-only black with no RGB colour', function () {
    [$book, $chapters] = cmykTestBook();

    $bytes = (new PdfExporter(new ContentPreparer, new FontService, new ClassicTemplate))
        ->generatePdfString($book, $chapters, ExportOptions::fromArray([
            'trim_size' => '6x9',
            'font_size' => 11,
            'cmyk' => true,
        ]));

    $operators = pdfColorOperators($bytes);

    // K-only black: cyan/magenta/yellow at 0, K at full (1.000), CMYK "k" operator.
    expect($operators)->toContain('0.000 0.000 0.000 1.000 k');

    // No RGB fill operators should survive in a CMYK document.
    expect($operators)->not->toMatch('/\d\.\d{3} \d\.\d{3} \d\.\d{3} rg/');
});

test('non-cmyk pdf export stays in RGB by default', function () {
    [$book, $chapters] = cmykTestBook();

    $bytes = (new PdfExporter(new ContentPreparer, new FontService, new ClassicTemplate))
        ->generatePdfString($book, $chapters, ExportOptions::fromArray([
            'trim_size' => '6x9',
            'font_size' => 11,
        ]));

    $operators = pdfColorOperators($bytes);

    expect($operators)->toMatch('/\d\.\d{3} \d\.\d{3} \d\.\d{3} rg/');
    expect($operators)->not->toContain('0.000 0.000 0.000 1.000 k');
});

test('export options parses the cmyk flag', function () {
    expect(ExportOptions::fromArray(['cmyk' => true])->cmyk)->toBeTrue();
    expect(ExportOptions::fromArray([])->cmyk)->toBeFalse();
});
