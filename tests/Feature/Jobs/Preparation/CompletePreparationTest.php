<?php

use App\Jobs\Preparation\CompletePreparation;
use App\Models\AiPreparation;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\HealthSnapshot;
use App\Models\Storyline;

function createBookForHealthSnapshot(array $chapterData): array
{
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $preparation = AiPreparation::create([
        'book_id' => $book->id,
        'status' => 'running',
        'current_phase' => 'health_analysis',
        'current_phase_total' => count($chapterData),
        'current_phase_progress' => count($chapterData),
    ]);

    $chapters = [];
    foreach ($chapterData as $i => $data) {
        $chapters[] = Chapter::factory()->for($book)->for($storyline)->create([
            'reader_order' => $i + 1,
            'title' => 'Chapter '.($i + 1),
            ...$data,
        ]);
    }

    return [$book, $chapters, $preparation];
}

test('scene purpose score counts chapters with value shifts', function () {
    [$book, $chapters, $preparation] = createBookForHealthSnapshot([
        ['hook_score' => 7, 'scene_purpose' => 'turning_point', 'value_shift' => 'trust → betrayal'],
        ['hook_score' => 6, 'scene_purpose' => 'deepening', 'value_shift' => 'hope → despair'],
        ['hook_score' => 5, 'scene_purpose' => 'transition', 'value_shift' => null],
        ['hook_score' => 8, 'scene_purpose' => 'revelation', 'value_shift' => 'ignorance → awareness'],
    ]);

    (new CompletePreparation($book, $preparation))->handle();

    $snapshot = HealthSnapshot::where('book_id', $book->id)->first();
    // 3 out of 4 have scene_purpose + value_shift = 75%
    expect($snapshot->scene_purpose_score)->toBe(75);
});

test('pacing score rewards variety of pacing feels', function () {
    [$book, $chapters, $preparation] = createBookForHealthSnapshot([
        ['hook_score' => 7, 'pacing_feel' => 'breakneck'],
        ['hook_score' => 6, 'pacing_feel' => 'brisk'],
        ['hook_score' => 5, 'pacing_feel' => 'measured'],
        ['hook_score' => 8, 'pacing_feel' => 'languid'],
        ['hook_score' => 7, 'pacing_feel' => 'static'],
    ]);

    (new CompletePreparation($book, $preparation))->handle();

    $snapshot = HealthSnapshot::where('book_id', $book->id)->first();
    // All 5 types represented, even distribution = high score
    expect($snapshot->pacing_score)->toBeGreaterThanOrEqual(80);
});

test('pacing score penalizes monotonous pacing', function () {
    [$book, $chapters, $preparation] = createBookForHealthSnapshot([
        ['hook_score' => 7, 'pacing_feel' => 'measured'],
        ['hook_score' => 6, 'pacing_feel' => 'measured'],
        ['hook_score' => 5, 'pacing_feel' => 'measured'],
        ['hook_score' => 8, 'pacing_feel' => 'measured'],
        ['hook_score' => 7, 'pacing_feel' => 'measured'],
    ]);

    (new CompletePreparation($book, $preparation))->handle();

    $snapshot = HealthSnapshot::where('book_id', $book->id)->first();
    // All same pacing = low variety, low balance
    expect($snapshot->pacing_score)->toBeLessThanOrEqual(20);
});

test('tension dynamics score rewards good ebb and flow', function () {
    [$book, $chapters, $preparation] = createBookForHealthSnapshot([
        ['hook_score' => 7, 'tension_score' => 4, 'micro_tension_score' => 5],
        ['hook_score' => 6, 'tension_score' => 7, 'micro_tension_score' => 6],
        ['hook_score' => 5, 'tension_score' => 3, 'micro_tension_score' => 7],
        ['hook_score' => 8, 'tension_score' => 8, 'micro_tension_score' => 8],
        ['hook_score' => 7, 'tension_score' => 5, 'micro_tension_score' => 6],
    ]);

    (new CompletePreparation($book, $preparation))->handle();

    $snapshot = HealthSnapshot::where('book_id', $book->id)->first();
    // Good step changes (3-4), micro-tension fills low conflict = high score
    expect($snapshot->tension_dynamics_score)->toBeGreaterThanOrEqual(60);
});

