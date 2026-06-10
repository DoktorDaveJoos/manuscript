<?php

use App\Ai\Agents\ChapterAnalyzer;
use App\Ai\Agents\EditorialNotesAgent;
use App\Ai\Agents\ManuscriptAnalyzer;
use App\Jobs\Editorial\AnalyzeReviewChapterJob;
use App\Models\EditorialReview;

test('AnalyzeReviewChapterJob creates a chapter note from the gap-fill agent', function () {
    fakeAllEditorialAgents();

    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $review = EditorialReview::factory()->for($book)->create(['status' => 'analyzing']);

    (new AnalyzeReviewChapterJob($book, $review, $chapters[0]->id, 1, 1))->handle();

    expect($review->chapterNotes()->count())->toBe(1);

    $note = $review->chapterNotes()->first();
    expect($note->chapter_id)->toBe($chapters[0]->id)
        ->and($note->notes)->toHaveKey('narrative_voice');
});

test('AnalyzeReviewChapterJob refreshes a stale chapter analysis before gap-fill', function () {
    ChapterAnalyzer::fake(fn () => [
        'summary' => 'Refreshed summary.',
        'key_events' => ['Event 1'],
        'characters_present' => ['John'],
        'tension_score' => 5,
        'micro_tension_score' => 4,
        'scene_purpose' => 'setup',
        'value_shift' => null,
        'emotional_state_open' => 'calm',
        'emotional_state_close' => 'worried',
        'emotional_shift_magnitude' => 3,
        'hook_score' => 6,
        'hook_type' => 'soft_hook',
        'hook_reasoning' => 'Moderate tension.',
        'entry_hook_score' => 5,
        'pacing_feel' => 'measured',
        'sensory_grounding' => 3,
        'information_delivery' => 'mixed',
    ]);
    ManuscriptAnalyzer::fake(fn () => ['score' => 7, 'findings' => [], 'recommendations' => []]);
    fakeAllEditorialAgents();

    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $chapter = $chapters[0];
    $chapter->update(['prepared_content_hash' => 'stale_hash']);

    $review = EditorialReview::factory()->for($book)->create(['status' => 'analyzing']);

    (new AnalyzeReviewChapterJob($book, $review, $chapter->id, 1, 1))->handle();

    $chapter->refresh();

    expect($chapter->summary)->toBe('Refreshed summary.')
        ->and($chapter->tension_score)->toBe(5)
        ->and($chapter->prepared_content_hash)->toBe($chapter->content_hash);

    ChapterAnalyzer::assertPrompted(fn ($prompt) => true);
});

test('AnalyzeReviewChapterJob runs manuscript analyses when refreshing a stale chapter', function () {
    ChapterAnalyzer::fake(fn () => [
        'summary' => 'Refreshed summary.',
        'key_events' => ['Event 1'],
        'characters_present' => ['John'],
        'tension_score' => 5,
        'micro_tension_score' => 4,
        'scene_purpose' => 'setup',
        'value_shift' => null,
        'emotional_state_open' => 'calm',
        'emotional_state_close' => 'worried',
        'emotional_shift_magnitude' => 3,
        'hook_score' => 6,
        'hook_type' => 'soft_hook',
        'hook_reasoning' => 'Moderate tension.',
        'entry_hook_score' => 5,
        'pacing_feel' => 'measured',
        'sensory_grounding' => 3,
        'information_delivery' => 'mixed',
    ]);
    ManuscriptAnalyzer::fake(fn () => ['score' => 7, 'findings' => [], 'recommendations' => []]);
    fakeAllEditorialAgents();

    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $chapter = $chapters[0];
    $chapter->update(['prepared_content_hash' => 'stale_hash']);

    $review = EditorialReview::factory()->for($book)->create(['status' => 'analyzing']);

    (new AnalyzeReviewChapterJob($book, $review, $chapter->id, 1, 1))->handle();

    $types = $chapter->analyses()->pluck('type')->map(fn ($type) => $type->value)->all();

    expect($types)->toContain('character_consistency')
        ->and($types)->toContain('plot_deviation');
});

