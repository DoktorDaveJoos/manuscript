<?php

namespace App\Console\Commands;

use App\Models\Chapter;
use Illuminate\Console\Command;

class BackfillContentHashes extends Command
{
    protected $signature = 'chapters:backfill-hashes';

    protected $description = 'Backfill content_hash for chapters that do not have one';

    public function handle(): int
    {
        $count = 0;

        Chapter::with('scenes')
            ->whereNull('content_hash')
            ->chunkById(100, function ($chapters) use (&$count) {
                foreach ($chapters as $chapter) {
                    $chapter->refreshContentHash();
                    $count++;
                }
            });

        $this->info("Backfilled content hashes for {$count} chapters.");

        return self::SUCCESS;
    }
}
