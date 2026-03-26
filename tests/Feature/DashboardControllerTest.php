<?php

use App\Enums\AnalysisType;
use App\Models\Analysis;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\License;
use App\Models\Storyline;

beforeEach(fn () => License::factory()->create());

test('dashboard returns correct stats', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 1000]);
    Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 2000]);
    Chapter::factory()->revised()->for($book)->for($storyline)->create(['word_count' => 500]);

    $this->get(route('books.dashboard', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('books/dashboard')
            ->where('stats.total_words', 3500)
            ->where('stats.chapter_count', 3)
            ->where('stats.estimated_pages', (int) ceil(3500 / 250))
            ->where('stats.reading_time_minutes', (int) ceil(3500 / 230))
            ->missing('stats.average_words')
            ->missing('stats.shortest_chapter')
            ->missing('stats.longest_chapter')
        );
});

test('dashboard returns suggested_next when next_chapter_suggestion exists', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    Analysis::factory()->for($book)->create([
        'type' => AnalysisType::NextChapterSuggestion,
        'result' => [
            'title' => 'The Confrontation',
            'description' => 'Continue with the climactic scene between the two rivals.',
        ],
    ]);

    $this->get(route('books.dashboard', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('suggested_next.title', 'The Confrontation')
            ->where('suggested_next.description', 'Continue with the climactic scene between the two rivals.')
        );
});

test('dashboard does not return removed keys', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->get(route('books.dashboard', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->missing('chapter_breakdown')
            ->missing('storyline_distribution')
            ->missing('health_metrics')
            ->missing('ai_preparation')
            ->missing('story_bible')
            ->missing('health_history')
            ->missing('ai_usage')
        );
});
