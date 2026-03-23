<?php

use App\Models\Act;
use App\Models\Beat;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\License;
use App\Models\PlotPoint;
use App\Models\Storyline;

beforeEach(fn () => License::factory()->create());

it('returns chapters with storyline_id and storyline name', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id, 'name' => 'Main Arc']);
    Chapter::factory()->create([
        'book_id' => $book->id,
        'storyline_id' => $storyline->id,
        'title' => 'The Beginning',
        'reader_order' => 1,
    ]);

    $this->get(route('books.plot', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('plot/index')
            ->has('chapters', 1)
            ->where('chapters.0.storyline_id', $storyline->id)
            ->where('chapters.0.title', 'The Beginning')
            ->has('chapters.0.storyline')
            ->where('chapters.0.storyline.name', 'Main Arc')
            ->has('storylines')
        );
});

it('includes storyline_id in beat chapter data', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);
    $act = Act::factory()->create(['book_id' => $book->id]);
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id, 'act_id' => $act->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $plotPoint->id]);
    $chapter = Chapter::factory()->create([
        'book_id' => $book->id,
        'storyline_id' => $storyline->id,
        'reader_order' => 1,
    ]);
    $beat->chapters()->attach($chapter->id);

    $this->get(route('books.plot', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('plot/index')
            ->where('plotPoints.0.beats.0.chapters.0.storyline_id', $storyline->id)
        );
});
