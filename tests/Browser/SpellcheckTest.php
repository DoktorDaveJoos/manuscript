<?php

use App\Models\Book;

it('shows spell-error decorations on chapter load without touching the text', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $book->update(['language' => 'en']);
    $chapter = $chapters[0];
    $content = '<p>This sentens contains one mispeled word or two.</p>';
    $chapter->scenes()->first()->update(['content' => $content]);
    $chapter->currentVersion->update(['content' => $content]);
    $chapter->refreshContentHash();

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->wait(3)
        ->assertPresent('.editor-prose .spell-error');
});

it('does not flag correctly spelled English text', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $book->update(['language' => 'en']);
    $chapter = $chapters[0];
    $content = '<p>This sentence contains only correctly spelled words.</p>';
    $chapter->scenes()->first()->update(['content' => $content]);
    $chapter->currentVersion->update(['content' => $content]);
    $chapter->refreshContentHash();

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->wait(3)
        ->assertMissing('.editor-prose .spell-error');
});
