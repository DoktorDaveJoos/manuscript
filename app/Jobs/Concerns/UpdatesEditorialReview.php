<?php

namespace App\Jobs\Concerns;

use App\Models\EditorialReview;

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

    protected function markReviewFailed(EditorialReview $review, string $message): void
    {
        $review->update([
            'status' => 'failed',
            'error_message' => $message,
        ]);
    }
}