test('AnalyzeReviewChapterJob skips manuscript analyses for a fresh chapter', function () {
    ManuscriptAnalyzer::fake(function () {
        throw new RuntimeException('ManuscriptAnalyzer must not run for fresh chapters');
    });
    fakeAllEditorialAgents();

    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $review = EditorialReview::factory()->for($book)->create(['status' => 'analyzing']);

    $job = new AnalyzeReviewChapterJob($book, $review, $chapters[0]->id, 1, 1);

    expect(fn () => $job->handle())->not->toThrow(Throwable::class);
    expect($review->chapterNotes()->count())->toBe(1);
});

test('AnalyzeReviewChapterJob skips gracefully when the notes agent throws', function () {
    EditorialNotesAgent::fake(function () {
        throw new RuntimeException('API error on chapter');
    });

    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $review = EditorialReview::factory()->for($book)->create(['status' => 'analyzing']);

    $job = new AnalyzeReviewChapterJob($book, $review, $chapters[0]->id, 1, 1);

    expect(fn () => $job->handle())->not->toThrow(Throwable::class);
    expect($review->chapterNotes()->count())->toBe(0);
});

test('AnalyzeReviewChapterJob reports its position in review progress', function () {
    fakeAllEditorialAgents();

    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $review = EditorialReview::factory()->for($book)->create(['status' => 'analyzing']);

    (new AnalyzeReviewChapterJob($book, $review, $chapters[0]->id, 2, 5))->handle();

    $review->refresh();

    expect($review->progress['phase'])->toBe('analyzing')
        ->and($review->progress['current_chapter'])->toBe(2)
        ->and($review->progress['total_chapters'])->toBe(5);
});

test('AnalyzeReviewChapterJob reuses an existing note when chapter content is unchanged', function () {
    EditorialNotesAgent::fake(function () {
        throw new RuntimeException('AI should not be called when reusing an unchanged note');
    });

    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $chapter = $chapters[0];

    // A note from a prior review, tagged with the chapter's current content hash.
    $priorReview = EditorialReview::factory()->for($book)->create(['status' => 'completed']);
    $priorReview->chapterNotes()->create([
        'chapter_id' => $chapter->id,
        'content_hash' => $chapter->content_hash,
        'notes' => [
            'narrative_voice' => ['pov' => 'reused-pov'],
            'themes' => [],
            'scene_craft' => [],
            'prose_style_patterns' => [],
        ],
    ]);

    $review = EditorialReview::factory()->for($book)->create(['status' => 'analyzing']);

    $job = new AnalyzeReviewChapterJob($book, $review, $chapter->id, 1, 1);
    expect(fn () => $job->handle())->not->toThrow(Throwable::class);

    $note = $review->chapterNotes()->first();
    expect($note)->not->toBeNull()
        ->and($note->notes['narrative_voice']['pov'])->toBe('reused-pov')
        ->and($note->content_hash)->toBe($chapter->content_hash);
});

test('AnalyzeReviewChapterJob stores the content hash on newly created notes', function () {
    fakeAllEditorialAgents();

    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $review = EditorialReview::factory()->for($book)->create(['status' => 'analyzing']);

    (new AnalyzeReviewChapterJob($book, $review, $chapters[0]->id, 1, 1))->handle();

    $note = $review->chapterNotes()->first();
    expect($note->content_hash)->toBe($chapters[0]->content_hash)
        ->and($note->notes)->toHaveKey('narrative_voice');
});

test('AnalyzeReviewChapterJob re-runs analysis when chapter content changed', function () {
    fakeAllEditorialAgents();

    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $chapter = $chapters[0];

    // Prior note tagged with a stale hash — must NOT be reused.
    $priorReview = EditorialReview::factory()->for($book)->create(['status' => 'completed']);
    $priorReview->chapterNotes()->create([
        'chapter_id' => $chapter->id,
        'content_hash' => 'a-stale-hash',
        'notes' => [
            'narrative_voice' => ['pov' => 'stale-pov'],
            'themes' => [],
            'scene_craft' => [],
            'prose_style_patterns' => [],
        ],
    ]);

    $review = EditorialReview::factory()->for($book)->create(['status' => 'analyzing']);
    (new AnalyzeReviewChapterJob($book, $review, $chapter->id, 1, 1))->handle();

    $note = $review->chapterNotes()->first();
    expect($note->notes['narrative_voice']['pov'])->toBe('third-person limited')
        ->and($note->content_hash)->toBe($chapter->content_hash);
});