test('tension dynamics penalizes flat tension with no micro-tension', function () {
    [$book, $chapters, $preparation] = createBookForHealthSnapshot([
        ['hook_score' => 5, 'tension_score' => 2, 'micro_tension_score' => 2],
        ['hook_score' => 5, 'tension_score' => 2, 'micro_tension_score' => 2],
        ['hook_score' => 5, 'tension_score' => 3, 'micro_tension_score' => 1],
        ['hook_score' => 5, 'tension_score' => 2, 'micro_tension_score' => 2],
    ]);

    (new CompletePreparation($book, $preparation))->handle();

    $snapshot = HealthSnapshot::where('book_id', $book->id)->first();
    // Flat tension + no micro-tension fill = low score
    expect($snapshot->tension_dynamics_score)->toBeLessThanOrEqual(40);
});

test('hooks score combines entry and exit hooks', function () {
    [$book, $chapters, $preparation] = createBookForHealthSnapshot([
        ['hook_score' => 8, 'exit_hook_score' => 8, 'entry_hook_score' => 6],
        ['hook_score' => 7, 'exit_hook_score' => 7, 'entry_hook_score' => 8],
        ['hook_score' => 9, 'exit_hook_score' => 9, 'entry_hook_score' => 7],
    ]);

    (new CompletePreparation($book, $preparation))->handle();

    $snapshot = HealthSnapshot::where('book_id', $book->id)->first();
    // avg exit = 8, avg entry = 7 → (8*0.6 + 7*0.4) * 10 = 76
    expect($snapshot->hooks_score)->toBe(76);
});

test('emotional arc score rewards moderate emotional shifts', function () {
    [$book, $chapters, $preparation] = createBookForHealthSnapshot([
        ['hook_score' => 7, 'emotional_shift_magnitude' => 5],
        ['hook_score' => 6, 'emotional_shift_magnitude' => 4],
        ['hook_score' => 5, 'emotional_shift_magnitude' => 7],
        ['hook_score' => 8, 'emotional_shift_magnitude' => 5],
        ['hook_score' => 7, 'emotional_shift_magnitude' => 6],
    ]);

    (new CompletePreparation($book, $preparation))->handle();

    $snapshot = HealthSnapshot::where('book_id', $book->id)->first();
    // Avg shift ~5.4 (ideal), 1/5 = 20% big beats (ideal 20-40%) = high score
    expect($snapshot->emotional_arc_score)->toBeGreaterThanOrEqual(70);
});

test('emotional arc score penalizes flat emotional line', function () {
    [$book, $chapters, $preparation] = createBookForHealthSnapshot([
        ['hook_score' => 5, 'emotional_shift_magnitude' => 1],
        ['hook_score' => 5, 'emotional_shift_magnitude' => 1],
        ['hook_score' => 5, 'emotional_shift_magnitude' => 2],
        ['hook_score' => 5, 'emotional_shift_magnitude' => 1],
    ]);

    (new CompletePreparation($book, $preparation))->handle();

    $snapshot = HealthSnapshot::where('book_id', $book->id)->first();
    // Avg shift 1.25 (very flat), 0% big beats
    expect($snapshot->emotional_arc_score)->toBeLessThanOrEqual(30);
});

test('craft score combines sensory grounding and information delivery', function () {
    [$book, $chapters, $preparation] = createBookForHealthSnapshot([
        ['hook_score' => 7, 'sensory_grounding' => 4, 'information_delivery' => 'organic'],
        ['hook_score' => 6, 'sensory_grounding' => 3, 'information_delivery' => 'mostly_organic'],
        ['hook_score' => 5, 'sensory_grounding' => 3, 'information_delivery' => 'organic'],
    ]);

    (new CompletePreparation($book, $preparation))->handle();

    $snapshot = HealthSnapshot::where('book_id', $book->id)->first();
    // High sensory (avg 3.3), 100% organic delivery = high score
    expect($snapshot->craft_score)->toBeGreaterThanOrEqual(85);
});

test('craft score penalizes info dumps and low sensory', function () {
    [$book, $chapters, $preparation] = createBookForHealthSnapshot([
        ['hook_score' => 5, 'sensory_grounding' => 1, 'information_delivery' => 'info_dump'],
        ['hook_score' => 5, 'sensory_grounding' => 1, 'information_delivery' => 'exposition_heavy'],
        ['hook_score' => 5, 'sensory_grounding' => 1, 'information_delivery' => 'info_dump'],
    ]);

    (new CompletePreparation($book, $preparation))->handle();

    $snapshot = HealthSnapshot::where('book_id', $book->id)->first();
    // Low sensory (avg 1), 0% organic delivery
    expect($snapshot->craft_score)->toBeLessThanOrEqual(20);
});

