<?php

use App\Models\Book;
use App\Models\License;
use App\Models\Storyline;

test('free user sees dashboard with basic stats', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->get(route('books.dashboard', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('books/dashboard')
            ->has('stats')
            ->has('status_counts')
            ->where('writing_goal', null)
            ->where('manuscript_target', null)
            ->where('ai_usage', null)
            ->where('health_metrics', null)
            ->where('suggested_next', null)
            ->where('ai_preparation', null)
        );
});

test('pro user sees full dashboard', function () {
    License::factory()->create();
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->get(route('books.dashboard', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('books/dashboard')
            ->has('stats')
            ->has('status_counts')
            ->has('writing_goal')
            ->has('manuscript_target')
            ->has('ai_usage')
            ->has('writing_heatmap')
            ->has('health_history')
        );
});
