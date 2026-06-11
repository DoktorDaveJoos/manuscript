<?php

it('renders publishing settings with book metadata sections', function () {
    [$book] = createBookWithChapters(1);

    // Legacy publish URL redirects to the publishing settings page; the cover
    // moved to its own page under book settings.
    $page = visit("/books/{$book->id}/publish");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Publish')
        ->assertSee('Book Metadata')
        ->assertSee('Front Matter')
        ->assertSee('Back Matter');
});

it('displays metadata fields on publish page', function () {
    [$book] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/publish");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Publisher Name')
        ->assertSee('ISBN')
        ->assertSee('Copyright')
        ->assertSee('Dedication');
});

it('renders export page with export controls', function () {
    [$book] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/settings/export");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Export')
        ->assertSee('Configure and export your manuscript.');
});