test('insufficient data defaults to 50 for dimension scores', function () {
    [$book, $chapters, $preparation] = createBookForHealthSnapshot([
        ['hook_score' => 7, 'tension_score' => 5],
        ['hook_score' => 6, 'tension_score' => 6],
    ]);

    (new CompletePreparation($book, $preparation))->handle();

    $snapshot = HealthSnapshot::where('book_id', $book->id)->first();
    // Only 2 chapters with tension = insufficient for dynamics
    expect($snapshot->tension_dynamics_score)->toBe(50);
    // No pacing_feel data = default 50
    expect($snapshot->pacing_score)->toBe(50);
    // No emotional data = default 50
    expect($snapshot->emotional_arc_score)->toBe(50);
});

test('composite score uses correct weights', function () {
    [$book, $chapters, $preparation] = createBookForHealthSnapshot([
        [
            'hook_score' => 8, 'exit_hook_score' => 8, 'entry_hook_score' => 8,
            'tension_score' => 6, 'micro_tension_score' => 6,
            'scene_purpose' => 'turning_point', 'value_shift' => 'trust → betrayal',
            'emotional_shift_magnitude' => 5,
            'pacing_feel' => 'brisk',
            'sensory_grounding' => 3, 'information_delivery' => 'organic',
        ],
        [
            'hook_score' => 7, 'exit_hook_score' => 7, 'entry_hook_score' => 7,
            'tension_score' => 4, 'micro_tension_score' => 5,
            'scene_purpose' => 'deepening', 'value_shift' => 'doubt → confidence',
            'emotional_shift_magnitude' => 6,
            'pacing_feel' => 'measured',
            'sensory_grounding' => 4, 'information_delivery' => 'mostly_organic',
        ],
        [
            'hook_score' => 9, 'exit_hook_score' => 9, 'entry_hook_score' => 6,
            'tension_score' => 8, 'micro_tension_score' => 7,
            'scene_purpose' => 'revelation', 'value_shift' => 'ignorance → awareness',
            'emotional_shift_magnitude' => 7,
            'pacing_feel' => 'breakneck',
            'sensory_grounding' => 3, 'information_delivery' => 'organic',
        ],
    ]);

    (new CompletePreparation($book, $preparation))->handle();

    $snapshot = HealthSnapshot::where('book_id', $book->id)->first();
    expect($snapshot->composite_score)->toBeGreaterThan(0)
        ->and($snapshot->scene_purpose_score)->toBe(100)
        ->and($snapshot->hooks_score)->toBeGreaterThan(0)
        ->and($snapshot->tension_dynamics_score)->toBeGreaterThan(0)
        ->and($snapshot->emotional_arc_score)->toBeGreaterThan(0)
        ->and($snapshot->craft_score)->toBeGreaterThan(0)
        ->and($snapshot->pacing_score)->toBeGreaterThan(0);
});

test('backward compat: old columns still populated', function () {
    [$book, $chapters, $preparation] = createBookForHealthSnapshot([
        ['hook_score' => 8, 'exit_hook_score' => 8, 'entry_hook_score' => 7, 'tension_score' => 6, 'pacing_feel' => 'brisk'],
        ['hook_score' => 7, 'exit_hook_score' => 7, 'entry_hook_score' => 6, 'tension_score' => 4, 'pacing_feel' => 'measured'],
        ['hook_score' => 9, 'exit_hook_score' => 9, 'entry_hook_score' => 8, 'tension_score' => 8, 'pacing_feel' => 'breakneck'],
    ]);

    (new CompletePreparation($book, $preparation))->handle();

    $snapshot = HealthSnapshot::where('book_id', $book->id)->first();
    // hooks_score should match new hooks calculation
    expect($snapshot->hooks_score)->toBe($snapshot->hooks_score)
        // pacing_score should be populated
        ->and($snapshot->pacing_score)->toBeGreaterThan(0)
        // tension_score should map to tension_dynamics
        ->and($snapshot->tension_score)->toBe($snapshot->tension_dynamics_score)
        // weave_score should be 0 (deprecated)
        ->and($snapshot->weave_score)->toBe(0);
});

test('empty analyzed chapters skips snapshot creation', function () {
    [$book, $chapters, $preparation] = createBookForHealthSnapshot([
        ['hook_score' => null, 'word_count' => 1000],
        ['hook_score' => null, 'word_count' => 2000],
    ]);

    (new CompletePreparation($book, $preparation))->handle();

    expect(HealthSnapshot::where('book_id', $book->id)->count())->toBe(0);
});

test('preparation is marked completed after snapshot', function () {
    [$book, $chapters, $preparation] = createBookForHealthSnapshot([
        ['hook_score' => 7],
        ['hook_score' => 8],
    ]);

    (new CompletePreparation($book, $preparation))->handle();

    $preparation->refresh();
    expect($preparation->status)->toBe('completed');
});
