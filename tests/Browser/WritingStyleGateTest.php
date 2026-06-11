<?php

use App\Ai\Agents\WritingStyleExtractor;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Scene;
use App\Models\Storyline;

/**
 * Pre-flight writing-style offer: prose-generating features (continue
 * writing, rewrite, revise) on a style-less book with enough prose first
 * offer to derive the style. Chat and plot coach are never gated.
 */
function createStyleLessBookWithProse(): array
{
    $content = '<p>'.trim(str_repeat('prose ', 400)).'</p>';

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 1,
        'title' => 'Chapter 1',
        'word_count' => 400,
    ]);
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => $content,
    ]);
    $scene = Scene::factory()->for($chapter)->create([
        'content' => $content,
        'sort_order' => 0,
        'word_count' => 400,
    ]);
    $chapter->refreshContentHash();

    return [$book, $chapter, $scene];
}

it('offers to derive the writing style before continue writing and proceeds on skip', function () {
    [$book, $chapter, $scene] = createStyleLessBookWithProse();

    $editorSelector = "#scene-{$scene->id} .ProseMirror";

    $page = visit("/books/{$book->id}/editor?panes={$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->click($editorSelector)
        ->keys($editorSelector, 'Control+p')
        ->click('Continue writing')
        ->assertSee('Set up your writing style?')
        ->click('Continue without')
        ->assertSee('Word goal')
        ->assertNoJavaScriptErrors();

    // A plain skip keeps the offer alive for next time.
    expect($book->refresh()->writing_style_prompt_dismissed)->toBeFalse();
});

it('analyzes the style inline and then proceeds to the requested feature', function () {
    WritingStyleExtractor::fake();

    [$book, $chapter, $scene] = createStyleLessBookWithProse();

    $editorSelector = "#scene-{$scene->id} .ProseMirror";

    $page = visit("/books/{$book->id}/editor?panes={$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->click($editorSelector)
        ->keys($editorSelector, 'Control+p')
        ->click('Continue writing')
        ->assertSee('Set up your writing style?')
        ->click('Analyze my style')
        ->wait(2)
        ->assertSee('Word goal')
        ->assertNoJavaScriptErrors();

    expect($book->refresh()->writing_style)->not->toBeNull();
});

it('persists the dismissal so the offer never returns for this book', function () {
    [$book, $chapter, $scene] = createStyleLessBookWithProse();

    $editorSelector = "#scene-{$scene->id} .ProseMirror";

    $page = visit("/books/{$book->id}/editor?panes={$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->click($editorSelector)
        ->keys($editorSelector, 'Control+p')
        ->click('Continue writing')
        ->assertSee('Set up your writing style?')
        ->click("Don't show this again for this book")
        ->click('Continue without')
        ->assertSee('Word goal')
        ->wait(1);

    expect($book->refresh()->writing_style_prompt_dismissed)->toBeTrue();
});
