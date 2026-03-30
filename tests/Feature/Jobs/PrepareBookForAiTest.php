<?php

use App\Ai\Agents\ChapterAnalyzer;
use App\Ai\Agents\EntityConsolidator;
use App\Ai\Agents\EntityExtractor;
use App\Ai\Agents\StoryBibleBuilder;
use App\Jobs\PrepareBookForAi;
use App\Models\AiPreparation;
use App\Models\Book;

beforeEach(function () {
    EntityConsolidator::fake(function () {
        return ['character_merges' => [], 'entity_merges' => []];
    });
});

test('prepare book for ai runs full 7-phase pipeline', function () {
    ChapterAnalyzer::fake(function () {
        return [
            'summary' => 'A test chapter summary.',
            'key_events' => ['Event 1'],
            'characters_present' => ['John'],
            'tension_score' => 7,
            'micro_tension_score' => 6,
            'scene_purpose' => 'turning_point',
            'value_shift' => 'safety → danger',
            'emotional_state_open' => 'cautious',
            'emotional_state_close' => 'terrified',
            'emotional_shift_magnitude' => 8,
            'hook_score' => 8,
            'hook_type' => 'cliffhanger',
            'hook_reasoning' => 'Strong ending.',
            'entry_hook_score' => 7,
            'pacing_feel' => 'brisk',
            'sensory_grounding' => 4,
            'information_delivery' => 'organic',
            'plot_points' => [
                ['title' => 'Plot point 1', 'description' => 'Something happened', 'type' => 'conflict'],
            ],
        ];
    });

    EntityExtractor::fake(function () {
        return [
            'characters' => [
                ['name' => 'John', 'aliases' => null, 'description' => 'The hero', 'role' => 'protagonist'],
            ],
            'entities' => [],
        ];
    });

    StoryBibleBuilder::fake(function () {
        return [
            'characters' => [['name' => 'John', 'role' => 'protagonist']],
            'setting' => [['location' => 'Castle']],
            'plot_outline' => [['description' => 'Hero journey']],
            'themes' => ['Courage'],
            'style_rules' => ['Third person'],
            'genre_rules' => ['Fantasy'],
            'timeline' => [['event' => 'Arrival']],
        ];
    });

    [$book, $chapters, $preparation] = createBookWithChapters(2);

    $job = new PrepareBookForAi($book, $preparation);
    $job->handle();

    $preparation->refresh();

    expect($preparation->status)->toBe('completed')
        ->and($preparation->completed_phases)->toContain('chunking')
        ->and($preparation->completed_phases)->toContain('chapter_analysis')
        ->and($preparation->completed_phases)->toContain('entity_extraction')
        ->and($preparation->completed_phases)->toContain('story_bible')
        ->and($preparation->completed_phases)->toContain('health_analysis');

    // Verify chapters got analyzed and marked as prepared
    $chapters[0]->refresh();
    expect($chapters[0]->summary)->toBe('A test chapter summary.')
        ->and($chapters[0]->tension_score)->toBe(7)
        ->and($chapters[0]->hook_score)->toBe(8)
        ->and($chapters[0]->hook_type)->toBe('cliffhanger')
        ->and($chapters[0]->prepared_content_hash)->toBe($chapters[0]->content_hash)
        ->and($chapters[0]->ai_prepared_at)->not->toBeNull();

    // Verify characters were extracted
    expect($book->characters()->count())->toBeGreaterThanOrEqual(1);

    // Verify story bible was built
    $book->refresh();
    expect($book->story_bible)->not->toBeNull()
        ->and($book->story_bible['characters'])->toBeArray();

    // AI plot point extraction was removed — plot points are manual-only now
});

