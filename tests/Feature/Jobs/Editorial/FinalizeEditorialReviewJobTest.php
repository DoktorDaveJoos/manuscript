<?php

use App\Ai\Agents\EditorialSynthesisAgent;
use App\Jobs\Editorial\FinalizeEditorialReviewJob;
use App\Models\EditorialReview;

/**
 * Seed a chapter note so the finalize guard ("no chapter content") passes.
 */
function seedEditorialChapterNote(EditorialReview $review, int $chapterId): void
{
    $review->chapterNotes()->create([
        'chapter_id' => $chapterId,
        'notes' => [
            'narrative_voice' => ['pov' => 'third', 'tense' => 'past', 'observations' => [], 'tone_notes' => 'x'],
            'themes' => ['motifs' => [], 'observations' => []],
            'scene_craft' => ['scene_purposes' => [], 'show_vs_tell' => [], 'sensory_detail' => 'x'],
            'prose_style_patterns' => ['sentence_rhythm' => 'x', 'repetitions' => [], 'vocabulary_notes' => 'x'],
        ],
    ]);
}

test('FinalizeEditorialReviewJob synthesizes all sections and an executive summary', function () {
    fakeAllEditorialAgents();

    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $review = EditorialReview::factory()->for($book)->create(['status' => 'analyzing']);
    seedEditorialChapterNote($review, $chapters[0]->id);

    (new FinalizeEditorialReviewJob($book, $review))->handle();

    $review->refresh();

    expect($review->status)->toBe('completed')
        ->and($review->completed_at)->not->toBeNull()
        ->and($review->overall_score)->toBe(75)
        ->and($review->executive_summary)->toContain('solid manuscript')
        ->and($review->sections()->count())->toBe(8);
});

test('FinalizeEditorialReviewJob persists per-section strengths', function () {
    fakeAllEditorialAgents();

    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $review = EditorialReview::factory()->for($book)->create(['status' => 'analyzing']);
    seedEditorialChapterNote($review, $chapters[0]->id);

    (new FinalizeEditorialReviewJob($book, $review))->handle();

    $review->refresh();

    expect($review->sections)->toHaveCount(8);

    $review->sections->each(function ($section) {
        expect($section->strengths)->toBeArray()
            ->and($section->strengths[0])->toContain('midpoint reversal');
    });
});

test('FinalizeEditorialReviewJob marks review as failed when there are no chapter notes', function () {
    fakeAllEditorialAgents();

    [$book] = createBookWithChaptersForEditorial(1);
    $review = EditorialReview::factory()->for($book)->create(['status' => 'analyzing']);

    (new FinalizeEditorialReviewJob($book, $review))->handle();

    $review->refresh();

    expect($review->status)->toBe('failed')
        ->and($review->error_message)->toContain('No chapter content')
        ->and($review->sections()->count())->toBe(0);
});

test('FinalizeEditorialReviewJob marks review as failed on a catastrophic synthesis error', function () {
    EditorialSynthesisAgent::fake(function () {
        throw new RuntimeException('Fatal synthesis error');
    });

    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $review = EditorialReview::factory()->for($book)->create(['status' => 'analyzing']);
    seedEditorialChapterNote($review, $chapters[0]->id);

    (new FinalizeEditorialReviewJob($book, $review))->handle();

    $review->refresh();

    expect($review->status)->toBe('failed')
        ->and($review->error_message)->toContain('Fatal synthesis error');
});

test('FinalizeEditorialReviewJob reports synthesizing progress', function () {
    fakeAllEditorialAgents();

    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $review = EditorialReview::factory()->for($book)->create(['status' => 'analyzing']);
    seedEditorialChapterNote($review, $chapters[0]->id);

    (new FinalizeEditorialReviewJob($book, $review))->handle();

    $review->refresh();

    expect($review->progress['phase'])->toBe('synthesizing');
});
