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
        ->assertSee('Configure and export your manuscript.')
        ->assertSee('Classic')
        ->assertSee('Customize in Book Designer')
        ->assertDontSee('Font Pairing')
        ->assertDontSee('Trim size');
});

it('offers the Normseite layout for docx exports', function () {
    [$book] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/settings/export");

    $page->assertDontSee('Normseite')
        ->click('[data-testid="export-format-docx"]')
        ->assertNoJavaScriptErrors()
        ->assertSee('Standard Manuscript')
        ->assertSee('Normseite (DIN A4)')
        ->click('[data-testid="docx-layout-normseite"]')
        ->assertSee('approx. 30 lines per page');
});
