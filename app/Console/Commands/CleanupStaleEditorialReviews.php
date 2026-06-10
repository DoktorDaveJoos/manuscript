<?php

namespace App\Console\Commands;

use App\Models\EditorialReview;
use Illuminate\Console\Command;

class CleanupStaleEditorialReviews extends Command
{
    protected $signature = 'editorial-reviews:cleanup-stale';

    protected $description = 'Mark stale editorial reviews as failed';

    public function handle(): int
    {
        $count = EditorialReview::failStale();

        if ($count > 0) {
            $this->info("Marked {$count} stale editorial review(s) as failed.");
        }

        return self::SUCCESS;
    }
}
