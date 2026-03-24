<?php

namespace App\Jobs;

use App\Models\Book;
use App\Models\EditorialReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunEditorialReviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(
        private Book $book,
        private EditorialReview $review,
    ) {}

    public function handle(): void
    {
        // TODO: Implement editorial review pipeline (phases 1-4)
    }
}
