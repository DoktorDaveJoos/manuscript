<?php

namespace App\Services;

use App\Ai\Agents\WritingStyleExtractor;
use App\Models\AiSetting;
use App\Models\Book;

class WritingStyleService
{
    /**
     * Extract writing style from sample chapter content. Token usage is
     * recorded by the RecordAiTokenUsage listener.
     *
     * @return array<string, mixed>
     */
    public function extract(string $sampleText, Book $book): array
    {
        abort_if(! AiSetting::activeProvider(), 422, 'No AI provider configured.');

        $response = (new WritingStyleExtractor($book))->prompt(
            "Derive the prose style from this manuscript excerpt. Be concrete enough that another writer could reproduce the voice.\n\n{$sampleText}",
        );

        return $response->toArray();
    }
}
