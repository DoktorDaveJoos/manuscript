<?php

namespace App\Jobs;

use App\Ai\Agents\EntityExtractor;
use App\Ai\Support\TextPrep;
use App\Jobs\Concerns\PersistsExtractedEntities;
use App\Jobs\Concerns\ReportsJobFailures;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExtractEntitiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, PersistsExtractedEntities, Queueable, ReportsJobFailures, SerializesModels;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(
        private Book $book,
        private Chapter $chapter,
    ) {}

    public function handle(): void
    {
        $chapter = $this->chapter->load('currentVersion');
        $content = $chapter->currentVersion?->content;

        if (blank($content)) {
            return;
        }

        $setting = AiSetting::activeProvider();

        if (! $setting || ! $setting->isConfigured()) {
            return;
        }

        $capped = TextPrep::plainTextCapped($content);

        try {
            $agent = new EntityExtractor($this->book);
            $response = $agent->prompt(
                "Extract all characters and narratively important entities from the following chapter text:\n\n{$capped}",
                timeout: 180,
            );

            $this->persistExtractedEntities($this->book, $this->chapter, $response->toArray());
        } catch (Throwable $e) {
            report($e);

            throw $e;
        }
    }
}
