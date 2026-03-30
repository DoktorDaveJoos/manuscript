<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\HealthSnapshot;
use App\Models\License;
use App\Models\Storyline;
use App\Models\WritingSession;

beforeEach(fn () => License::factory()->create());

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

    $allColumns = ['composite_score', 'hooks_score', 'pacing_score', 'tension_score', 'weave_score', 'scene_purpose_score', 'tension_dynamics_score', 'emotional_arc_score', 'craft_score', 'updated_at'];

    // First upsert creates
    HealthSnapshot::query()->upsert(
        [...$baseData, 'composite_score' => 50, 'hooks_score' => 60, 'pacing_score' => 55, 'tension_score' => 50, 'weave_score' => 0, 'scene_purpose_score' => 45, 'tension_dynamics_score' => 50, 'emotional_arc_score' => 55, 'craft_score' => 60],
        ['book_id', 'recorded_at'],
        $allColumns,
    );

    // Second upsert updates
    HealthSnapshot::query()->upsert(
        [...$baseData, 'composite_score' => 75, 'hooks_score' => 80, 'pacing_score' => 70, 'tension_score' => 65, 'weave_score' => 0, 'scene_purpose_score' => 70, 'tension_dynamics_score' => 65, 'emotional_arc_score' => 72, 'craft_score' => 78],
        ['book_id', 'recorded_at'],
        $allColumns,
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
