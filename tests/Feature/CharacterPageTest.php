<?php

use App\Models\Book;
use App\Models\Storyline;

test('characters page loads successfully', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->get(route('books.characters', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('characters/index')
            ->has('book')
            ->has('characters')
        );
});