test('prepare book for ai tracks phase progress', function () {
    ChapterAnalyzer::fake(function () {
        return [
            'summary' => 'Summary.',
            'key_events' => [],
            'characters_present' => [],
            'tension_score' => 5,
            'micro_tension_score' => 4,
            'scene_purpose' => 'deepening',
            'value_shift' => null,
            'emotional_state_open' => 'calm',
            'emotional_state_close' => 'calm',
            'emotional_shift_magnitude' => 1,
            'hook_score' => 5,
            'hook_type' => 'closed',
            'hook_reasoning' => 'OK.',
            'entry_hook_score' => 5,
            'pacing_feel' => 'measured',
            'sensory_grounding' => 2,
            'information_delivery' => 'mixed',
            'plot_points' => [],
        ];
    });

    EntityExtractor::fake(function () {
        return ['characters' => [], 'entities' => []];
    });

    StoryBibleBuilder::fake(function () {
        return [
            'characters' => [],
            'setting' => [],
            'plot_outline' => [],
            'themes' => [],
            'style_rules' => [],
            'genre_rules' => [],
            'timeline' => [],
        ];
    });

    [$book, $chapters, $preparation] = createBookWithChapters(1);

    $job = new PrepareBookForAi($book, $preparation);
    $job->handle();

    $preparation->refresh();

    expect($preparation->status)->toBe('completed')
        ->and($preparation->completed_phases)->toBeArray()
        ->and(count($preparation->completed_phases))->toBeGreaterThanOrEqual(5);
});

test('prepare book for ai isolates per-chapter failures', function () {
    $callCount = 0;
    ChapterAnalyzer::fake(function () use (&$callCount) {
        $callCount++;
        if ($callCount === 1) {
            throw new RuntimeException('API timeout');
        }

        return [
            'summary' => 'Second chapter summary.',
            'key_events' => [],
            'characters_present' => [],
            'tension_score' => 6,
            'micro_tension_score' => 5,
            'scene_purpose' => 'setup',
            'value_shift' => null,
            'emotional_state_open' => 'calm',
            'emotional_state_close' => 'tense',
            'emotional_shift_magnitude' => 4,
            'hook_score' => 7,
            'hook_type' => 'soft_hook',
            'hook_reasoning' => 'OK.',
            'entry_hook_score' => 6,
            'pacing_feel' => 'brisk',
            'sensory_grounding' => 3,
            'information_delivery' => 'organic',
            'plot_points' => [],
        ];
    });

    EntityExtractor::fake(function () {
        return ['characters' => [], 'entities' => []];
    });

    StoryBibleBuilder::fake(function () {
        return [
            'characters' => [],
            'setting' => [],
            'plot_outline' => [],
            'themes' => [],
            'style_rules' => [],
            'genre_rules' => [],
            'timeline' => [],
        ];
    });

    [$book, $chapters, $preparation] = createBookWithChapters(2);

    $job = new PrepareBookForAi($book, $preparation);
    $job->handle();

    $preparation->refresh();

    // Should still complete despite one chapter failing
    expect($preparation->status)->toBe('completed')
        ->and($preparation->phase_errors)->toBeArray()
        ->and(count($preparation->phase_errors))->toBeGreaterThanOrEqual(1);

    // Find the chapter_analysis error specifically
    $chapterErrors = collect($preparation->phase_errors)->where('phase', 'chapter_analysis');
    expect($chapterErrors)->not->toBeEmpty();

    // Second chapter should still have been analyzed and marked as prepared
    $chapters[1]->refresh();
    expect($chapters[1]->summary)->toBe('Second chapter summary.')
        ->and($chapters[1]->prepared_content_hash)->toBe($chapters[1]->content_hash);

    // First chapter (failed) should NOT be marked as prepared
    $chapters[0]->refresh();
    expect($chapters[0]->prepared_content_hash)->toBeNull();
});

test('prepare book for ai fails when api key not configured', function () {
    $book = Book::factory()->create();
    $preparation = AiPreparation::create([
        'book_id' => $book->id,
        'status' => 'pending',
    ]);

    $job = new PrepareBookForAi($book, $preparation);
    $job->handle();

    $preparation->refresh();

    expect($preparation->status)->toBe('failed')
        ->and($preparation->error_message)->toContain('No AI provider configured');
});
