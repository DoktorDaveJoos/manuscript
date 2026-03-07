<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\HealthSnapshot;
use App\Models\Storyline;
use App\Models\WritingSession;

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

test('dashboard returns writing heatmap data', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    WritingSession::factory()->for($book)->create([
        'date' => now()->toDateString(),
        'words_written' => 500,
        'goal_met' => true,
    ]);
    WritingSession::factory()->for($book)->create([
        'date' => now()->subDay()->toDateString(),
        'words_written' => 300,
        'goal_met' => false,
    ]);

    $response = $this->get("/books/{$book->id}/dashboard");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('books/dashboard')
        ->has('writing_heatmap', 2)
    );
});

test('dashboard returns health history from snapshots', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    HealthSnapshot::factory()->for($book)->create([
        'recorded_at' => now()->subDays(5)->toDateString(),
        'composite_score' => 72,
        'hooks_score' => 80,
        'pacing_score' => 65,
        'tension_score' => 70,
        'weave_score' => 75,
    ]);
    HealthSnapshot::factory()->for($book)->create([
        'recorded_at' => now()->toDateString(),
        'composite_score' => 78,
        'hooks_score' => 85,
        'pacing_score' => 70,
        'tension_score' => 75,
        'weave_score' => 80,
    ]);

    $response = $this->get("/books/{$book->id}/dashboard");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('books/dashboard')
        ->has('health_history', 2)
        ->where('health_history.0.composite', 72)
        ->where('health_history.1.composite', 78)
    );
});

test('dashboard returns manuscript target data', function () {
    $book = Book::factory()->create(['target_word_count' => 80000]);
    $storyline = Storyline::factory()->for($book)->create();

    Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 25000]);

    $response = $this->get("/books/{$book->id}/dashboard");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('books/dashboard')
        ->where('manuscript_target.target_word_count', 80000)
        ->where('manuscript_target.total_words', 25000)
        ->where('manuscript_target.progress_percent', 31)
        ->where('manuscript_target.milestone_reached', false)
    );
});

test('dashboard auto-detects milestone when target reached', function () {
    $book = Book::factory()->create(['target_word_count' => 50000]);
    $storyline = Storyline::factory()->for($book)->create();

    Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 55000]);

    $response = $this->get("/books/{$book->id}/dashboard");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('books/dashboard')
        ->where('manuscript_target.milestone_reached', true)
    );

    $book->refresh();
    expect($book->milestone_reached_at)->not->toBeNull();
});

test('milestone detection is idempotent', function () {
    $book = Book::factory()->create([
        'target_word_count' => 50000,
        'milestone_reached_at' => now()->subDay(),
    ]);
    $storyline = Storyline::factory()->for($book)->create();

    Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 55000]);

    $originalTimestamp = $book->milestone_reached_at->toISOString();

    $this->get("/books/{$book->id}/dashboard")->assertOk();

    $book->refresh();
    expect($book->milestone_reached_at->toISOString())->toBe($originalTimestamp);
});

test('dismiss milestone sets milestone_dismissed', function () {
    $book = Book::factory()->create([
        'target_word_count' => 50000,
        'milestone_reached_at' => now(),
        'milestone_dismissed' => false,
    ]);

    $response = $this->patch("/books/{$book->id}/milestone/dismiss");

    $response->assertOk();
    $response->assertJson(['dismissed' => true]);

    $book->refresh();
    expect($book->milestone_dismissed)->toBeTrue();
});

test('health snapshot upsert is idempotent', function () {
    $book = Book::factory()->create();
    $today = now()->toDateString();
    $baseData = [
        'book_id' => $book->id,
        'recorded_at' => $today,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    // First upsert creates
    HealthSnapshot::query()->upsert(
        [...$baseData, 'composite_score' => 50, 'hooks_score' => 60, 'pacing_score' => 55, 'tension_score' => 50, 'weave_score' => 45],
        ['book_id', 'recorded_at'],
        ['composite_score', 'hooks_score', 'pacing_score', 'tension_score', 'weave_score', 'updated_at'],
    );

    // Second upsert updates
    HealthSnapshot::query()->upsert(
        [...$baseData, 'composite_score' => 75, 'hooks_score' => 80, 'pacing_score' => 70, 'tension_score' => 65, 'weave_score' => 60],
        ['book_id', 'recorded_at'],
        ['composite_score', 'hooks_score', 'pacing_score', 'tension_score', 'weave_score', 'updated_at'],
    );

    expect(HealthSnapshot::where('book_id', $book->id)->count())->toBe(1);
    expect(HealthSnapshot::where('book_id', $book->id)->first()->composite_score)->toBe(75);
});

test('writing goal update accepts target_word_count', function () {
    $book = Book::factory()->create(['daily_word_count_goal' => 500]);

    $response = $this->putJson("/books/{$book->id}/writing-goal", [
        'daily_word_count_goal' => 1000,
        'target_word_count' => 80000,
    ]);

    $response->assertOk();
    $response->assertJson([
        'daily_word_count_goal' => 1000,
        'target_word_count' => 80000,
    ]);

    $book->refresh();
    expect($book->target_word_count)->toBe(80000);
});
