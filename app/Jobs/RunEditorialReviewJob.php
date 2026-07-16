<?php

namespace App\Jobs;

use App\Enums\EditorialReviewErrorCode;
use App\Jobs\Concerns\UpdatesEditorialReview;
use App\Jobs\Editorial\AnalyzeReviewChapterJob;
use App\Jobs\Editorial\EmbedReviewChapterJob;
use App\Jobs\Editorial\FinalizeEditorialReviewJob;
use App\Jobs\Editorial\RefreshWritingStyleJob;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\EditorialReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\FailOnTimeout;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * Orchestrates an editorial review by fanning the per-chapter work out into a
 * batch of short jobs followed by a terminal finalize job. Keeping each unit
 * small means no single job can exceed a worker timeout, however large the book.
 */
#[FailOnTimeout]
class RunEditorialReviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, UpdatesEditorialReview;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public Book $book,
        public EditorialReview $review,
    ) {}

    public function handle(): void
    {
        $setting = AiSetting::activeProvider();

        if (! $setting || ! $setting->isConfigured()) {
            $this->markReviewFailed(
                $this->review,
                __('The editorial review could not start because no configured AI provider is selected.'),
                EditorialReviewErrorCode::NoProvider,
            );

            return;
        }

        $chapters = $this->book->chapters()
            ->with(['currentVersion', 'scenes'])
            ->orderBy('reader_order')
            ->get();

        // Backfill content hashes so staleness detection works for legacy chapters.
        foreach ($chapters as $chapter) {
            if ($chapter->content_hash === null && $chapter->scenes->isNotEmpty()) {
                $chapter->refreshContentHash();
            }
        }

        $total = $chapters->count();

        $this->review->update([
            'status' => 'analyzing',
            'started_at' => $this->review->started_at ?? now(),
        ]);

        $this->updateReviewProgress($this->review, 'analyzing', current_chapter: 0, total_chapters: $total);

        $staleChapters = $chapters->filter(
            fn ($chapter) => $chapter->needsAiPreparation() && $chapter->currentVersion?->content
        );

        // Shared AI context refreshes run before analysis (single-worker FIFO):
        // the notes agent reads the writing style, retrieval reads the chunks.
        $jobs = [];

        if ($this->book->writing_style === null || $staleChapters->isNotEmpty()) {
            $jobs[] = new RefreshWritingStyleJob($this->book);
        }

        foreach ($staleChapters as $chapter) {
            $jobs[] = new EmbedReviewChapterJob($this->book, $chapter->id);
        }

        $jobs = array_merge($jobs, $chapters
            ->values()
            ->map(fn ($chapter, $index) => new AnalyzeReviewChapterJob(
                $this->book,
                $this->review,
                $chapter->id,
                $index + 1,
                $total,
            ))
            ->all());

        // Terminal job runs last (single-worker FIFO): synthesis + summary.
        $jobs[] = new FinalizeEditorialReviewJob($this->book, $this->review);

        $batch = Bus::batch($jobs)
            ->allowFailures()
            ->dispatch();

        $this->review->update(['batch_id' => $batch->id]);
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception) {
            report($exception);
            $this->markReviewFailedFromThrowable(
                $this->review,
                $exception,
                __('The app could not prepare the editorial review jobs. Your saved progress is still available.'),
            );

            return;
        }

        $this->markReviewFailed(
            $this->review,
            __('The app could not prepare the editorial review jobs. Your saved progress is still available.'),
        );
    }
}
