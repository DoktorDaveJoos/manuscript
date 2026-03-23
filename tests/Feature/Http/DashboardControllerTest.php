<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\HealthSnapshot;
use App\Models\License;
use App\Models\Storyline;
use App\Models\WritingSession;

beforeEach(fn () => License::factory()->create());

test('dashboard shows health metrics from chapter analysis data', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $pacingFeels = ['breakneck', 'brisk', 'measured', 'languid', 'measured'];
    $purposes = ['turning_point', 'deepening', 'revelation', 'setup', 'resolution'];
    for ($i = 1; $i <= 5; $i++) {
        Chapter::factory()->for($book)->for($storyline)->create([
            'reader_order' => $i,
            'word_count' => fake()->numberBetween(2000, 4000),
            'hook_score' => $i <= 3 ? 4 : 8,
            'hook_type' => $i <= 3 ? 'dead_end' : 'cliffhanger',
            'tension_score' => $i * 2,
            'micro_tension_score' => 5,
            'scene_purpose' => $purposes[$i - 1],
            'value_shift' => 'calm → tense',
            'emotional_shift_magnitude' => $i + 2,
            'pacing_feel' => $pacingFeels[$i - 1],
            'entry_hook_score' => 6,
            'exit_hook_score' => $i <= 3 ? 4 : 8,
            'sensory_grounding' => 3,
            'information_delivery' => 'organic',
            'summary' => "Summary of chapter {$i}",
        ]);
    }

    $response = $this->get("/books/{$book->id}/dashboard");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('books/dashboard')
        ->has('health_metrics')
        ->where('health_metrics.metrics.0.label', 'scene_purpose')
        ->where('health_metrics.metrics.1.label', 'pacing')
        ->where('health_metrics.metrics.2.label', 'tension_dynamics')
        ->where('health_metrics.metrics.3.label', 'hooks')
        ->where('health_metrics.metrics.4.label', 'emotional_arc')
        ->where('health_metrics.metrics.5.label', 'craft')
        ->has('health_metrics.attention_items')
    );
});

test('dashboard shows story bible when available', function () {
    $book = Book::factory()->create([
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
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();

    Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 1,
        'hook_score' => 2,
        'exit_hook_score' => 2,
        'hook_type' => 'dead_end',
        'tension_score' => 5,
        'micro_tension_score' => 5,
        'scene_purpose' => 'deepening',
        'pacing_feel' => 'measured',
        'entry_hook_score' => 5,
        'sensory_grounding' => 3,
        'information_delivery' => 'organic',
    ]);
    Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 2,
        'hook_score' => 9,
        'exit_hook_score' => 9,
        'hook_type' => 'cliffhanger',
        'tension_score' => 8,
        'micro_tension_score' => 7,
        'scene_purpose' => 'turning_point',
        'value_shift' => 'peace → war',
        'pacing_feel' => 'brisk',
        'entry_hook_score' => 8,
        'sensory_grounding' => 4,
        'information_delivery' => 'organic',
    ]);
    Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 3,
        'hook_score' => 3,
        'exit_hook_score' => 3,
        'hook_type' => 'closed',
        'tension_score' => 6,
        'micro_tension_score' => 5,
        'scene_purpose' => 'deepening',
        'pacing_feel' => 'languid',
        'entry_hook_score' => 6,
        'sensory_grounding' => 3,
        'information_delivery' => 'mostly_organic',
    ]);

    $response = $this->get("/books/{$book->id}/dashboard");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('books/dashboard')
        ->has('health_metrics.attention_items')
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
        'weave_score' => 0,
        'scene_purpose_score' => 60,
        'tension_dynamics_score' => 70,
        'emotional_arc_score' => 65,
        'craft_score' => 75,
    ]);
    HealthSnapshot::factory()->for($book)->create([
        'recorded_at' => now()->toDateString(),
        'composite_score' => 78,
        'hooks_score' => 85,
        'pacing_score' => 70,
        'tension_score' => 75,
        'weave_score' => 0,
        'scene_purpose_score' => 70,
        'tension_dynamics_score' => 75,
        'emotional_arc_score' => 72,
        'craft_score' => 80,
    ]);

    $response = $this->get("/books/{$book->id}/dashboard");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('books/dashboard')
        ->has('health_history', 2)
        ->where('health_history.0.composite', 72)
        ->where('health_history.0.scene_purpose', 60)
        ->where('health_history.1.composite', 78)
        ->where('health_history.1.craft', 80)
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
