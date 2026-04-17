<?php

use App\Jobs\Preparation\AnalyzeChapter;
use App\Jobs\Preparation\BuildStoryBible;
use App\Jobs\Preparation\ConsolidateEntities;
use App\Jobs\Preparation\ExtractWritingStyle;
use App\Models\AppSetting;
use App\Models\Book;
use App\Models\License;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    AppSetting::clearCache();
    License::factory()->create();
    AppSetting::set('show_ai_features', true);
});

test('retry endpoint dispatches targeted jobs and clears matching errors', function () {
    Bus::fake();
    [$book, $chapters, $preparation] = createBookWithChapters(2);

    $preparation->update([
        'status' => 'completed',
        'phase_errors' => [
            ['phase' => 'chapter_analysis', 'chapter' => $chapters[0]->title, 'chapter_id' => $chapters[0]->id, 'error' => 'timeout'],
            ['phase' => 'entity_extraction', 'chapter' => $chapters[1]->title, 'chapter_id' => $chapters[1]->id, 'error' => 'timeout'],
            ['phase' => 'writing_style', 'chapter' => null, 'chapter_id' => null, 'error' => 'timeout'],
            ['phase' => 'story_bible', 'chapter' => null, 'chapter_id' => null, 'error' => 'timeout'],
        ],
    ]);

    $response = $this->postJson(route('books.ai.prepare.retry', $book));

    $response->assertOk()
        ->assertJsonPath('status', 'running')
        ->assertJsonPath('current_phase', 'retry');

    Bus::assertBatched(function ($batch) {
        $jobs = collect($batch->jobs);

        expect($jobs->filter(fn ($j) => $j instanceof AnalyzeChapter))->toHaveCount(2);
        expect($jobs->contains(fn ($j) => $j instanceof ExtractWritingStyle))->toBeTrue();
        expect($jobs->contains(fn ($j) => $j instanceof BuildStoryBible))->toBeTrue();

        return true;
    });

    $preparation->refresh();
    expect($preparation->phase_errors)->toBeArray()->toBeEmpty()
        ->and($preparation->status)->toBe('running')
        ->and($preparation->current_phase)->toBe('retry');
});

test('retry deduplicates per chapter when multiple phases failed for the same chapter', function () {
    Bus::fake();
    [$book, $chapters, $preparation] = createBookWithChapters(2);

    $preparation->update([
        'status' => 'completed',
        'phase_errors' => [
            ['phase' => 'chapter_analysis', 'chapter' => $chapters[0]->title, 'chapter_id' => $chapters[0]->id, 'error' => 'err1'],
            ['phase' => 'entity_extraction', 'chapter' => $chapters[0]->title, 'chapter_id' => $chapters[0]->id, 'error' => 'err2'],
            ['phase' => 'manuscript_analysis', 'chapter' => $chapters[0]->title, 'chapter_id' => $chapters[0]->id, 'error' => 'err3'],
        ],
    ]);

    $this->postJson(route('books.ai.prepare.retry', $book))->assertOk();

    Bus::assertBatched(function ($batch) {
        $chapterJobs = collect($batch->jobs)->filter(fn ($j) => $j instanceof AnalyzeChapter);
        expect($chapterJobs)->toHaveCount(1);

        return true;
    });
});

test('retry groups entity_extraction with null chapter into ConsolidateEntities', function () {
    Bus::fake();
    [$book, , $preparation] = createBookWithChapters(1);

    $preparation->update([
        'status' => 'completed',
        'phase_errors' => [
            ['phase' => 'entity_extraction', 'chapter' => null, 'chapter_id' => null, 'error' => 'consolidation failed'],
        ],
    ]);

    $this->postJson(route('books.ai.prepare.retry', $book))->assertOk();

    Bus::assertBatched(function ($batch) {
        $jobs = collect($batch->jobs);
        expect($jobs->contains(fn ($j) => $j instanceof ConsolidateEntities))->toBeTrue();

        return true;
    });
});

test('retry returns 422 when there is nothing to retry', function () {
    [$book, , $preparation] = createBookWithChapters(1);

    $preparation->update([
        'status' => 'completed',
        'phase_errors' => [],
    ]);

    $this->postJson(route('books.ai.prepare.retry', $book))
        ->assertStatus(422)
        ->assertJsonPath('message', 'Nothing to retry.');
});

test('retry returns 404 when no preparation exists', function () {
    $book = Book::factory()->withAi()->create();

    $this->postJson(route('books.ai.prepare.retry', $book))
        ->assertStatus(404);
});

test('clearPhaseErrors only removes matching entries and leaves others', function () {
    [$book, $chapters, $preparation] = createBookWithChapters(2);

    $preparation->update([
        'phase_errors' => [
            ['phase' => 'chapter_analysis', 'chapter' => $chapters[0]->title, 'chapter_id' => $chapters[0]->id, 'error' => 'a'],
            ['phase' => 'entity_extraction', 'chapter' => $chapters[1]->title, 'chapter_id' => $chapters[1]->id, 'error' => 'b'],
            ['phase' => 'writing_style', 'chapter' => null, 'chapter_id' => null, 'error' => 'c'],
        ],
    ]);

    $preparation->clearPhaseErrors([
        ['phase' => 'writing_style', 'chapter_id' => null],
    ]);

    $remaining = $preparation->fresh()->phase_errors;
    expect($remaining)->toHaveCount(2)
        ->and(collect($remaining)->pluck('phase')->all())->toBe(['chapter_analysis', 'entity_extraction']);
});
