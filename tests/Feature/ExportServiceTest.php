<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Scene;
use App\Models\Storyline;
use App\Services\Export\ExportService;

beforeEach(function () {
    $this->service = new ExportService;
});

// === DOCX Tests ===

test('exports book as docx', function () {
    $book = Book::factory()->create(['title' => 'Test Book']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Chapter One']);
    Scene::factory()->for($chapter)->create(['content' => '<p>Once upon a time.</p>', 'sort_order' => 1]);

    $response = $this->service->export($book, ['format' => 'docx', 'scope' => 'full']);

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('content-disposition'))->toContain('test-book.docx');
});

// === TXT Tests ===

test('exports book as txt', function () {
    $book = Book::factory()->create(['title' => 'My Novel']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'First Chapter']);
    Scene::factory()->for($chapter)->create(['content' => '<p>Hello world.</p>', 'sort_order' => 1]);

    $response = $this->service->export($book, ['format' => 'txt', 'scope' => 'full']);

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('content-disposition'))->toContain('my-novel.txt');

    $file = $response->getFile();
    $content = file_get_contents($file->getPathname());
    expect($content)->toContain('First Chapter');
    expect($content)->toContain('Hello world.');
});

// === Scope Tests ===

test('exports single chapter scope', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapterA = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Ch A', 'reader_order' => 1]);
    $chapterB = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Ch B', 'reader_order' => 2]);
    Scene::factory()->for($chapterA)->create(['content' => '<p>Alpha content.</p>', 'sort_order' => 1]);
    Scene::factory()->for($chapterB)->create(['content' => '<p>Beta content.</p>', 'sort_order' => 1]);

    $response = $this->service->export($book, [
        'format' => 'txt',
        'scope' => 'chapter',
        'chapter_id' => $chapterA->id,
    ]);

    $content = file_get_contents($response->getFile()->getPathname());
    expect($content)->toContain('Ch A');
    expect($content)->toContain('Alpha content.');
    expect($content)->not->toContain('Ch B');
    expect($content)->not->toContain('Beta content.');
});

test('exports single storyline scope', function () {
    $book = Book::factory()->create();
    $storyA = Storyline::factory()->for($book)->create(['name' => 'Main']);
    $storyB = Storyline::factory()->for($book)->create(['name' => 'Subplot']);
    $chapterA = Chapter::factory()->for($book)->for($storyA)->create(['title' => 'Main Ch']);
    $chapterB = Chapter::factory()->for($book)->for($storyB)->create(['title' => 'Sub Ch']);
    Scene::factory()->for($chapterA)->create(['content' => '<p>Main story.</p>', 'sort_order' => 1]);
    Scene::factory()->for($chapterB)->create(['content' => '<p>Subplot content.</p>', 'sort_order' => 1]);

    $response = $this->service->export($book, [
        'format' => 'txt',
        'scope' => 'storyline',
        'storyline_id' => $storyA->id,
    ]);

    $content = file_get_contents($response->getFile()->getPathname());
    expect($content)->toContain('Main Ch');
    expect($content)->not->toContain('Sub Ch');
});

// === Options Tests ===

test('respects include_chapter_titles option', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Hidden Title']);
    Scene::factory()->for($chapter)->create(['content' => '<p>Body text.</p>', 'sort_order' => 1]);

    $response = $this->service->export($book, [
        'format' => 'txt',
        'scope' => 'full',
        'include_chapter_titles' => false,
    ]);

    $content = file_get_contents($response->getFile()->getPathname());
    expect($content)->not->toContain('Hidden Title');
    expect($content)->toContain('Body text.');
});

test('handles scene breaks between scenes', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Multi Scene']);
    Scene::factory()->for($chapter)->create(['content' => '<p>Scene one.</p>', 'sort_order' => 1]);
    Scene::factory()->for($chapter)->create(['content' => '<p>Scene two.</p>', 'sort_order' => 2]);

    $response = $this->service->export($book, ['format' => 'txt', 'scope' => 'full']);

    $content = file_get_contents($response->getFile()->getPathname());
    expect($content)->toContain('Scene one.');
    expect($content)->toContain('* * *');
    expect($content)->toContain('Scene two.');
});

test('exports empty book without error', function () {
    $book = Book::factory()->create();

    $response = $this->service->export($book, ['format' => 'txt', 'scope' => 'full']);

    expect($response->getStatusCode())->toBe(200);
});

// === chapter_ids Ordering Tests ===

