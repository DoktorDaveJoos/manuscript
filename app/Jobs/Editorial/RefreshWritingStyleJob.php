<?php

namespace App\Jobs\Editorial;

use App\Models\Book;
use App\Services\WritingStyleService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Refreshes the book-wide writing style as part of an editorial review run.
 * The style guides prose generation (continue writing, revise, rewrite) and
 * the editorial notes agent. Failures are reported but never fail the review.
 */
class RefreshWritingStyleJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [15];

    public int $timeout = 180;

    public function __construct(public Book $book) {}

    public function handle(WritingStyleService $styleService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $sampleText = $this->book->writingStyleSample();

        if ($sampleText === null) {
            return;
        }

        $this->book->update(['writing_style' => $styleService->extract($sampleText, $this->book)]);
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
