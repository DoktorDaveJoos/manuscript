<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Scene;
use App\Models\Storyline;

it('copies the live chapter text including words typed since load', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 1,
        'title' => 'Chapter 1',
        'word_count' => 1,
    ]);
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
        ->typeSlowly($editorSelector, ' Freshly typed words.')
        ->wait(1);

    // Capture instead of writing to the real clipboard (headless browsers
    // don't grant clipboard permissions).
    $page->script(
        'navigator.clipboard.writeText = (text) => { window.__copiedChapterText = text; return Promise.resolve(); };',
    );

    $page->click('button[aria-label="Copy chapter"]')->wait(1);

    $copied = $page->script('window.__copiedChapterText');

    // The copy must reflect what's on screen right now — not the scene
    // state from the last server fetch.
    expect($copied)
        ->toContain('Hello.')
        ->toContain('Freshly typed words.');
});
