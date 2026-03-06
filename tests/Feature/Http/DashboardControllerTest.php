<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Storyline;

test('dashboard shows health metrics from chapter analysis data', function () {
    $book = Book::factory()->create(['ai_enabled' => true]);
    $storyline = Storyline::factory()->for($book)->create();

    $chapters = [];
    for ($i = 1; $i <= 5; $i++) {
        $chapters[] = Chapter::factory()->for($book)->for($storyline)->create([
            'reader_order' => $i,
            'word_count' => fake()->numberBetween(2000, 4000),
            'hook_score' => $i <= 3 ? 4 : 8,
            'hook_type' => $i <= 3 ? 'dead_end' : 'cliffhanger',
            'tension_score' => $i * 2,
            'summary' => "Summary of chapter {$i}",
        ]);
    }

    $response = $this->get("/books/{$book->id}/dashboard");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('books/dashboard')
        ->has('health_metrics')
        ->where('health_metrics.metrics.0.label', 'Hooks')
        ->has('health_metrics.attention_items')
    );
});

test('dashboard shows story bible when available', function () {
    $book = Book::factory()->create([
        'ai_enabled' => true,
        'story_bible' => [
            'characters' => [['name' => 'John', 'role' => 'protagonist']],
            'themes' => ['Courage', 'Betrayal'],
            'plot_outline' => [['description' => 'Hero journey']],
            'setting' => [],
            'style_rules' => [],
            'genre_rules' => [],
            'timeline' => [],
        ],
    ]);
    Storyline::factory()->for($book)->create();

    $response = $this->get("/books/{$book->id}/dashboard");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('books/dashboard')
        ->has('story_bible')
        ->has('story_bible.characters')
        ->has('story_bible.themes')
    );
});

test('dashboard returns null health metrics when no chapter analysis exists', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $response = $this->get("/books/{$book->id}/dashboard");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('books/dashboard')
        ->where('health_metrics', null)
    );
});

test('dashboard identifies weakest hooks as attention items', function () {
    $book = Book::factory()->create(['ai_enabled' => true]);
    $storyline = Storyline::factory()->for($book)->create();

    // Create chapters with varying hook scores
    Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 1,
        'hook_score' => 2,
        'hook_type' => 'dead_end',
        'tension_score' => 5,
    ]);
    Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 2,
        'hook_score' => 9,
        'hook_type' => 'cliffhanger',
        'tension_score' => 8,
    ]);
    Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 3,
        'hook_score' => 3,
        'hook_type' => 'closed',
        'tension_score' => 6,
    ]);

    $response = $this->get("/books/{$book->id}/dashboard");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('books/dashboard')
        ->has('health_metrics.attention_items')
        ->where('health_metrics.attention_items.0.severity', 'high')
    );
});
