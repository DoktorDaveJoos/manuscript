<?php

use App\Models\Act;
use App\Models\Beat;
use App\Models\Book;
use App\Models\PlotPoint;
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

test('plot page includes plot points with nested beats', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $act = Act::factory()->for($book)->create();
    $plotPoint = PlotPoint::factory()->for($book)->create(['act_id' => $act->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $plotPoint->id, 'title' => 'Hero finds map']);

    $this->get(route('books.plot', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('plot/index')
            ->has('plotPoints', 1)
            ->has('plotPoints.0.beats', 1)
            ->where('plotPoints.0.beats.0.title', 'Hero finds map')
            ->missing('chapters')
            ->missing('connections')
        );
});
