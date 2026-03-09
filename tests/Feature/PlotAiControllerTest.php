<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\License;
use App\Models\Storyline;

beforeEach(function () {
    License::factory()->create();
});

it('returns tension arc from existing chapter scores', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    Chapter::factory()->for($book)->for($storyline)->create([
        'tension_score' => 7,
        'reader_order' => 0,
    ]);

    $this->postJson(route('books.plot.ai.tension', $book))
        ->assertOk()
        ->assertJsonStructure(['tension_arc', 'generated_at']);
});

it('returns 422 when no tension data available', function () {
    $book = Book::factory()->withAi()->create();

    $this->postJson(route('books.plot.ai.tension', $book))
        ->assertUnprocessable();
});

it('returns analysis status', function () {
    $book = Book::factory()->create();

    $this->getJson(route('books.plot.ai.status', $book))
        ->assertOk()
        ->assertJsonStructure(['analyses']);
});

it('requires an active license for plot ai routes', function () {
    License::query()->delete();

    $book = Book::factory()->create();

    $this->postJson(route('books.plot.ai.health', $book))
        ->assertForbidden();
});
