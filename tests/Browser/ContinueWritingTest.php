<?php

use App\Ai\Agents\ContinueWritingAgent;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Scene;
use App\Models\Storyline;

/**
 * Reproduces Sentry 126857918 (RangeError: Position N out of range).
 *
 * When a single streamed flush contains prose on both sides of a paragraph
 * break ("…one.\n\nTwo…"), the delta flusher chained insertContent +
 * splitBlock into ONE Tiptap chain. splitBlock re-maps the already-current
 * selection through the chain's accumulated mapping, double-counting the
 * just-inserted text and resolving a position past the end of the document.
 * The faked agent stream glues the paragraph break to its surrounding words
 * in one TextDelta, which forces exactly that flush shape.
 */
it('streams a multi-paragraph continuation without losing text', function () {
    ContinueWritingAgent::fake(
        fn () => "The first streamed paragraph.\n\nThe second streamed paragraph."
    );

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 1,
        'title' => 'Chapter 1',
        'word_count' => 1,
    ]);
    // Short content on purpose: the out-of-range resolve needs the cursor to
    // sit closer to the end of the doc than the first flushed paragraph is
    // long, which is the normal "continue writing at the end of a draft" case.
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => '<p>Hello.</p>',
    ]);
    $scene = Scene::factory()->for($chapter)->create([
        'content' => '<p>Hello.</p>',
        'sort_order' => 0,
        'word_count' => 1,
    ]);
    $chapter->refreshContentHash();

    $editorSelector = "#scene-{$scene->id} .ProseMirror";

    $page = visit("/books/{$book->id}/editor?panes={$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->click($editorSelector)
        ->keys($editorSelector, 'Control+p')
        ->click('Continue writing')
        ->click('button[type="submit"]')
        ->wait(3);

    $page->assertNoJavaScriptErrors()
        ->assertSee('The second streamed paragraph.');

    // The committed version must carry the full continuation, including the
    // paragraph that followed the break.
    $version = $chapter->versions()->where('is_current', true)->first();
    expect($version->content)
        ->toContain('The first streamed paragraph.')
        ->toContain('The second streamed paragraph.');
});
