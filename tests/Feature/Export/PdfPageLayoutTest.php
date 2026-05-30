<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\License;
use App\Models\Scene;
use App\Models\Storyline;
use App\Services\Export\ContentPreparer;
use App\Services\Export\Exporters\PdfExporter;
use App\Services\Export\ExportOptions;
use App\Services\Export\ExportService;
use App\Services\Export\FontService;
use App\Services\Export\Templates\ClassicTemplate;
use App\Services\Export\Templates\ElegantTemplate;
use App\Services\Export\Templates\ModernTemplate;

/**
 * Render the paged PDF HTML exactly as PdfExporter does for a real export.
 *
 * @param  array<string, mixed>  $opts
 */
function renderPagedPdfHtml(Book $book, array $opts = []): string
{
    $chapters = $book->chapters()
        ->with(['scenes' => fn ($q) => $q->orderBy('sort_order'), 'act'])
        ->orderBy('reader_order')
        ->get();

    $options = ExportOptions::fromArray(array_merge([
        'chapter_heading' => 'full',
        'show_page_numbers' => true,
        'trim_size' => '6x9',
        'font_size' => 11,
    ], $opts));

    return (new PdfExporter(new ContentPreparer, new FontService, new ClassicTemplate))
        ->renderHtml($book, $chapters, $options, false);
}

/**
 * Count fully blank interior pages (ignoring page-number-only lines, and the
 * trailing page which mPDF can leave empty).
 */
function interiorBlankPages(string $pdfBytes): array
{
    $pdf = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
    $txt = $pdf.'.txt';
    file_put_contents($pdf, $pdfBytes);
    exec('pdftotext -layout '.escapeshellarg($pdf).' '.escapeshellarg($txt).' 2>/dev/null');
    $pages = explode("\f", (string) @file_get_contents($txt));
    @unlink($pdf);
    @unlink($txt);

    $blank = [];
    $last = count($pages) - 1;
    foreach ($pages as $i => $page) {
        if ($i === $last) {
            continue; // trailing blank is a harmless mPDF artifact
        }
        if (trim(preg_replace('/^\s*\d+\s*$/m', '', $page)) === '') {
            $blank[] = $i + 1;
        }
    }

    return $blank;
}

/**
 * Per-page [text, footer] tuples. The footer is the trailing all-digits line that
 * mPDF renders in the bottom margin, or null when the page carries no folio.
 *
 * @return array<int, array{text: string, footer: ?string}>
 */
function pdfPagesWithFooters(string $pdfBytes): array
{
    $pdf = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
    $txt = $pdf.'.txt';
    file_put_contents($pdf, $pdfBytes);
    exec('pdftotext -layout '.escapeshellarg($pdf).' '.escapeshellarg($txt).' 2>/dev/null');
    $pages = explode("\f", (string) @file_get_contents($txt));
    @unlink($pdf);
    @unlink($txt);

    return array_map(function (string $page) {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $page)), fn ($l) => $l !== ''));
        $last = end($lines) ?: '';

        return [
            'text' => implode(' ', $lines),
            'footer' => preg_match('/^\d+$/', $last) ? $last : null,
        ];
    }, $pages);
}

beforeEach(function () {
    License::factory()->create();
});

/*
 * Professional folio: numbering opens on the prologue (when present), runs
 * continuously through the body, and carries on into the epilogue. Matter that
 * precedes the prologue (title page, copyright, …) stays unnumbered.
 */
