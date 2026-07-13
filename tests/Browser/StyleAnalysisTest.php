<?php

it('marks fillers, filter words, repetition and clichés when the style panel opens', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $book->update(['language' => 'de']);
    $chapter = $chapters[0];
    $content = '<p>Der Schreibtisch war eigentlich leer, dachte er. Die leeren Tassen standen auf dem Tisch. Seine geschlossene Faust schlug zu.</p>';
    $chapter->scenes()->first()->update(['content' => $content]);
    $chapter->currentVersion->update(['content' => $content]);
    $chapter->refreshContentHash();

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->wait(2)
        ->assertMissing('.editor-prose .style-filler')
        ->click('[data-access-bar=style]')
        ->wait(3)
        ->assertPresent('.editor-prose .style-filler')
        ->assertSeeIn('.editor-prose .style-filler', 'eigentlich')
        ->assertPresent('.editor-prose .style-filter-word')
        ->assertPresent('.editor-prose .style-repetition')
        ->assertPresent('.editor-prose .style-cliche')
        ->assertSee('Filler words');
});

it('clears all style marks when the panel closes', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $book->update(['language' => 'de']);
    $chapter = $chapters[0];
    $content = '<p>Das war eigentlich ganz einfach.</p>';
    $chapter->scenes()->first()->update(['content' => $content]);
    $chapter->currentVersion->update(['content' => $content]);
    $chapter->refreshContentHash();

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar=style]')
        ->wait(3)
        ->assertPresent('.editor-prose .style-filler')
        ->click('[data-access-bar=style]')
        ->wait(1)
        ->assertMissing('.editor-prose .style-filler');
});

it('ignores a word for the book from the finding popover', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $book->update(['language' => 'de']);
    $chapter = $chapters[0];
    $content = '<p>Der Drucker streikte eigentlich nie.</p>';
    $chapter->scenes()->first()->update(['content' => $content]);
    $chapter->currentVersion->update(['content' => $content]);
    $chapter->refreshContentHash();

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar=style]')
        ->wait(3)
        ->assertPresent('.editor-prose .style-filler')
        ->click('.editor-prose .style-filler')
        ->wait(1)
        ->assertSee('Ignore this word')
        ->click('[data-style-action=ignore]')
        ->wait(2)
        ->assertMissing('.editor-prose .style-filler');

    expect($book->fresh()->style_ignored_words)->toContain('eigentlich');
});