test('chapter_ids controls export order', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $ch1 = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'First', 'reader_order' => 1]);
    $ch2 = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Second', 'reader_order' => 2]);
    $ch3 = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Third', 'reader_order' => 3]);
    Scene::factory()->for($ch1)->create(['content' => '<p>Content 1.</p>', 'sort_order' => 1]);
    Scene::factory()->for($ch2)->create(['content' => '<p>Content 2.</p>', 'sort_order' => 1]);
    Scene::factory()->for($ch3)->create(['content' => '<p>Content 3.</p>', 'sort_order' => 1]);

    // Export in reverse order
    $response = $this->service->export($book, [
        'format' => 'txt',
        'chapter_ids' => [$ch3->id, $ch1->id, $ch2->id],
    ]);

    $content = file_get_contents($response->getFile()->getPathname());
    $posThird = strpos($content, 'Third');
    $posFirst = strpos($content, 'First');
    $posSecond = strpos($content, 'Second');

    expect($posThird)->toBeLessThan($posFirst);
    expect($posFirst)->toBeLessThan($posSecond);
});

test('chapter_ids subset only exports selected chapters', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $ch1 = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Included']);
    $ch2 = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Excluded']);
    Scene::factory()->for($ch1)->create(['content' => '<p>Yes.</p>', 'sort_order' => 1]);
    Scene::factory()->for($ch2)->create(['content' => '<p>No.</p>', 'sort_order' => 1]);

    $response = $this->service->export($book, [
        'format' => 'txt',
        'chapter_ids' => [$ch1->id],
    ]);

    $content = file_get_contents($response->getFile()->getPathname());
    expect($content)->toContain('Included');
    expect($content)->not->toContain('Excluded');
});

// === EPUB Tests ===

test('exports book as epub', function () {
    $book = Book::factory()->create(['title' => 'Epic Novel', 'author' => 'Jane Doe', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'The Beginning']);
    Scene::factory()->for($chapter)->create(['content' => '<p>It was a dark and stormy night.</p>', 'sort_order' => 1]);

    $response = $this->service->export($book, ['format' => 'epub', 'scope' => 'full']);

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('content-disposition'))->toContain('epic-novel.epub');

    // Verify ZIP structure
    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());

    expect($zip->locateName('mimetype'))->not->toBeFalse();
    expect($zip->locateName('META-INF/container.xml'))->not->toBeFalse();
    expect($zip->locateName('OEBPS/content.opf'))->not->toBeFalse();
    expect($zip->locateName('OEBPS/toc.ncx'))->not->toBeFalse();
    expect($zip->locateName('OEBPS/Styles/stylesheet.css'))->not->toBeFalse();
    expect($zip->locateName('OEBPS/Text/chapter-001.xhtml'))->not->toBeFalse();

    // Verify mimetype content
    expect($zip->getFromName('mimetype'))->toBe('application/epub+zip');

    // Verify chapter content
    $chapterXhtml = $zip->getFromName('OEBPS/Text/chapter-001.xhtml');
    expect($chapterXhtml)->toContain('The Beginning');
    expect($chapterXhtml)->toContain('dark and stormy night');

    // Verify OPF metadata
    $opf = $zip->getFromName('OEBPS/content.opf');
    expect($opf)->toContain('Epic Novel');
    expect($opf)->toContain('Jane Doe');
    expect($opf)->toContain('<dc:language>en</dc:language>');

    $zip->close();
});

test('epub includes table of contents when requested', function () {
    $book = Book::factory()->create(['title' => 'TOC Book']);
    $storyline = Storyline::factory()->for($book)->create();
    $ch1 = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Chapter One', 'reader_order' => 1]);
    $ch2 = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Chapter Two', 'reader_order' => 2]);
    Scene::factory()->for($ch1)->create(['content' => '<p>First.</p>', 'sort_order' => 1]);
    Scene::factory()->for($ch2)->create(['content' => '<p>Second.</p>', 'sort_order' => 1]);

    $response = $this->service->export($book, [
        'format' => 'epub',
        'scope' => 'full',
        'include_table_of_contents' => true,
    ]);

    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());

    expect($zip->locateName('OEBPS/toc.xhtml'))->not->toBeFalse();

    $tocXhtml = $zip->getFromName('OEBPS/toc.xhtml');
    expect($tocXhtml)->toContain('Chapter One');
    expect($tocXhtml)->toContain('Chapter Two');

    // Verify TOC is in manifest with nav property
    $opf = $zip->getFromName('OEBPS/content.opf');
    expect($opf)->toContain('properties="nav"');

    $zip->close();
});

