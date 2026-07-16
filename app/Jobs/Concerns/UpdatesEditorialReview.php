<?php

namespace App\Jobs\Concerns;

use App\Enums\EditorialReviewErrorCode;
use App\Models\EditorialReview;
use Throwable;

trait UpdatesEditorialReview
{
    /**
     * Write the review's progress JSON consumed by the polling frontend.
     */
    protected function updateReviewProgress(
        EditorialReview $review,
        string $phase,
        ?int $current_chapter = null,
        ?int $total_chapters = null,
        ?string $current_section = null,
    ): void {
        $progress = ['phase' => $phase];

        if ($current_chapter !== null) {
            $progress['current_chapter'] = $current_chapter;
        }

        if ($total_chapters !== null) {
            $progress['total_chapters'] = $total_chapters;
        }

        if ($current_section !== null) {
            $progress['current_section'] = $current_section;
        }

        $review->update(['progress' => $progress]);
    }

    protected function markReviewFailed(
        EditorialReview $review,
        string $message,
        EditorialReviewErrorCode $code = EditorialReviewErrorCode::Unknown,
    ): void {
        $review->update([
            'status' => 'failed',
            'error_message' => $message,
            'error_code' => $code->value,
        ]);
    }

    /**
     * Persist a safe user-facing failure while retaining the throwable only in
     * the exception report. Provider messages may contain request details and
     * must never be copied directly into a review shown to the user.
     */
    protected function markReviewFailedFromThrowable(
        EditorialReview $review,
        Throwable $exception,
        string $message,
    ): void {
        $this->markReviewFailed(
            $review,
            $message,
            EditorialReviewErrorCode::fromThrowable($exception),
        );
    }
}
