<?php

use App\Enums\PreparationStep;
use App\Jobs\Preparation\AnalyzeChapter;
use App\Jobs\Preparation\CompletePreparation;
use App\Jobs\PrepareBookForAi;
use App\Models\License;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    License::factory()->create();
});

/**
 * @return list<string>
 */
function batchedClasses(PendingBatch $batch): array
{
    return $batch->jobs->map(fn ($job) => class_basename($job))->all();
}

it('omits wiki and story bible jobs when those steps are not selected', function () {
    Bus::fake();
    [$book, , $preparation] = createBookWithChapters(2);
    $preparation->update(['steps' => ['chapter_analysis']]);

    (new PrepareBookForAi($book, $preparation))->handle();

    Bus::assertBatched(function (PendingBatch $batch) {
        $classes = batchedClasses($batch);

        expect($classes)->toContain('AnalyzeChapter')
            ->and($classes)->not->toContain('ConsolidateEntities')
            ->and($classes)->not->toContain('BuildStoryBible')
            ->and($classes)->not->toContain('ExtractWritingStyle')
            ->and($classes)->not->toContain('ChunkAndEmbedChapter');

        $analyze = $batch->jobs->first(fn ($job) => $job instanceof AnalyzeChapter);
        expect($analyze->runAnalysis)->toBeTrue()
            ->and($analyze->runEntities)->toBeFalse();

        return true;
    });
});

it('includes every pipeline job when all steps are selected', function () {
    Bus::fake();
    [$book, , $preparation] = createBookWithChapters(2);
    $preparation->update(['steps' => PreparationStep::values()]);

    (new PrepareBookForAi($book, $preparation))->handle();

    Bus::assertBatched(function (PendingBatch $batch) {
        $classes = batchedClasses($batch);

        foreach (['ChunkAndEmbedChapter', 'ExtractWritingStyle', 'AnalyzeChapter', 'ConsolidateEntities', 'BuildStoryBible', 'CompletePreparation'] as $expected) {
            expect($classes)->toContain($expected);
        }

        $analyze = $batch->jobs->first(fn ($job) => $job instanceof AnalyzeChapter);
        expect($analyze->runAnalysis)->toBeTrue()
            ->and($analyze->runEntities)->toBeTrue();

        return true;
    });
});

it('treats a null steps value as all steps for backward compatibility', function () {
    Bus::fake();
    [$book, , $preparation] = createBookWithChapters(1);
    $preparation->update(['steps' => null]);

    (new PrepareBookForAi($book, $preparation))->handle();

    Bus::assertBatched(fn (PendingBatch $batch) => collect(batchedClasses($batch))->contains('BuildStoryBible'));
});

it('skips the health snapshot in completion when health is not selected', function () {
    Bus::fake();
    [$book, , $preparation] = createBookWithChapters(1);
    $preparation->update(['steps' => ['chapter_analysis']]);

    (new PrepareBookForAi($book, $preparation))->handle();

    Bus::assertBatched(function (PendingBatch $batch) {
        $complete = $batch->jobs->first(fn ($job) => $job instanceof CompletePreparation);
        expect($complete)->not->toBeNull()
            ->and($complete->runHealthSnapshot)->toBeFalse();

        return true;
    });
});

it('still runs singleton steps when no chapters are dirty', function () {
    Bus::fake();
    [$book, $chapters, $preparation] = createBookWithChapters(1);

    foreach ($chapters as $chapter) {
        $chapter->update(['prepared_content_hash' => $chapter->content_hash]);
    }

    $preparation->update(['steps' => ['chapter_analysis', 'story_bible']]);

    (new PrepareBookForAi($book, $preparation))->handle();

    Bus::assertBatched(fn (PendingBatch $batch) => collect(batchedClasses($batch))->contains('BuildStoryBible'));
});
