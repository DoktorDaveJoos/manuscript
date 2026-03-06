<?php

use App\Models\Book;
use App\Models\License;
use App\Models\Storyline;

test('canvas page loads successfully', function () {
    License::factory()->create();
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->get(route('books.canvas', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('canvas/index')
            ->has('book')
        );
});
