<?php

use App\Models\Book;
use App\Models\License;

beforeEach(function () {
    License::factory()->create();
});

it('loads the publish page without JavaScript errors', function () {
    $book = Book::factory()->create(['title' => 'My Book', 'author' => 'Jane Doe']);

    $page = visit(route('books.publish', $book));

    $page->assertNoJavaScriptErrors()
        ->assertSee('Cover')
        // The Klappentext section sits above the cover.
        ->assertSee('Klappentext');
});

it('shows the back/front toggle inside the cover creator dialog', function () {
    $book = Book::factory()->create([
        'title' => 'My Book',
        'author' => 'Jane Doe',
        'klappentext' => 'A back-cover hook that sells the book.',
    ]);

    $page = visit(route('books.publish', $book));

    $page->click('Create one')
        ->assertSee('Create a cover')
        ->assertSee('Back')
        ->assertSee('Front')
        ->assertNoJavaScriptErrors();
});

it('opens the cover creator dialog and shows a preview', function () {
    $book = Book::factory()->create(['title' => 'My Book', 'author' => 'Jane Doe']);

    $page = visit(route('books.publish', $book));

    $page->click('Create one')
        ->assertSee('Create a cover')
        ->assertSee('Save as cover')
        ->assertNoJavaScriptErrors();
});

it('offers a separate cover PDF download for a generated cover', function () {
    $book = Book::factory()->create([
        'title' => 'My Book',
        'author' => 'Jane Doe',
        'cover_image_path' => 'covers/test.png',
        'cover_settings' => [
            'title' => 'My Book',
            'subtitle' => 'A Thriller',
            'author' => 'Jane Doe',
            'trim_size' => '13x19cm',
        ],
    ]);

    $page = visit(route('books.publish', $book));

    $page->assertNoJavaScriptErrors()
        ->assertSee('Download PDF');
});
