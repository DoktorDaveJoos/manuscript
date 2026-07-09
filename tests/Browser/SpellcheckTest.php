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

it('replaces a misspelled word from the right-click popover', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $book->update(['language' => 'en']);
    $chapter = $chapters[0];
    $content = '<p>The knight was mispeled here.</p>';
    $chapter->scenes()->first()->update(['content' => $content]);
    $chapter->currentVersion->update(['content' => $content]);
    $chapter->refreshContentHash();

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->wait(3)
        ->assertPresent('.editor-prose .spell-error')
        ->rightClick('.editor-prose .spell-error')
        ->wait(1)
        ->assertSee('Add to Dictionary');
});

it('adds a word to the custom dictionary and clears its squiggle', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $book->update(['language' => 'en']);
    $chapter = $chapters[0];
    $content = '<p>The wizard Zaphrandor smiled.</p>';
    $chapter->scenes()->first()->update(['content' => $content]);
    $chapter->currentVersion->update(['content' => $content]);
    $chapter->refreshContentHash();

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->wait(3)
        ->assertPresent('.editor-prose .spell-error')
        ->rightClick('.editor-prose .spell-error')
        ->wait(1)
        ->click('Add to Dictionary')
        ->wait(2)
        ->assertMissing('.editor-prose .spell-error');

    expect($book->fresh()->custom_dictionary)->toContain('zaphrandor');
});

it('never flags words already in the custom dictionary', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $book->update(['language' => 'en', 'custom_dictionary' => ['zaphrandor']]);
    $chapter = $chapters[0];
    $content = '<p>The wizard Zaphrandor smiled.</p>';
    $chapter->scenes()->first()->update(['content' => $content]);
    $chapter->currentVersion->update(['content' => $content]);
    $chapter->refreshContentHash();

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->wait(3)
        ->assertMissing('.editor-prose .spell-error');
});

it('checks German books with the German dictionary, including compounds', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $book->update(['language' => 'de']);
    $chapter = $chapters[0];
    $scene = $chapter->scenes()->first();
    // "Haustürschlüssel" is a compound that plain word-list checkers flag;
    // real Hunspell must accept it. "falsh" is a genuine misspelling.
    $content = '<p>Der Haustürschlüssel liegt auf dem Küchentisch und das ist falsh.</p>';
    $scene->update(['content' => $content]);
    $chapter->currentVersion->update(['content' => $content]);
    $chapter->refreshContentHash();

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->wait(5)
        ->assertPresent('.editor-prose .spell-error')
        ->assertSeeIn('.editor-prose .spell-error', 'falsh')
        ->assertDontSeeIn('.editor-prose .spell-error', 'Haustürschlüssel');
});

it('clears all squiggles when spell check is toggled off', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $book->update(['language' => 'en']);
    $chapter = $chapters[0];
    $scene = $chapter->scenes()->first();
    $content = '<p>Another mispeled word sits here.</p>';
    $scene->update(['content' => $content]);
    $chapter->currentVersion->update(['content' => $content]);
    $chapter->refreshContentHash();

    $editorSelector = "#scene-{$scene->id} .ProseMirror";

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->wait(3)
        ->assertPresent('.editor-prose .spell-error')
        ->click($editorSelector)
        ->keys($editorSelector, 'Control+p')
        ->wait(1)
        ->click('Disable Spell Check')
        ->wait(1)
        ->assertMissing('.editor-prose .spell-error');
});
