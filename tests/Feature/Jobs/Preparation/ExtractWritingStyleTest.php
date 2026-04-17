<?php

use App\Jobs\Preparation\ExtractWritingStyle;
use App\Models\AiPreparation;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Storyline;
use App\Services\WritingStyleService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Exceptions;

function seedBookForStyle(): array
{
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 1,
        'title' => 'Chapter 1',
    ]);
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => '<p>'.fake()->paragraphs(3, true).'</p>',
    ]);

    $preparation = AiPreparation::create([
        'book_id' => $book->id,
        'status' => 'running',
        'current_phase' => 'writing_style',
        'current_phase_total' => 1,
        'current_phase_progress' => 0,
    ]);

    return [$book, $preparation];
}

test('extract writing style reports non-transient errors to Sentry and records them', function () {
    Exceptions::fake();
    [$book, $preparation] = seedBookForStyle();

    $service = $this->mock(WritingStyleService::class);
    $service->shouldReceive('extract')->once()->andThrow(new RuntimeException('OpenRouter 500'));

    $job = new ExtractWritingStyle($book, $preparation);
    $job->handle($service);

    Exceptions::assertReported(function (RuntimeException $e) {
        return $e->getMessage() === 'OpenRouter 500';
    });

    $preparation->refresh();
    expect($preparation->phase_errors)->toBeArray()
        ->and($preparation->phase_errors[0]['phase'])->toBe('writing_style')
        ->and($preparation->phase_errors[0]['error'])->toContain('OpenRouter 500')
        ->and($preparation->current_phase_progress)->toBe(1);
});

test('extract writing style rethrows transient errors for retry and does not record', function () {
    Exceptions::fake();
    [$book, $preparation] = seedBookForStyle();

    $service = $this->mock(WritingStyleService::class);
    $service->shouldReceive('extract')->once()
        ->andThrow(new ConnectionException('cURL error 28: Operation timed out'));

    $job = new ExtractWritingStyle($book, $preparation);

    expect(fn () => $job->handle($service))->toThrow(ConnectionException::class);

    $preparation->refresh();
    expect($preparation->phase_errors)->toBeNull();
});

test('failed hook reports to Sentry and records error', function () {
    Exceptions::fake();
    [$book, $preparation] = seedBookForStyle();

    $job = new ExtractWritingStyle($book, $preparation);
    $job->failed(new RuntimeException('Job aborted after retries'));

    Exceptions::assertReported(function (RuntimeException $e) {
        return $e->getMessage() === 'Job aborted after retries';
    });

    $preparation->refresh();
    expect($preparation->phase_errors)->toBeArray()
        ->and($preparation->phase_errors[0]['phase'])->toBe('writing_style')
        ->and($preparation->phase_errors[0]['error'])->toContain('Job aborted after retries');
});
