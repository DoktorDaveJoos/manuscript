<?php

namespace App\Jobs;

use App\Ai\Agents\EntityExtractor;
use App\Ai\Support\TextPrep;
use App\Jobs\Concerns\PersistsExtractedEntities;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExtractEntitiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, PersistsExtractedEntities, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

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

        $agent = new EntityExtractor($this->book);
        $response = $agent->prompt("Extract all characters and narratively important entities from the following chapter text:\n\n{$capped}");

        $this->persistExtractedEntities($this->book, $this->chapter, $response->toArray());
    }
}
