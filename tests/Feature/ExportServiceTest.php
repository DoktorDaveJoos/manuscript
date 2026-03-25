<?php

use App\Contracts\ExportTemplate;
use App\Models\AppSetting;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\License;
use App\Models\Scene;
use App\Models\Storyline;
use App\Services\Export\ContentPreparer;
use App\Services\Export\ExportOptions;
use App\Services\Export\ExportService;
use App\Services\Export\Templates\ClassicTemplate;
use Native\Desktop\Facades\System;

beforeEach(function () {
    License::factory()->create();
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
    expect($zip->locateName('OEBPS/Fonts/CrimsonPro-Regular.ttf'))->not->toBeFalse();
    expect($zip->locateName('OEBPS/Fonts/CrimsonPro-Italic.ttf'))->not->toBeFalse();

    // CSS should reference fonts
    $css = $zip->getFromName('OEBPS/Styles/stylesheet.css');
    expect($css)->toContain('@font-face');
    expect($css)->toContain('Crimson Pro');

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

// === PDF Tests (Chromium-based via NativePHP System::printToPDF) ===

test('exports book as pdf', function () {
    $book = Book::factory()->create(['title' => 'PDF Book']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Opening']);
    Scene::factory()->for($chapter)->create(['content' => '<p>The story begins.</p>', 'sort_order' => 1]);

    // mPDF generates PDFs in PHP — no mocking needed

    $response = $this->service->export($book, ['format' => 'pdf', 'scope' => 'full']);

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('content-disposition'))->toContain('pdf-book.pdf');

    $content = file_get_contents($response->getFile()->getPathname());
    expect($content)->toStartWith('%PDF');
});

test('pdf respects trim size', function () {
    $book = Book::factory()->create(['title' => 'Sized Book']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Ch 1']);
    Scene::factory()->for($chapter)->create(['content' => '<p>Content.</p>', 'sort_order' => 1]);

    // mPDF generates PDFs in PHP — no mocking needed

    $response = $this->service->export($book, [
        'format' => 'pdf',
        'scope' => 'full',
        'trim_size' => '5x8',
    ]);

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('content-disposition'))->toContain('.pdf');
});

test('pdf respects font size', function () {
    $book = Book::factory()->create(['title' => 'Font Size Book']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Ch 1']);
    Scene::factory()->for($chapter)->create(['content' => '<p>Content.</p>', 'sort_order' => 1]);

    // mPDF generates PDFs in PHP — no mocking needed

    $response = $this->service->export($book, [
        'format' => 'pdf',
        'scope' => 'full',
        'font_size' => 14,
    ]);

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('content-disposition'))->toContain('.pdf');
});

test('pdf chapter_ids ordering', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $ch1 = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Alpha Chapter', 'reader_order' => 1]);
    $ch2 = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Beta Chapter', 'reader_order' => 2]);
    Scene::factory()->for($ch1)->create(['content' => '<p>First content.</p>', 'sort_order' => 1]);
    Scene::factory()->for($ch2)->create(['content' => '<p>Second content.</p>', 'sort_order' => 1]);

    // mPDF generates PDFs in PHP — no mocking needed

    $response = $this->service->export($book, [
        'format' => 'pdf',
        'chapter_ids' => [$ch2->id, $ch1->id],
    ]);

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('content-disposition'))->toContain('.pdf');
});

test('pdf exports multiple chapters successfully with alternating headers', function () {
    $book = Book::factory()->create(['title' => 'Multi Chapter PDF']);
    $storyline = Storyline::factory()->for($book)->create();
    $ch1 = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Dawn', 'reader_order' => 1]);
    $ch2 = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Dusk', 'reader_order' => 2]);
    $ch3 = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Midnight', 'reader_order' => 3]);
    Scene::factory()->for($ch1)->create(['content' => '<p>Morning light.</p>', 'sort_order' => 1]);
    Scene::factory()->for($ch2)->create(['content' => '<p>Evening glow.</p>', 'sort_order' => 1]);
    Scene::factory()->for($ch3)->create(['content' => '<p>Stars above.</p>', 'sort_order' => 1]);

    // mPDF generates PDFs in PHP — no mocking needed

    $response = $this->service->export($book, ['format' => 'pdf', 'scope' => 'full']);

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('content-disposition'))->toContain('.pdf');

    $content = file_get_contents($response->getFile()->getPathname());
    expect($content)->toStartWith('%PDF');
});