test('epub spine matches chapter order', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $ch1 = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Alpha', 'reader_order' => 1]);
    $ch2 = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Beta', 'reader_order' => 2]);
    Scene::factory()->for($ch1)->create(['content' => '<p>A.</p>', 'sort_order' => 1]);
    Scene::factory()->for($ch2)->create(['content' => '<p>B.</p>', 'sort_order' => 1]);

    $response = $this->service->export($book, ['format' => 'epub', 'scope' => 'full']);

    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());

    $opf = $zip->getFromName('OEBPS/content.opf');
    $pos001 = strpos($opf, 'idref="chapter-001"');
    $pos002 = strpos($opf, 'idref="chapter-002"');
    expect($pos001)->toBeLessThan($pos002);

    $zip->close();
});

test('epub embeds fonts when available', function () {
    $book = Book::factory()->create(['title' => 'Font Test']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Ch 1']);
    Scene::factory()->for($chapter)->create(['content' => '<p>Test.</p>', 'sort_order' => 1]);

    $response = $this->service->export($book, ['format' => 'epub', 'scope' => 'full']);

    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());

    // Fonts should be embedded since we have them in resources/fonts
    expect($zip->locateName('OEBPS/Fonts/Literata.ttf'))->not->toBeFalse();
    expect($zip->locateName('OEBPS/Fonts/Literata-Italic.ttf'))->not->toBeFalse();

    // CSS should reference fonts
    $css = $zip->getFromName('OEBPS/Styles/stylesheet.css');
    expect($css)->toContain('@font-face');
    expect($css)->toContain('Literata');

    $zip->close();
});

test('epub chapter_ids ordering', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $ch1 = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'First', 'reader_order' => 1]);
    $ch2 = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Second', 'reader_order' => 2]);
    Scene::factory()->for($ch1)->create(['content' => '<p>1.</p>', 'sort_order' => 1]);
    Scene::factory()->for($ch2)->create(['content' => '<p>2.</p>', 'sort_order' => 1]);

    // Reverse order
    $response = $this->service->export($book, [
        'format' => 'epub',
        'chapter_ids' => [$ch2->id, $ch1->id],
    ]);

    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());

    // chapter-001 should contain "Second" (since it was first in our order)
    $ch001 = $zip->getFromName('OEBPS/Text/chapter-001.xhtml');
    $ch002 = $zip->getFromName('OEBPS/Text/chapter-002.xhtml');
    expect($ch001)->toContain('Second');
    expect($ch002)->toContain('First');

    $zip->close();
});

// === PDF Tests ===

test('exports book as pdf', function () {
    $book = Book::factory()->create(['title' => 'PDF Book']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Opening']);
    Scene::factory()->for($chapter)->create(['content' => '<p>The story begins.</p>', 'sort_order' => 1]);

    $response = $this->service->export($book, ['format' => 'pdf', 'scope' => 'full']);

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('content-disposition'))->toContain('pdf-book.pdf');

    // Verify it's a valid PDF
    $content = file_get_contents($response->getFile()->getPathname());
    expect(str_starts_with($content, '%PDF'))->toBeTrue();
});

