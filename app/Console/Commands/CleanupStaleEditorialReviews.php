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
        $staleMinutes = 45; // 1.5x the 30-minute job timeout

        $count = EditorialReview::query()
            ->whereIn('status', ['pending', 'analyzing', 'synthesizing'])
            ->where('updated_at', '<', now()->subMinutes($staleMinutes))
            ->update([
                'status' => 'failed',
                'error_message' => __('Review timed out. Please try again.'),
            ]);

        if ($count > 0) {
            $this->info("Marked {$count} stale editorial review(s) as failed.");
        }

        return self::SUCCESS;
    }
}
