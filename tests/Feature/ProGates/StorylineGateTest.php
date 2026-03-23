<?php

use App\Models\Book;
use App\Models\License;
use App\Models\Storyline;

test('free user has default main storyline', function () {
    $book = Book::factory()->create();

    $this->post(route('books.import.skip', $book));

    expect($book->storylines()->count())->toBe(1);
});

test('free user cannot create second storyline', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->post(route('storylines.store', $book), [
        'name' => 'Subplot',
    ])->assertRedirect(route('books.editor', $book));

    expect($book->storylines()->count())->toBe(1);
});

test('pro user can create unlimited storylines', function () {
    License::factory()->create();
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->post(route('storylines.store', $book), [
        'name' => 'Subplot',
    ])->assertRedirect();

    expect($book->storylines()->count())->toBe(2);
});
