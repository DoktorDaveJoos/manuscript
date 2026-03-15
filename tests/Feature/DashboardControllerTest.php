<?php

use App\Enums\AnalysisType;
use App\Models\Analysis;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Storyline;

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

test('dashboard returns null health_metrics when no analyses exist', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->get(route('books.dashboard', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('health_metrics', null)
        );
});

test('dashboard returns health_metrics when analyses exist', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    Analysis::factory()->for($book)->create([
        'type' => AnalysisType::Pacing,
        'chapter_id' => null,
        'result' => [
            'score' => 7,
            'findings' => [
                ['title' => 'Slow middle', 'description' => 'Pacing drops in chapters 4-6', 'severity' => 'medium'],
            ],
        ],
    ]);

    Analysis::factory()->for($book)->create([
        'type' => AnalysisType::Plothole,
        'chapter_id' => null,
        'result' => [
            'score' => 8,
            'findings' => [
                ['title' => 'Unresolved hook', 'description' => 'Chapter 2 setup not paid off', 'severity' => 'high'],
            ],
        ],
    ]);

    Analysis::factory()->for($book)->create([
        'type' => AnalysisType::Density,
        'chapter_id' => null,
        'result' => ['score' => 6, 'findings' => []],
    ]);

    Analysis::factory()->for($book)->create([
        'type' => AnalysisType::CharacterConsistency,
        'chapter_id' => null,
        'result' => ['score' => 9, 'findings' => []],
    ]);

    $this->get(route('books.dashboard', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('health_metrics')
            ->where('health_metrics.composite_score', 75)
            ->has('health_metrics.metrics', 4)
            ->where('health_metrics.metrics.0.label', 'pacing')
            ->where('health_metrics.metrics.0.score', 70)
            ->where('health_metrics.metrics.1.label', 'hooks')
            ->where('health_metrics.metrics.1.score', 80)
            ->has('health_metrics.attention_items', 2)
            ->where('health_metrics.attention_items.0.title', 'Slow middle')
            ->where('health_metrics.attention_items.0.severity', 'medium')
            ->where('health_metrics.attention_items.1.title', 'Unresolved hook')
            ->where('health_metrics.attention_items.1.severity', 'high')
            ->has('health_metrics.last_analyzed_at')
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
        );
});