test('pdf with table of contents exports successfully', function () {
    $book = Book::factory()->create(['title' => 'TOC PDF Book']);
    $storyline = Storyline::factory()->for($book)->create();
    $ch1 = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Chapter One', 'reader_order' => 1]);
    $ch2 = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Chapter Two', 'reader_order' => 2]);
    Scene::factory()->for($ch1)->create(['content' => '<p>First content.</p>', 'sort_order' => 1]);
    Scene::factory()->for($ch2)->create(['content' => '<p>Second content.</p>', 'sort_order' => 1]);

    // mPDF generates PDFs in PHP — no mocking needed

    $response = $this->service->export($book, [
        'format' => 'pdf',
        'scope' => 'full',
        'include_table_of_contents' => true,
    ]);

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('content-disposition'))->toContain('.pdf');

    $content = file_get_contents($response->getFile()->getPathname());
    expect($content)->toStartWith('%PDF');
});

test('epub chapters include semantic epub:type attributes', function () {
    $book = Book::factory()->create(['title' => 'Semantic EPUB', 'author' => 'Author', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'First Chapter']);
    Scene::factory()->for($chapter)->create(['content' => '<p>Content here.</p>', 'sort_order' => 1]);

    $response = $this->service->export($book, ['format' => 'epub', 'scope' => 'full']);

    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());

    $chapterXhtml = $zip->getFromName('OEBPS/Text/chapter-001.xhtml');
    expect($chapterXhtml)->toContain('xmlns:epub="http://www.idpf.org/2007/ops"');
    expect($chapterXhtml)->toContain('epub:type="bodymatter"');
    expect($chapterXhtml)->toContain('<section epub:type="chapter">');

    $zip->close();
});

