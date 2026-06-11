<?php

use App\Models\Book;
use App\Models\License;

beforeEach(function () {
    License::factory()->create();
});

it('redirects the book settings index to the general page', function () {
    $book = Book::factory()->create(['title' => 'My Book', 'author' => 'Jane Doe']);

    $page = visit("/books/{$book->id}/settings");

    $page->assertNoJavaScriptErrors()
        ->assertSee('General')
        ->assertSee('Title')
        ->assertSee('Author')
        ->assertSee('Genre');
});

it('navigates between the book settings sections via the rail', function () {
    $book = Book::factory()->create(['title' => 'My Book', 'author' => 'Jane Doe']);

    $page = visit("/books/{$book->id}/settings/general");

    $page->assertNoJavaScriptErrors()
        ->click('Writing Style')
        ->assertSee('Describe the prose voice')
        ->click('Revision Rules')
        ->assertSee("Show, don't tell")
        ->click('Proofreading')
        ->assertSee('Enable spell check')
        ->click('Publishing')
        ->assertSee('Klappentext')
        ->click('Cover')
        ->assertSee('Upload your book cover')
        ->assertNoJavaScriptErrors();
});

it('shows the book prose rules on the revision rules page', function () {
    $rules = Book::defaultProsePassRules();
    $book = Book::factory()->create(['prose_pass_rules' => $rules]);

    $page = visit("/books/{$book->id}/settings/prose-rules");

    $page->assertNoJavaScriptErrors()
        ->assertSee("Show, don't tell")
        ->assertSee('Prose tightening');
});
