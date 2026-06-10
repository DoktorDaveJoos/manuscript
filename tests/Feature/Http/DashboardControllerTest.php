<?php

use App\Models\Book;
use App\Models\Chapter;
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
