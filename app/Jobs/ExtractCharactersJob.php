<?php

namespace App\Jobs;

use App\Ai\Agents\CharacterExtractor;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExtractCharactersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

        $setting->injectConfig();

        $agent = new CharacterExtractor($this->book);
        $response = $agent->prompt("Extract all characters from the following chapter text:\n\n{$content}");

        $characters = $response['characters'] ?? [];

        foreach ($characters as $characterData) {
            if (! is_array($characterData) || empty($characterData['name'])) {
                continue;
            }

            $this->book->characters()->updateOrCreate(
                ['name' => $characterData['name']],
                [
                    'aliases' => $characterData['aliases'] ?? null,
                    'description' => $characterData['description'] ?? null,
                    'is_ai_extracted' => true,
                    'first_appearance' => $this->chapter->id,
                ],
            );
        }
    }
}
