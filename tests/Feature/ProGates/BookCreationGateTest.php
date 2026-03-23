<?php

use App\Models\Book;
use App\Models\License;
use App\Models\Storyline;

test('free user can create first book', function () {
    $this->post(route('books.store'), [
        'title' => 'My Novel',
        'author' => 'Jane Doe',
        'language' => 'en',
    ])->assertRedirect();

    expect(Book::count())->toBe(1);
});

test('free user cannot create second book', function () {
    Book::factory()->create();

    $this->post(route('books.store'), [
        'title' => 'Second Novel',
        'author' => 'Jane Doe',
        'language' => 'en',
    ])->assertRedirect(route('books.index'));

    expect(Book::count())->toBe(1);
});

test('free user cannot duplicate when at limit', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->post(route('books.duplicate', $book))
        ->assertRedirect(route('books.index'));

    expect(Book::count())->toBe(1);
});

test('pro user can create unlimited books', function () {
    License::factory()->create();
    Book::factory()->create();

    $this->post(route('books.store'), [
        'title' => 'Second Novel',
        'author' => 'Jane Doe',
        'language' => 'en',
    ])->assertRedirect();

    expect(Book::count())->toBe(2);
});

test('existing books remain accessible when over limit', function () {
    $books = Book::factory()->count(3)->create();

    foreach ($books as $book) {
        Storyline::factory()->for($book)->create();
    }

    // All books are still accessible even though user is over limit
    $this->get(route('books.index'))->assertOk();
});