test('page numbers start at the prologue and run through the epilogue', function () {
    if (! trim((string) shell_exec('command -v pdftotext'))) {
        $this->markTestSkipped('pdftotext not available');
    }

    $book = Book::factory()->create(['title' => 'Folio Test', 'author' => 'Author', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();

    $prologue = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Opening', 'is_prologue' => true, 'reader_order' => 1]);
    Scene::factory()->for($prologue)->create(['content' => '<p>PrologueMarker text.</p>', 'sort_order' => 1]);
    foreach (range(1, 3) as $i) {
        $ch = Chapter::factory()->for($book)->for($storyline)->create(['title' => "Chapter $i", 'reader_order' => $i + 1]);
        Scene::factory()->for($ch)->create(['content' => "<p>BodyMarker{$i} text.</p>", 'sort_order' => 1]);
    }
    $epilogue = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Closing', 'is_epilogue' => true, 'reader_order' => 99]);
    Scene::factory()->for($epilogue)->create(['content' => '<p>EpilogueMarker text.</p>', 'sort_order' => 1]);

    $bytes = file_get_contents(
        (new ExportService)->export($book, [
            'format' => 'pdf',
            'scope' => 'full',
            'show_page_numbers' => true,
            'trim_size' => '6x9',
            'front_matter' => ['title-page', 'prologue'],
            'back_matter' => ['epilogue'],
        ])->getFile()->getPathname()
    );

    $pages = pdfPagesWithFooters($bytes);
    $byMarker = fn (string $marker) => collect($pages)->first(fn ($p) => str_contains($p['text'], $marker));

    // Title page (before the prologue) stays unnumbered.
    expect($byMarker('Folio Test')['footer'])->toBeNull();

    // Numbering opens at the prologue and runs continuously into the epilogue.
    expect($byMarker('PrologueMarker')['footer'])->toBe('1');
    expect($byMarker('BodyMarker1')['footer'])->toBe('2');
    expect($byMarker('BodyMarker2')['footer'])->toBe('3');
    expect($byMarker('BodyMarker3')['footer'])->toBe('4');
    expect($byMarker('EpilogueMarker')['footer'])->toBe('5');

    // No blank interior pages introduced by the reset/page-selector.
    expect(interiorBlankPages($bytes))->toBe([]);
});

/*
 * Without a prologue, numbering still opens at 1 on the first body chapter and
 * the preceding title page stays unnumbered.
 */
test('without a prologue, page numbers start at 1 on the first chapter', function () {
    if (! trim((string) shell_exec('command -v pdftotext'))) {
        $this->markTestSkipped('pdftotext not available');
    }

    $book = Book::factory()->create(['title' => 'No Prologue Book', 'author' => 'Author', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    foreach (range(1, 2) as $i) {
        $ch = Chapter::factory()->for($book)->for($storyline)->create(['title' => "Chapter $i", 'reader_order' => $i]);
        Scene::factory()->for($ch)->create(['content' => "<p>BodyMarker{$i} text.</p>", 'sort_order' => 1]);
    }

    $bytes = file_get_contents(
        (new ExportService)->export($book, [
            'format' => 'pdf',
            'scope' => 'full',
            'show_page_numbers' => true,
            'trim_size' => '6x9',
            'front_matter' => ['title-page'],
        ])->getFile()->getPathname()
    );

    $pages = pdfPagesWithFooters($bytes);
    $byMarker = fn (string $marker) => collect($pages)->first(fn ($p) => str_contains($p['text'], $marker));

    expect($byMarker('No Prologue Book')['footer'])->toBeNull(); // title page
    expect($byMarker('BodyMarker1')['footer'])->toBe('1');
    expect($byMarker('BodyMarker2')['footer'])->toBe('2');
    expect(interiorBlankPages($bytes))->toBe([]);
});

test('no page numbers anywhere when the option is disabled', function () {
    if (! trim((string) shell_exec('command -v pdftotext'))) {
        $this->markTestSkipped('pdftotext not available');
    }

    $book = Book::factory()->create(['title' => 'No Folio', 'author' => 'Author', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    foreach (range(1, 2) as $i) {
        $ch = Chapter::factory()->for($book)->for($storyline)->create(['title' => "Chapter $i", 'reader_order' => $i]);
        Scene::factory()->for($ch)->create(['content' => "<p>Body{$i}.</p>", 'sort_order' => 1]);
    }

    $bytes = file_get_contents(
        (new ExportService)->export($book, [
            'format' => 'pdf',
            'scope' => 'full',
            'show_page_numbers' => false,
            'trim_size' => '6x9',
            'front_matter' => ['title-page'],
        ])->getFile()->getPathname()
    );

    foreach (pdfPagesWithFooters($bytes) as $page) {
        expect($page['footer'])->toBeNull();
    }
});

/*
 * Bug: scene breaks appeared to start on a fresh page, with a blank page before
 * them. Root cause (proven by bisection): `.scene-break` carried
 * `page-break-before: avoid; page-break-after: avoid`, which mPDF mishandles —
 * when a "* * *" lands near a page boundary it ejects a blank page and pushes the
 * scene break to the top of the next page. The fix removes those declarations.
 */

test('scene break css does not use page-break avoid rules that make mPDF insert blank pages', function (string $templateClass) {
    $css = (new $templateClass)->sceneBreakCss();

    expect($css)
        ->not->toContain('page-break-before: avoid')
        ->not->toContain('page-break-after: avoid');
})->with([
    ClassicTemplate::class,
    ModernTemplate::class,
    ElegantTemplate::class,
]);

test('a multi-scene chapter keeps scenes in one section separated by inline scene breaks', function () {
    $book = Book::factory()->create(['title' => 'Inline Book', 'author' => 'Author']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Opening']);
    Scene::factory()->for($chapter)->create(['content' => '<p>First scene.</p>', 'sort_order' => 1]);
    Scene::factory()->for($chapter)->create(['content' => '<p>Second scene.</p>', 'sort_order' => 2]);
    Scene::factory()->for($chapter)->create(['content' => '<p>Third scene.</p>', 'sort_order' => 3]);

    $html = renderPagedPdfHtml($book);
    $body = substr($html, strpos($html, '<body>'));

    // One chapter section, two inline scene breaks, no forced page break in the body.
    expect(substr_count($body, 'class="chapter-section"'))->toBe(1);
    expect(substr_count($body, 'scene-break--asterisks'))->toBe(2);
    expect($body)->not->toContain('page-break');
});

/**
 * Render the paged PDF HTML through the real export pipeline so that
 * prologue/epilogue chapters are excluded from the body the same way a
 * production export does.
 *
 * @param  array<string, mixed>  $opts
 */
function renderExportedPdfHtml(Book $book, array $opts): string
{
    $chapters = ExportService::resolveChapters($book, array_merge(['scope' => 'full'], $opts));

    $options = ExportOptions::fromArray(array_merge([
        'chapter_heading' => 'full',
        'show_page_numbers' => true,
        'trim_size' => '6x9',
        'font_size' => 11,
    ], $opts));

    return (new PdfExporter(new ContentPreparer, new FontService, new ClassicTemplate))
        ->renderHtml($book, $chapters, $options, false);
}

test('prologue and epilogue show only the localized label, not the chapter title (German)', function () {
    $book = Book::factory()->create(['title' => 'Deutsches Buch', 'author' => 'Autor', 'language' => 'de']);
    $storyline = Storyline::factory()->for($book)->create();

    $prologue = Chapter::factory()->for($book)->for($storyline)->create([
        'title' => 'Wie alles begann',
        'is_prologue' => true,
        'reader_order' => 1,
    ]);
    Scene::factory()->for($prologue)->create(['content' => '<p>Der Anfang.</p>', 'sort_order' => 1]);

    $body = Chapter::factory()->for($book)->for($storyline)->create([
        'title' => 'Kapitel Eins',
        'reader_order' => 2,
    ]);
    Scene::factory()->for($body)->create(['content' => '<p>Die Mitte.</p>', 'sort_order' => 1]);

    $epilogue = Chapter::factory()->for($book)->for($storyline)->create([
        'title' => 'Danach',
        'is_epilogue' => true,
        'reader_order' => 3,
    ]);
    Scene::factory()->for($epilogue)->create(['content' => '<p>Das Ende.</p>', 'sort_order' => 1]);

    $html = renderExportedPdfHtml($book, [
        'front_matter' => ['prologue'],
        'back_matter' => ['epilogue'],
    ]);

    expect($html)
        ->toContain('<p class="chapter-label">Prolog</p>')
        ->toContain('<p class="chapter-label">Epilog</p>')
        // The chapter titles must not appear — neither number nor title
        ->not->toContain('Wie alles begann')
        ->not->toContain('Danach')
        ->not->toContain('>Prologue<')
        ->not->toContain('>Epilogue<');
});

test('prologue and epilogue render the English label by default', function () {
    $book = Book::factory()->create(['title' => 'English Book', 'author' => 'Author', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();

    $prologue = Chapter::factory()->for($book)->for($storyline)->create([
        'title' => 'Before',
        'is_prologue' => true,
        'reader_order' => 1,
    ]);
    Scene::factory()->for($prologue)->create(['content' => '<p>The start.</p>', 'sort_order' => 1]);

    $epilogue = Chapter::factory()->for($book)->for($storyline)->create([
        'title' => 'After',
        'is_epilogue' => true,
        'reader_order' => 2,
    ]);
    Scene::factory()->for($epilogue)->create(['content' => '<p>The end.</p>', 'sort_order' => 1]);

    $html = renderExportedPdfHtml($book, [
        'front_matter' => ['prologue'],
        'back_matter' => ['epilogue'],
    ]);

    expect($html)
        ->toContain('<p class="chapter-label">Prologue</p>')
        ->toContain('<p class="chapter-label">Epilogue</p>')
        ->not->toContain('>Before<')
        ->not->toContain('>After<');
});

/*
 * Running headers (book title on verso, chapter title on recto) were removed from
 * the paged PDF. Guard against any of the header machinery creeping back in.
 */
test('paged pdf renders no running header with book or chapter titles', function () {
    $book = Book::factory()->create(['title' => 'My Running Header Book', 'author' => 'Author', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'A Recto Chapter Title', 'reader_order' => 1]);
    Scene::factory()->for($chapter)->create(['content' => '<p>Body.</p>', 'sort_order' => 1]);

    $html = renderPagedPdfHtml($book);

    expect($html)
        ->not->toContain('name="bookhdr"')
        ->not->toContain('name="chaphdr')
        ->not->toContain('header-name')
        ->not->toContain('@top-left')
        ->not->toContain('@top-right')
        ->not->toContain('<htmlpageheader');
});

test('hyphenation language falls back to English only for unsupported locales', function () {
    expect(PdfExporter::hyphenationLanguage('de'))->toBe('de')
        ->and(PdfExporter::hyphenationLanguage('es'))->toBe('es')
        ->and(PdfExporter::hyphenationLanguage('en'))->toBe('en')
        ->and(PdfExporter::hyphenationLanguage('pt'))->toBe('en')
        ->and(PdfExporter::hyphenationLanguage(''))->toBe('en');
});

/*
 * Regression: PdfExporter never set mPDF's SHYlang, so German text was
 * hyphenated with English patterns (e.g. "Geschwindigkeitsbe-grenzung").
 * It also must not over-tighten SHYcharmin — a value of 5 silently disables
 * hyphenation in this mPDF version. This proves a dense German paragraph in a
 * narrow column actually breaks words (and therefore loads the de dictionary).
 */
test('german text is hyphenated in a narrow column', function () {
    if (! trim((string) shell_exec('command -v pdftotext'))) {
        $this->markTestSkipped('pdftotext not available');
    }

    $book = Book::factory()->create(['title' => 'Hyphen', 'author' => 'Autor', 'language' => 'de']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Kapitel']);
    $para = '<p>'.trim(str_repeat('Die Geschwindigkeitsbegrenzung der Bibliotheksverwaltung '
        .'erforderte umfangreiche Dokumentationspflichten. ', 14)).'</p>';
    Scene::factory()->for($chapter)->create(['content' => $para, 'sort_order' => 1]);

    // Pocket (5x8) — a narrow measure that forces line breaks inside long compounds.
    $bytes = file_get_contents(
        (new ExportService)->export($book, ['format' => 'pdf', 'scope' => 'full', 'trim_size' => '5x8'])
            ->getFile()->getPathname()
    );

    $pdf = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
    $txt = $pdf.'.txt';
    file_put_contents($pdf, $bytes);
    exec('pdftotext -layout '.escapeshellarg($pdf).' '.escapeshellarg($txt));
    $text = (string) @file_get_contents($txt);
    @unlink($pdf);
    @unlink($txt);

    preg_match_all('/\S+-\s*\n/u', $text, $hyphenated);
    expect(count($hyphenated[0]))->toBeGreaterThan(0);
});

test('a long multi-scene chapter renders with no blank interior pages', function () {
    if (! trim((string) shell_exec('command -v pdftotext'))) {
        $this->markTestSkipped('pdftotext not available');
    }

    // Long scenes so the "* * *" breaks fall at varied positions near page edges.
    $para = '<p>'.trim(str_repeat('Das Licht fiel schräg durch die Jalousien und legte '
        .'helle Streifen über den abgewetzten Tisch, während draußen die Stadt erwachte. ', 8)).'</p>';
    $sceneHtml = str_repeat($para, 6);

    $book = Book::factory()->create(['title' => 'Long Book', 'author' => 'Author', 'language' => 'de']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Kapitel']);
    foreach (range(1, 5) as $order) {
        Scene::factory()->for($chapter)->create(['content' => $sceneHtml, 'sort_order' => $order]);
    }

    $response = (new ExportService)->export($book, [
        'format' => 'pdf',
        'scope' => 'full',
        'show_page_numbers' => true,
        'trim_size' => '6x9',
    ]);
    $bytes = file_get_contents($response->getFile()->getPathname());

    expect(interiorBlankPages($bytes))->toBe([]);
});