test('epub toc xhtml includes epub namespace', function () {
    $book = Book::factory()->create(['title' => 'NS EPUB', 'author' => 'Author', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Ch 1']);
    Scene::factory()->for($chapter)->create(['content' => '<p>Text.</p>', 'sort_order' => 1]);

    $response = $this->service->export($book, [
        'format' => 'epub',
        'scope' => 'full',
        'include_table_of_contents' => true,
    ]);

    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());

    $tocXhtml = $zip->getFromName('OEBPS/toc.xhtml');
    expect($tocXhtml)->toContain('xmlns:epub="http://www.idpf.org/2007/ops"');

    $zip->close();
});

test('epub stylesheet includes typography properties', function () {
    $book = Book::factory()->create(['title' => 'Typo EPUB', 'author' => 'Author', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Ch 1']);
    Scene::factory()->for($chapter)->create(['content' => '<p>Text.</p>', 'sort_order' => 1]);

    $response = $this->service->export($book, ['format' => 'epub', 'scope' => 'full']);

    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());

    $css = $zip->getFromName('OEBPS/Styles/stylesheet.css');
    expect($css)->toContain('widows: 2');
    expect($css)->toContain('orphans: 2');
    expect($css)->toContain('hyphens: auto');
    expect($css)->toContain('-webkit-hyphens: auto');

    $zip->close();
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

    foreach (['docx', 'txt', 'epub', 'kdp'] as $format) {
        $this->postJson(route('books.settings.export.run', $book), [
            'format' => $format,
            'scope' => 'full',
        ])->assertOk();
    }

    // PDF requires NativePHP environment + System::printToPDF mock (Chromium-based)
    config(['nativephp-internal.running' => true]);

    // mPDF generates PDFs in PHP — no mocking needed

    $this->postJson(route('books.settings.export.run', $book), [
        'format' => 'pdf',
        'scope' => 'full',
    ])->assertOk();
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

// === Front/Back Matter Tests ===

test('pdf includes title page when front_matter selected', function () {
    $book = Book::factory()->create(['title' => 'Matter PDF', 'author' => 'Test Author']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Ch 1']);
    Scene::factory()->for($chapter)->create(['content' => '<p>Content.</p>', 'sort_order' => 1]);

    // mPDF generates PDFs in PHP — no mocking needed

    $response = $this->service->export($book, [
        'format' => 'pdf',
        'scope' => 'full',
        'front_matter' => ['title-page'],
    ]);

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('content-disposition'))->toContain('.pdf');

    $content = file_get_contents($response->getFile()->getPathname());
    expect($content)->toStartWith('%PDF');
});

test('pdf includes front and back matter pages', function () {
    $book = Book::factory()->create(['title' => 'Full Matter PDF', 'author' => 'Author']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Ch 1']);
    Scene::factory()->for($chapter)->create(['content' => '<p>Content.</p>', 'sort_order' => 1]);

    // mPDF generates PDFs in PHP — no mocking needed

    $response = $this->service->export($book, [
        'format' => 'pdf',
        'scope' => 'full',
        'front_matter' => ['title-page', 'copyright'],
        'back_matter' => ['acknowledgments', 'about-author'],
    ]);

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('content-disposition'))->toContain('.pdf');

    $content = file_get_contents($response->getFile()->getPathname());
    expect($content)->toStartWith('%PDF');
});

test('epub includes title page xhtml', function () {
    $book = Book::factory()->create(['title' => 'EPUB Matter', 'author' => 'Jane', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Ch 1']);
    Scene::factory()->for($chapter)->create(['content' => '<p>Content.</p>', 'sort_order' => 1]);

    $response = $this->service->export($book, [
        'format' => 'epub',
        'scope' => 'full',
        'front_matter' => ['title-page', 'copyright'],
    ]);

    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());

    expect($zip->locateName('OEBPS/Text/title-page.xhtml'))->not->toBeFalse();
    expect($zip->locateName('OEBPS/Text/copyright.xhtml'))->not->toBeFalse();

    $titlePage = $zip->getFromName('OEBPS/Text/title-page.xhtml');
    expect($titlePage)->toContain('EPUB Matter');
    expect($titlePage)->toContain('epub:type="titlepage"');

    $copyrightPage = $zip->getFromName('OEBPS/Text/copyright.xhtml');
    expect($copyrightPage)->toContain('epub:type="copyright-page"');

    $zip->close();
});

test('epub includes back matter in spine', function () {
    $book = Book::factory()->create(['title' => 'Spine Test', 'author' => 'Author', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Ch 1']);
    Scene::factory()->for($chapter)->create(['content' => '<p>Content.</p>', 'sort_order' => 1]);

    $response = $this->service->export($book, [
        'format' => 'epub',
        'scope' => 'full',
        'front_matter' => ['title-page'],
        'back_matter' => ['acknowledgments', 'about-author'],
    ]);

    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());

    $opf = $zip->getFromName('OEBPS/content.opf');

    // Front matter before chapters in spine
    $titlePagePos = strpos($opf, 'idref="title-page"');
    $chapterPos = strpos($opf, 'idref="chapter-001"');
    expect($titlePagePos)->toBeLessThan($chapterPos);

    // Back matter after chapters in spine
    $ackPos = strpos($opf, 'idref="acknowledgments"');
    $aboutPos = strpos($opf, 'idref="about-author"');
    expect($chapterPos)->toBeLessThan($ackPos);
    expect($ackPos)->toBeLessThan($aboutPos);

    $zip->close();
});

test('export endpoint accepts front_matter and back_matter arrays', function () {
    $book = Book::factory()->create(['author' => 'Test', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Text.</p>', 'sort_order' => 1]);

    config(['nativephp-internal.running' => true]);

    // mPDF generates PDFs in PHP — no mocking needed

    $this->postJson(route('books.settings.export.run', $book), [
        'format' => 'pdf',
        'scope' => 'full',
        'front_matter' => ['title-page', 'copyright'],
        'back_matter' => ['acknowledgments'],
    ])->assertOk();
});

test('export endpoint rejects invalid front_matter values', function () {
    $book = Book::factory()->create();

    $this->postJson(route('books.settings.export.run', $book), [
        'format' => 'pdf',
        'scope' => 'full',
        'front_matter' => ['invalid-page'],
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

// === cssEscape Helper Tests ===

test('cssEscape escapes double quotes', function () {
    expect(cssEscape('Hello "World"'))->toBe('Hello \\"World\\"');
});

test('cssEscape escapes backslashes', function () {
    expect(cssEscape('path\\to\\file'))->toBe('path\\\\to\\\\file');
});

test('cssEscape strips newlines', function () {
    expect(cssEscape("line1\nline2\rline3"))->toBe('line1line2line3');
});

test('cssEscape handles combined special characters', function () {
    expect(cssEscape("He said \"hello\"\nand left"))->toBe('He said \\"hello\\"and left');
});

// === Blade Template Tests ===

test('pdf blade template renders valid html', function () {
    $book = Book::factory()->create(['title' => 'Template Test', 'author' => 'Author']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Chapter One']);
    Scene::factory()->for($chapter)->create(['content' => '<p>Once upon a time.</p>', 'sort_order' => 1]);

    $chapters = $book->chapters()
        ->with(['scenes' => fn ($q) => $q->orderBy('sort_order'), 'act'])
        ->orderBy('reader_order')
        ->get();

    $contentPreparer = new ContentPreparer;
    $chapters = $chapters->map(function ($chapter) use ($contentPreparer) {
        $scenes = $chapter->scenes ?? collect();
        $preparedContent = '';
        foreach ($scenes as $sceneIndex => $scene) {
            if ($sceneIndex > 0) {
                $preparedContent .= '<p class="scene-break">*&nbsp;&nbsp;*&nbsp;&nbsp;*</p>';
            }
            $preparedContent .= $contentPreparer->toPdfHtml($scene->content ?? '');
        }
        $chapter->prepared_content = $preparedContent;

        return $chapter;
    });

    $options = ExportOptions::fromArray([
        'include_chapter_titles' => true,
        'show_page_numbers' => true,
        'trim_size' => '6x9',
        'font_size' => 11,
        'front_matter' => ['title-page'],
    ]);

    $html = view('export.pdf', [
        'book' => $book,
        'chapters' => $chapters,
        'options' => $options,
        'css' => (new \App\Services\Export\Templates\ClassicTemplate)->pdfCss($options->fontSize),
        'contentPreparer' => $contentPreparer,
    ])->render();

    expect($html)->toContain('<!DOCTYPE html>');
    expect($html)->toContain('Template Test');
    expect($html)->toContain('Chapter One');
    expect($html)->toContain('Once upon a time');
    expect($html)->toContain('@page');
});

// === Preview Endpoint Tests ===

test('preview endpoint returns pdf base64', function () {
    $book = Book::factory()->create(['title' => 'Preview Test']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Ch 1']);
    Scene::factory()->for($chapter)->create(['content' => '<p>Content.</p>', 'sort_order' => 1]);

    $response = $this->postJson(route('books.export.preview', $book), [
        'format' => 'pdf',
        'scope' => 'full',
        'trim_size' => '6x9',
        'font_size' => 11,
    ]);

    $response->assertOk()->assertJsonStructure(['pdf']);

    // Verify the response contains valid PDF data
    $pdfBytes = base64_decode($response->json('pdf'));
    expect($pdfBytes)->toStartWith('%PDF');
});

test('pdf export endpoint returns 422 without nativephp', function () {
    $book = Book::factory()->create();

    $this->postJson(route('books.settings.export.run', $book), [
        'format' => 'pdf',
        'scope' => 'full',
    ])->assertStatus(422)
        ->assertJson(['error' => 'PDF export requires the desktop app']);
});

test('non-pdf export works without nativephp', function () {
    $book = Book::factory()->create(['author' => 'Test', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Text.</p>', 'sort_order' => 1]);

    $this->postJson(route('books.settings.export.run', $book), [
        'format' => 'epub',
        'scope' => 'full',
    ])->assertOk();
});

// === Public Static Method Tests ===

test('resolveChapters is accessible as public static method', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 1]);

    $chapters = ExportService::resolveChapters($book, ['scope' => 'full']);

    expect($chapters)->toHaveCount(1);
});

// === Template Resolution Tests ===

test('resolveTemplate returns ClassicTemplate for classic slug', function () {
    $template = ExportService::resolveTemplate('classic');

    expect($template)->toBeInstanceOf(ClassicTemplate::class);
    expect($template)->toBeInstanceOf(ExportTemplate::class);
    expect($template->slug())->toBe('classic');
    expect($template->name())->toBe('Classic');
});

test('resolveTemplate falls back to ClassicTemplate for unknown slug', function () {
    $template = ExportService::resolveTemplate('unknown');

    expect($template)->toBeInstanceOf(ClassicTemplate::class);
});

test('ExportOptions parses template from array', function () {
    $options = ExportOptions::fromArray(['template' => 'classic']);
    expect($options->template)->toBe('classic');

    $optionsDefault = ExportOptions::fromArray([]);
    expect($optionsDefault->template)->toBe('classic');
});

test('export endpoint accepts template parameter', function () {
    $book = Book::factory()->create(['author' => 'Test', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Text.</p>', 'sort_order' => 1]);

    $this->postJson(route('books.settings.export.run', $book), [
        'format' => 'epub',
        'scope' => 'full',
        'template' => 'classic',
    ])->assertOk();
});

test('export endpoint rejects invalid template', function () {
    $book = Book::factory()->create();

    $this->postJson(route('books.settings.export.run', $book), [
        'format' => 'epub',
        'scope' => 'full',
        'template' => 'nonexistent',
    ])->assertUnprocessable();
});

test('injectMatterText populates options from app settings', function () {
    AppSetting::set('copyright_text', 'Test Copyright');

    $options = [
        'front_matter' => ['copyright'],
    ];

    ExportService::injectMatterText($options);

    expect($options['copyright_text'])->toBe('Test Copyright');
});