test('pdf respects trim size', function () {
    $book = Book::factory()->create(['title' => 'Sized Book']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Ch 1']);
    Scene::factory()->for($chapter)->create(['content' => '<p>Content.</p>', 'sort_order' => 1]);

    $response = $this->service->export($book, [
        'format' => 'pdf',
        'scope' => 'full',
        'trim_size' => '5x8',
    ]);

    expect($response->getStatusCode())->toBe(200);
    $content = file_get_contents($response->getFile()->getPathname());
    expect(str_starts_with($content, '%PDF'))->toBeTrue();
});

test('pdf respects font size', function () {
    $book = Book::factory()->create(['title' => 'Font Size Book']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Ch 1']);
    Scene::factory()->for($chapter)->create(['content' => '<p>Content.</p>', 'sort_order' => 1]);

    $response = $this->service->export($book, [
        'format' => 'pdf',
        'scope' => 'full',
        'font_size' => 14,
    ]);

    expect($response->getStatusCode())->toBe(200);
    $content = file_get_contents($response->getFile()->getPathname());
    expect(str_starts_with($content, '%PDF'))->toBeTrue();
});

test('pdf chapter_ids ordering', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $ch1 = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Alpha Chapter', 'reader_order' => 1]);
    $ch2 = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Beta Chapter', 'reader_order' => 2]);
    Scene::factory()->for($ch1)->create(['content' => '<p>First content.</p>', 'sort_order' => 1]);
    Scene::factory()->for($ch2)->create(['content' => '<p>Second content.</p>', 'sort_order' => 1]);

    $response = $this->service->export($book, [
        'format' => 'pdf',
        'chapter_ids' => [$ch2->id, $ch1->id],
    ]);

    expect($response->getStatusCode())->toBe(200);
});

// === KDP Tests ===

test('exports book as kdp epub', function () {
    $book = Book::factory()->create(['title' => 'KDP Novel', 'author' => 'Author Name', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Chapter 1']);
    Scene::factory()->for($chapter)->create(['content' => '<p>KDP content.</p>', 'sort_order' => 1]);

    $response = $this->service->export($book, ['format' => 'kdp', 'scope' => 'full']);

    expect($response->getStatusCode())->toBe(200);
    // KDP uses .epub extension
    expect($response->headers->get('content-disposition'))->toContain('kdp-novel.epub');

    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());

    // KDP forces TOC
    expect($zip->locateName('OEBPS/toc.xhtml'))->not->toBeFalse();

    // Verify metadata
    $opf = $zip->getFromName('OEBPS/content.opf');
    expect($opf)->toContain('KDP Novel');
    expect($opf)->toContain('Author Name');
    expect($opf)->toContain('<dc:language>en</dc:language>');

    $zip->close();
});

test('kdp forces table of contents and chapter titles', function () {
    $book = Book::factory()->create(['title' => 'KDP Forced', 'author' => 'Writer', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'My Chapter']);
    Scene::factory()->for($chapter)->create(['content' => '<p>Text.</p>', 'sort_order' => 1]);

    // Request without TOC and without titles — KDP should force both
    $response = $this->service->export($book, [
        'format' => 'kdp',
        'scope' => 'full',
        'include_table_of_contents' => false,
        'include_chapter_titles' => false,
    ]);

    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());

    // TOC forced
    expect($zip->locateName('OEBPS/toc.xhtml'))->not->toBeFalse();

    // Chapter title forced
    $chapterXhtml = $zip->getFromName('OEBPS/Text/chapter-001.xhtml');
    expect($chapterXhtml)->toContain('My Chapter');

    $zip->close();
});

test('kdp validates required metadata', function () {
    $book = Book::factory()->create(['title' => 'No Author', 'author' => '', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Ch']);
    Scene::factory()->for($chapter)->create(['content' => '<p>T.</p>', 'sort_order' => 1]);

    expect(fn () => $this->service->export($book, ['format' => 'kdp', 'scope' => 'full']))
        ->toThrow(InvalidArgumentException::class, 'author');
});

test('kdp validates language is required', function () {
    $book = Book::factory()->create(['title' => 'No Lang', 'author' => 'Writer', 'language' => '']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Ch']);
    Scene::factory()->for($chapter)->create(['content' => '<p>T.</p>', 'sort_order' => 1]);

    expect(fn () => $this->service->export($book, ['format' => 'kdp', 'scope' => 'full']))
        ->toThrow(InvalidArgumentException::class, 'language');
});

// === Validation Tests ===

test('export endpoint accepts new formats', function () {
    $book = Book::factory()->create(['author' => 'Test', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Text.</p>', 'sort_order' => 1]);

    foreach (['docx', 'txt', 'epub', 'pdf', 'kdp'] as $format) {
        $this->postJson(route('books.settings.export.run', $book), [
            'format' => $format,
            'scope' => 'full',
        ])->assertOk();
    }
});

test('export endpoint rejects invalid format', function () {
    $book = Book::factory()->create();

    $this->postJson(route('books.settings.export.run', $book), [
        'format' => 'html',
        'scope' => 'full',
    ])->assertUnprocessable();
});

test('export endpoint requires valid scope', function () {
    $book = Book::factory()->create();

    $this->postJson(route('books.settings.export.run', $book), [
        'format' => 'docx',
        'scope' => 'invalid',
    ])->assertUnprocessable();
});

test('export endpoint accepts chapter_ids', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Text.</p>', 'sort_order' => 1]);

    $this->postJson(route('books.settings.export.run', $book), [
        'format' => 'txt',
        'chapter_ids' => [$chapter->id],
    ])->assertOk();
});

test('export endpoint validates trim_size', function () {
    $book = Book::factory()->create();

    $this->postJson(route('books.settings.export.run', $book), [
        'format' => 'pdf',
        'scope' => 'full',
        'trim_size' => 'invalid',
    ])->assertUnprocessable();
});

test('export endpoint validates font_size', function () {
    $book = Book::factory()->create();

    $this->postJson(route('books.settings.export.run', $book), [
        'format' => 'pdf',
        'scope' => 'full',
        'font_size' => 99,
    ])->assertUnprocessable();
});

// === Backward Compatibility ===

test('backward compatible with legacy scope-based request', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Legacy Chapter']);
    Scene::factory()->for($chapter)->create(['content' => '<p>Legacy content.</p>', 'sort_order' => 1]);

    // Old-style request still works
    $this->postJson(route('books.settings.export.run', $book), [
        'format' => 'docx',
        'scope' => 'full',
        'include_chapter_titles' => true,
    ])->assertOk();
});
