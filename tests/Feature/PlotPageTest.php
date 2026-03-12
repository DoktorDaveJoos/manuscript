<?php

use App\Models\Book;
use App\Models\Chapter;
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

test('plot page includes flat chapters prop sorted by reader_order', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $chapter3 = Chapter::factory()->for($book)->create([
        'storyline_id' => $storyline->id,
        'reader_order' => 3,
        'title' => 'Third',
    ]);
    $chapter1 = Chapter::factory()->for($book)->create([
        'storyline_id' => $storyline->id,
        'reader_order' => 1,
        'title' => 'First',
    ]);
    $chapter2 = Chapter::factory()->for($book)->create([
        'storyline_id' => $storyline->id,
        'reader_order' => 2,
        'title' => 'Second',
    ]);

    $trashedChapter = Chapter::factory()->for($book)->create([
        'storyline_id' => $storyline->id,
        'reader_order' => 0,
        'title' => 'Trashed',
    ]);
    $trashedChapter->delete();

    $this->get(route('books.plot', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('plot/index')
            ->has('chapters', 3)
            ->where('chapters.0.id', $chapter1->id)
            ->where('chapters.1.id', $chapter2->id)
            ->where('chapters.2.id', $chapter3->id)
        );
});
