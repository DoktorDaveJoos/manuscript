<?php

use App\Ai\Agents\ChapterAnalyzer;
use App\Enums\EditorialSectionType;
use App\Jobs\RunEditorialReviewJob;
use App\Models\Book;
use App\Models\EditorialReview;
use App\Models\EditorialReviewSection;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;

/**
 * @return list<string>
 */
function editorialBatchedClasses(PendingBatch $batch): array
{
    return $batch->jobs->map(fn ($job) => class_basename($job))->all();
}

test('RunEditorialReviewJob batches one chapter job per chapter plus a finalize job', function () {
    Bus::fake();

    [$book, $chapters] = createBookWithChaptersForEditorial(3);
    $review = EditorialReview::factory()->for($book)->create(['status' => 'pending']);

    (new RunEditorialReviewJob($book, $review))->handle();

    Bus::assertBatched(function (PendingBatch $batch) {
        $classes = editorialBatchedClasses($batch);

        expect(collect($classes)->filter(fn ($c) => $c === 'AnalyzeReviewChapterJob'))->toHaveCount(3)
            ->and(collect($classes)->filter(fn ($c) => $c === 'FinalizeEditorialReviewJob'))->toHaveCount(1);

        // Finalize must be the terminal job (single-worker FIFO ordering).
        expect($classes[array_key_last($classes)])->toBe('FinalizeEditorialReviewJob');

        return true;
    });
});

test('RunEditorialReviewJob batches embed jobs for stale chapters and a style refresh before analysis', function () {
    Bus::fake();

    [$book, $chapters] = createBookWithChaptersForEditorial(3);
    $book->update(['writing_style' => ['tone' => 'wry']]);
    $chapters[0]->update(['prepared_content_hash' => 'stale-hash']);
    $chapters[2]->update(['prepared_content_hash' => null]);

    $review = EditorialReview::factory()->for($book)->create(['status' => 'pending']);

    (new RunEditorialReviewJob($book, $review))->handle();

    Bus::assertBatched(function (PendingBatch $batch) {
        $classes = collect(editorialBatchedClasses($batch));

        expect($classes->filter(fn ($c) => $c === 'EmbedReviewChapterJob'))->toHaveCount(2)
            ->and($classes->filter(fn ($c) => $c === 'RefreshWritingStyleJob'))->toHaveCount(1);

        // Embedding + style refresh must run before analysis (single-worker FIFO),
        // so the notes agent sees fresh style and retrieval sees fresh chunks.
        $firstAnalysis = $classes->search('AnalyzeReviewChapterJob');
        $lastPrep = $classes
            ->filter(fn ($c) => in_array($c, ['EmbedReviewChapterJob', 'RefreshWritingStyleJob'], true))
            ->keys()
            ->max();

        expect($lastPrep)->toBeLessThan($firstAnalysis);

        return true;
    });
});

test('RunEditorialReviewJob skips embed and style jobs when chapters are fresh and a style exists', function () {
    Bus::fake();

    [$book] = createBookWithChaptersForEditorial(2);
    $book->update(['writing_style' => ['tone' => 'wry']]);

    $review = EditorialReview::factory()->for($book)->create(['status' => 'pending']);

    (new RunEditorialReviewJob($book, $review))->handle();

    Bus::assertBatched(function (PendingBatch $batch) {
        $classes = editorialBatchedClasses($batch);

        expect($classes)->not->toContain('EmbedReviewChapterJob')
            ->and($classes)->not->toContain('RefreshWritingStyleJob');

        return true;
    });
});

test('RunEditorialReviewJob refreshes writing style when the book has none', function () {
    Bus::fake();

    [$book] = createBookWithChaptersForEditorial(2);
    $book->update(['writing_style' => null]);

    $review = EditorialReview::factory()->for($book)->create(['status' => 'pending']);

    (new RunEditorialReviewJob($book, $review))->handle();

    Bus::assertBatched(function (PendingBatch $batch) {
        $classes = editorialBatchedClasses($batch);

        expect($classes)->not->toContain('EmbedReviewChapterJob')
            ->and(collect($classes)->filter(fn ($c) => $c === 'RefreshWritingStyleJob'))->toHaveCount(1);

        return true;
    });
});

test('RunEditorialReviewJob marks the review analyzing with chapter totals', function () {
    Bus::fake();

    [$book] = createBookWithChaptersForEditorial(3);
    $review = EditorialReview::factory()->for($book)->create(['status' => 'pending']);

    (new RunEditorialReviewJob($book, $review))->handle();

    $review->refresh();

    expect($review->status)->toBe('analyzing')
        ->and($review->progress['phase'])->toBe('analyzing')
        ->and($review->progress['total_chapters'])->toBe(3)
        ->and($review->started_at)->not->toBeNull();
});

test('RunEditorialReviewJob marks review as failed when no AI provider configured', function () {
    Bus::fake();

    $book = Book::factory()->create();
    $review = EditorialReview::factory()->for($book)->create(['status' => 'pending']);

    (new RunEditorialReviewJob($book, $review))->handle();

    $review->refresh();

    expect($review->status)->toBe('failed')
        ->and($review->error_message)->toBe('No AI provider configured.');

    Bus::assertNothingBatched();
});

test('RunEditorialReviewJob runs the full decomposed pipeline end to end', function () {
    ChapterAnalyzer::fake(fn () => [
        'summary' => 'Chapter summary.',
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
    ]);
    fakeAllEditorialAgents();

    [$book] = createBookWithChaptersForEditorial(2);
    $review = EditorialReview::factory()->for($book)->create(['status' => 'pending']);

    // Sync queue: the dispatched batch (chapter jobs + finalize) runs inline.
    (new RunEditorialReviewJob($book, $review))->handle();

    $review->refresh();

    expect($review->status)->toBe('completed')
        ->and($review->completed_at)->not->toBeNull()
        ->and($review->batch_id)->not->toBeNull()
        ->and($review->overall_score)->toBe(75)
        ->and($review->executive_summary)->toContain('solid manuscript')
        ->and($review->chapterNotes()->count())->toBe(2)
        ->and($review->sections()->count())->toBe(8);

    foreach (EditorialSectionType::cases() as $sectionType) {
        $section = $review->sections()->where('type', $sectionType)->first();
        expect($section)->not->toBeNull()
            ->and($section->score)->toBe(72);
    }
});

test('EditorialSectionType enum has all eight cases', function () {
    $cases = EditorialSectionType::cases();

    expect($cases)->toHaveCount(8)
        ->and(collect($cases)->pluck('value')->all())->toBe([
            'plot', 'characters', 'pacing', 'narrative_voice',
            'themes', 'scene_craft', 'prose_style', 'chapter_notes',
        ]);
});

test('EditorialReview model has correct relationships', function () {
    [$book] = createBookWithChaptersForEditorial(1);
    $review = EditorialReview::factory()->for($book)->create();

    expect($review->book)->toBeInstanceOf(Book::class)
        ->and($review->sections)->toBeEmpty()
        ->and($review->chapterNotes)->toBeEmpty();
});

test('findingKey produces xxh128 length hash', function () {
    $key = EditorialReviewSection::findingKey('plot', 'Some finding description');

    expect(strlen($key))->toBe(32);
});
