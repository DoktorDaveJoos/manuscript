<?php

use App\Enums\ChapterHeading;
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
use App\Services\Export\Templates\ElegantTemplate;
use App\Services\Export\Templates\ModernTemplate;

beforeEach(function () {
    License::factory()->create();
});

// === Enum semantics ===

test('ChapterHeading exposes number/title visibility', function () {
    expect(ChapterHeading::None->showsNumber())->toBeFalse();
    expect(ChapterHeading::None->showsTitle())->toBeFalse();

    expect(ChapterHeading::Number->showsNumber())->toBeTrue();
    expect(ChapterHeading::Number->showsTitle())->toBeFalse();

    expect(ChapterHeading::Full->showsNumber())->toBeTrue();
    expect(ChapterHeading::Full->showsTitle())->toBeTrue();
});

// === Template: chapterHeaderHtml can omit the title ===

test('chapterHeaderHtml renders the number label without the title when title is excluded', function (string $templateClass) {
    $html = (new $templateClass)->chapterHeaderHtml(0, 'Secret Title', 'en', includeTitle: false);

    expect($html)
        ->toContain('chapter-label')
        ->toContain('id="chapter-0"')
        ->not->toContain('Secret Title')
        ->not->toContain('<h1>');
})->with([ClassicTemplate::class, ModernTemplate::class, ElegantTemplate::class]);

test('chapterHeaderHtml still renders the title by default', function () {
    $html = (new ClassicTemplate)->chapterHeaderHtml(0, 'Visible Title', 'en');

    expect($html)
        ->toContain('Chapter 1')
        ->toContain('<h1>Visible Title</h1>');
});

// === ExportOptions parsing ===

test('ExportOptions parses chapter_heading and defaults to full', function () {
    expect(ExportOptions::fromArray(['chapter_heading' => 'number'])->chapterHeading)
        ->toBe(ChapterHeading::Number);
    expect(ExportOptions::fromArray(['chapter_heading' => 'none'])->chapterHeading)
        ->toBe(ChapterHeading::None);
    expect(ExportOptions::fromArray([])->chapterHeading)
        ->toBe(ChapterHeading::Full);
});

// === PDF render reflects the chosen heading ===

function renderHeadingPdfHtml(string $chapterHeading): string
{
    $book = Book::factory()->create(['title' => 'Heading Book', 'author' => 'Author', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'The Real Title']);
    Scene::factory()->for($chapter)->create(['content' => '<p>Body.</p>', 'sort_order' => 1]);

    $chapters = $book->chapters()->with(['scenes' => fn ($q) => $q->orderBy('sort_order'), 'act'])->get();
    $options = ExportOptions::fromArray([
        'chapter_heading' => $chapterHeading,
        'trim_size' => '6x9',
        'font_size' => 11,
    ]);

    return (new PdfExporter(new ContentPreparer, new FontService, new ClassicTemplate))
        ->renderHtml($book, $chapters, $options, false);
}

test('pdf shows number label only when chapter_heading is number', function () {
    $html = renderHeadingPdfHtml('number');

    expect($html)
        ->toContain('Chapter 1')
        ->not->toContain('<h1>The Real Title</h1>');
});

test('pdf shows number and title when chapter_heading is full', function () {
    $html = renderHeadingPdfHtml('full');

    expect($html)
        ->toContain('Chapter 1')
        ->toContain('<h1>The Real Title</h1>');
});

test('pdf shows no chapter heading when chapter_heading is none', function () {
    $html = renderHeadingPdfHtml('none');

    // 'chapter-label' also appears as a CSS class in the <style> block, so assert on
    // the per-chapter anchor id, which only the rendered heading emits.
    expect($html)
        ->not->toContain('id="chapter-0"')
        ->not->toContain('<h1>The Real Title</h1>');
});

// === Request validation ===

test('export endpoint accepts chapter_heading', function () {
    $book = Book::factory()->create(['author' => 'A', 'language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Text.</p>', 'sort_order' => 1]);

    $this->postJson(route('books.settings.export.run', $book), [
        'format' => 'epub',
        'scope' => 'full',
        'chapter_heading' => 'number',
    ])->assertOk();
});

test('export endpoint rejects invalid chapter_heading', function () {
    $book = Book::factory()->create();

    $this->postJson(route('books.settings.export.run', $book), [
        'format' => 'epub',
        'scope' => 'full',
        'chapter_heading' => 'bogus',
    ])->assertUnprocessable();
});
