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

        // Sample the first three chapters that actually contain prose — empty
        // outline chapters at the start of a book must not shrink the sample.
        $sampleTexts = $this->book->chapters()
            ->with('currentVersion')
            ->orderBy('reader_order')
            ->get()
            ->map(fn ($chapter) => $chapter->currentVersion?->content)
            ->filter()
            ->take(3)
            ->map(fn (string $content) => strip_tags($content))
            ->values()
            ->all();

        if (empty($sampleTexts)) {
            return;
        }

        $combinedSample = implode("\n\n---\n\n", $sampleTexts);
        $words = preg_split('/\s+/', $combinedSample);
        if (count($words) > 5000) {
            $combinedSample = implode(' ', array_slice($words, 0, 5000));
        }

        $this->book->update(['writing_style' => $styleService->extract($combinedSample, $this->book)]);
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
