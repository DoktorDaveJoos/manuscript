<?php

use App\Ai\Agents\ManuscriptAnalyzer;
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

it('runs plot health synchronously and returns analysis result', function () {
    ManuscriptAnalyzer::fake();

    $book = Book::factory()->withAi()->create();

    $this->postJson(route('books.plot.ai.health', $book))
        ->assertOk()
        ->assertJsonStructure(['analysis' => ['score', 'findings', 'recommendations']]);

    expect($book->analyses()->where('type', 'thriller_health')->count())->toBe(1);
});

it('runs plot hole detection synchronously and returns analysis result', function () {
    ManuscriptAnalyzer::fake();

    $book = Book::factory()->withAi()->create();

    $this->postJson(route('books.plot.ai.holes', $book))
        ->assertOk()
        ->assertJsonStructure(['analysis' => ['score', 'findings', 'recommendations']]);

    expect($book->analyses()->where('type', 'plothole')->count())->toBe(1);
});

it('runs beat suggestion synchronously and returns analysis result', function () {
    ManuscriptAnalyzer::fake();

    $book = Book::factory()->withAi()->create();

    $this->postJson(route('books.plot.ai.beats', $book))
        ->assertOk()
        ->assertJsonStructure(['analysis' => ['score', 'findings', 'recommendations']]);

    expect($book->analyses()->where('type', 'next_chapter_suggestion')->count())->toBe(1);
});
