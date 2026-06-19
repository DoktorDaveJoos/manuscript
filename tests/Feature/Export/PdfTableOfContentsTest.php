<?php

use App\Models\Book;
use App\Models\Chapter;
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
 * @param  array<string, mixed>  $options
 * @param  array<int, string>  $tocFolios
 */
function renderPdfTableOfContentsHtml(Book $book, array $options = [], array $tocFolios = [], bool $tocProbe = false): string
{
    $resolvedOptions = array_merge([
        'scope' => 'full',
        'chapter_heading' => 'full',
        'show_page_numbers' => true,
        'trim_size' => '6x9',
        'font_size' => 11,
        'include_cover' => false,
    ], $options);

    $chapters = ExportService::resolveChapters($book, $resolvedOptions);

    return (new PdfExporter(new ContentPreparer, new FontService, new ClassicTemplate))
        ->renderHtml($book, $chapters, ExportOptions::fromArray($resolvedOptions), false, $tocFolios, $tocProbe);
}

/**
 * @param  array<string, mixed>  $options
 */
function exportPdfTableOfContentsBytes(Book $book, array $options = []): string
{
    $path = (new ExportService)->exportToPath($book, array_merge([
        'format' => 'pdf',
        'scope' => 'full',
        'chapter_heading' => 'full',
        'show_page_numbers' => true,
        'trim_size' => '6x9',
        'include_cover' => false,
    ], $options));

    try {
        return (string) file_get_contents($path);
    } finally {
        @unlink($path);
    }
}

/**
 * @return array<int, array{text: string, footer: ?string}>
 */
function pdfTableOfContentsPages(string $pdfBytes): array
{
    $pdfPath = tempnam(sys_get_temp_dir(), 'pdf-toc').'.pdf';
    $textPath = $pdfPath.'.txt';
    file_put_contents($pdfPath, $pdfBytes);
    exec('pdftotext -layout '.escapeshellarg($pdfPath).' '.escapeshellarg($textPath).' 2>/dev/null');
    $pages = explode("\f", (string) @file_get_contents($textPath));
    @unlink($pdfPath);
    @unlink($textPath);

    return array_map(function (string $page): array {
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $page)),
            fn (string $line): bool => $line !== '',
        ));
        $lastLine = end($lines) ?: '';

        return [
            'text' => implode(' ', $lines),
            'footer' => preg_match('/^\d+$/', $lastLine) ? $lastLine : null,
        ];
    }, $pages);
}

/**
 * 1-based page numbers of fully blank interior pages (the trailing page mPDF can
 * leave empty is ignored).
 *
 * @param  array<int, array{text: string, footer: ?string}>  $pages
 * @return array<int, int>
 */
function blankInteriorTocPages(array $pages): array
{
    $blank = [];
    $last = count($pages) - 1;
    foreach ($pages as $i => $page) {
        if ($i !== $last && trim($page['text']) === '') {
            $blank[] = $i + 1;
        }
    }

    return $blank;
}

/**
 * Export a book (built with three chapters and an optional prologue) and split
 * the result into pages.
 *
 * @param  array<int, string>  $frontMatter
 * @return array<int, array{text: string, footer: ?string}>
 */
