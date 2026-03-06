<?php

use App\Models\Book;
use App\Models\Storyline;

test('plot page loads successfully', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->get(route('books.plot', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('plot/index')
            ->has('book')
            ->has('acts')
            ->has('plotPoints')
        );
});