function tocLayoutPages(array $frontMatter, bool $withPrologue = false): array
{
    $book = Book::factory()->create(['title' => 'Blank Page Guard', 'author' => 'Author', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();

    if ($withPrologue) {
        $prologue = Chapter::factory()->for($book)->for($storyline)->create([
            'title' => 'Before', 'is_prologue' => true, 'reader_order' => 1,
        ]);
        Scene::factory()->for($prologue)->create(['content' => '<p>PrologueBodyMarker.</p>', 'sort_order' => 1]);
    }

    foreach (['First Chapter', 'Second Chapter', 'Third Chapter'] as $index => $title) {
        $chapter = Chapter::factory()->for($book)->for($storyline)->create([
            'title' => $title, 'reader_order' => $index + ($withPrologue ? 2 : 1),
        ]);
        Scene::factory()->for($chapter)->create([
            'content' => '<p>'.str_replace(' ', '', $title).'BodyMarker.</p>', 'sort_order' => 1,
        ]);
    }

    return pdfTableOfContentsPages(exportPdfTableOfContentsBytes($book, ['front_matter' => $frontMatter]));
}

test('the body starts immediately after the table of contents with no blank page between', function (array $frontMatter, bool $withPrologue, string $firstMarker) {
    $pages = tocLayoutPages($frontMatter, $withPrologue);

    $tocIndex = collect($pages)->search(fn (array $page): bool => str_contains($page['text'], 'Table of Contents'));
    $bodyIndex = collect($pages)->search(fn (array $page): bool => str_contains($page['text'], $firstMarker));

    expect($tocIndex)->not->toBeFalse()
        ->and($bodyIndex)->not->toBeFalse()
        ->and($bodyIndex)->toBe($tocIndex + 1);
})->with([
    'toc then chapters' => [['title-page', 'toc'], false, 'FirstChapterBodyMarker'],
    'toc then prologue' => [['title-page', 'toc', 'prologue'], true, 'PrologueBodyMarker'],
])->skip(
    fn (): bool => ! trim((string) shell_exec('command -v pdftotext')),
    'pdftotext not available',
);

test('a table of contents introduces no blank interior pages', function (array $frontMatter, bool $withPrologue) {
    expect(blankInteriorTocPages(tocLayoutPages($frontMatter, $withPrologue)))->toBe([]);
})->with([
    'toc only' => [['toc'], false],
    'title then toc' => [['title-page', 'toc'], false],
    'toc then prologue' => [['title-page', 'toc', 'prologue'], true],
])->skip(
    fn (): bool => ! trim((string) shell_exec('command -v pdftotext')),
    'pdftotext not available',
);

test('pdf renders a static table of contents and collects folios via a probe pass', function () {
    $book = Book::factory()->create([
        'title' => 'Libro',
        'author' => 'Autora',
        'language' => 'es',
    ]);
    $storyline = Storyline::factory()->for($book)->create();

    foreach (['La "Llegada"', 'El regreso'] as $index => $title) {
        $chapter = Chapter::factory()->for($book)->for($storyline)->create([
            'title' => $title,
            'reader_order' => $index + 1,
        ]);
        Scene::factory()->for($chapter)->create([
            'content' => "<p>Marcador {$index}.</p>",
            'sort_order' => 1,
        ]);
    }

    // Final pass: a plain TOC page with the localized title, chapter rows and
    // dotted-leader folios — and none of mPDF's native (recto-forcing) TOC tags.
    $html = renderPdfTableOfContentsHtml($book, ['front_matter' => ['toc']], ['1', '12']);

    expect($html)
        ->toContain('<p class="toc-title">Índice</p>')
        ->toContain('<span class="mpdf_toc_t_level_0">La &quot;Llegada&quot;</span>')
        ->toContain('<span class="mpdf_toc_t_level_0">El regreso</span>')
        ->toContain('<span class="mpdf_toc_p_level_0">1</span>')
        ->toContain('<span class="mpdf_toc_p_level_0">12</span>')
        ->toContain('<dottab')
        ->not->toContain('<tocpagebreak')
        ->not->toContain('<tocentry ')
        ->not->toContain('class="toc-entry"')
        ->not->toContain('href="#chapter-');

    // Probe pass: emits the <tocentry> markers used to compute folios, and skips
    // the visible TOC page.
    $probe = renderPdfTableOfContentsHtml($book, ['front_matter' => ['toc']], [], true);

    expect($probe)
        ->toContain('<tocentry content="La &quot;Llegada&quot;" level="0" />')
        ->toContain('<tocentry content="El regreso" level="0" />')
        ->not->toContain('<p class="toc-title">');
    expect(substr_count($probe, '<tocentry '))->toBe(2);

    // No TOC requested: neither markers nor a TOC page.
    expect(renderPdfTableOfContentsHtml($book))
        ->not->toContain('<tocentry ')
        ->not->toContain('<p class="toc-title">');

    // The standalone option renders the same static TOC page.
    expect(renderPdfTableOfContentsHtml($book, ['include_table_of_contents' => true], ['1', '12']))
        ->toContain('<p class="toc-title">Índice</p>')
        ->not->toContain('<tocpagebreak');
});

test('all pdf templates style the table of contents', function (string $templateClass) {
    $css = (new $templateClass)->pdfCss(11);

    expect($css)
        ->toContain('div.mpdf_toc')
        ->toContain('span.mpdf_toc_t_level_0')
        ->toContain('span.mpdf_toc_p_level_0')
        ->not->toContain('.toc-entry');
})->with([
    ClassicTemplate::class,
    ModernTemplate::class,
    ElegantTemplate::class,
]);

test('pdf table of contents folios match chapter page numbers', function (bool $withPrologue) {
    $book = Book::factory()->create([
        'title' => 'Folio Reference',
        'author' => 'Author',
        'language' => 'en',
    ]);
    $storyline = Storyline::factory()->for($book)->create();

    if ($withPrologue) {
        $prologue = Chapter::factory()->for($book)->for($storyline)->create([
            'title' => 'Before',
            'is_prologue' => true,
            'reader_order' => 1,
        ]);
        Scene::factory()->for($prologue)->create([
            'content' => '<p>PrologueBodyMarker.</p>',
            'sort_order' => 1,
        ]);
    }

    foreach (['First Chapter', 'Second Chapter'] as $index => $title) {
        $chapter = Chapter::factory()->for($book)->for($storyline)->create([
            'title' => $title,
            'reader_order' => $index + ($withPrologue ? 2 : 1),
        ]);
        Scene::factory()->for($chapter)->create([
            'content' => '<p>'.str_replace(' ', '', $title).'BodyMarker.</p>',
            'sort_order' => 1,
        ]);
    }

    $frontMatter = $withPrologue
        ? ['title-page', 'toc', 'prologue']
        : ['title-page', 'toc'];
    $pages = pdfTableOfContentsPages(exportPdfTableOfContentsBytes($book, [
        'front_matter' => $frontMatter,
    ]));

    $tocPage = collect($pages)->first(
        fn (array $page): bool => str_contains($page['text'], 'Table of Contents'),
    );
    $prologuePage = collect($pages)->first(
        fn (array $page): bool => str_contains($page['text'], 'PrologueBodyMarker'),
    );
    $firstChapterPage = collect($pages)->first(
        fn (array $page): bool => str_contains($page['text'], 'FirstChapterBodyMarker'),
    );

    expect($tocPage)->not->toBeNull()
        ->and($tocPage['text'])
        ->toContain('First Chapter')
        ->toContain('Second Chapter')
        ->toMatch('/\.{3,}/')
        ->and($tocPage['footer'])->toBeNull()
        ->and($firstChapterPage)->not->toBeNull();

    preg_match('/First Chapter[.\s]+(\d+)/u', $tocPage['text'], $matches);

    expect($matches)->not->toBeEmpty()
        ->and($matches[1])->toBe($firstChapterPage['footer']);

    if ($withPrologue) {
        expect($prologuePage['footer'])->toBe('1')
            ->and($firstChapterPage['footer'])->toBe('2');
    } else {
        expect($firstChapterPage['footer'])->toBe('1');
    }
})->with([
    'without prologue' => false,
    'with prologue' => true,
])->skip(
    fn (): bool => ! trim((string) shell_exec('command -v pdftotext')),
    'pdftotext not available',
);

test('pdf matter labels use the book language', function (
    string $locale,
    string $tocTitle,
    string $acknowledgments,
    string $aboutAuthor,
    string $alsoBy,
    string $rights,
) {
    $book = Book::factory()->create([
        'title' => 'Localized Book',
        'author' => 'Ada',
        'language' => $locale,
    ]);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create([
        'title' => 'Opening',
        'reader_order' => 1,
    ]);
    Scene::factory()->for($chapter)->create([
        'content' => '<p>Body.</p>',
        'sort_order' => 1,
    ]);

    $html = renderPdfTableOfContentsHtml($book, [
        'front_matter' => ['copyright', 'toc'],
        'back_matter' => ['acknowledgments', 'about-author', 'also-by'],
        'acknowledgment_text' => 'Thanks.',
        'about_author_text' => 'Biography.',
        'also_by_text' => 'Another book.',
    ]);

    expect($html)
        ->toContain("<p class=\"toc-title\">{$tocTitle}</p>")
        ->toContain("<p class=\"matter-title\">{$acknowledgments}</p>")
        ->toContain("<p class=\"matter-title\">{$aboutAuthor}</p>")
        ->toContain("<p class=\"matter-title\">{$alsoBy}</p>")
        ->toContain("<p class=\"copyright-text\">{$rights}</p>");
})->with([
    'English' => ['en', 'Table of Contents', 'Acknowledgments', 'About the Author', 'Also By Ada', 'All rights reserved.'],
    'German' => ['de', 'Inhaltsverzeichnis', 'Danksagung', 'Über den Autor', 'Weitere Werke von Ada', 'Alle Rechte vorbehalten.'],
    'Spanish' => ['es', 'Índice', 'Agradecimientos', 'Sobre el autor', 'Otras obras de Ada', 'Todos los derechos reservados.'],
]);
