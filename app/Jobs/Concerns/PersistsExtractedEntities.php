<?php

namespace App\Jobs\Concerns;

use App\Models\Book;
use App\Models\Chapter;

trait PersistsExtractedEntities
{
    /**
     * Persist extracted characters and wiki entries from an EntityExtractor response.
     *
     * @param  array<string, mixed>  $response
     */
    protected function persistExtractedEntities(Book $book, Chapter $chapter, array $response): void
    {
        foreach ($response['characters'] ?? [] as $characterData) {
            if (! is_array($characterData) || empty($characterData['name'])) {
                continue;
            }

            $book->characters()->updateOrCreate(
                ['name' => $characterData['name']],
                [
                    'aliases' => $characterData['aliases'] ?? null,
                    'description' => $characterData['description'] ?? null,
                    'is_ai_extracted' => true,
                    'first_appearance' => $chapter->id,
                ],
            );
        }

        foreach ($response['entities'] ?? [] as $entityData) {
            if (! is_array($entityData) || empty($entityData['name']) || empty($entityData['kind'])) {
                continue;
            }

            $book->wikiEntries()->updateOrCreate(
                ['name' => $entityData['name'], 'kind' => $entityData['kind']],
                [
                    'type' => $entityData['type'] ?? null,
                    'description' => $entityData['description'] ?? null,
                    'is_ai_extracted' => true,
                    'first_appearance' => $chapter->id,
                ],
            );
        }
    }
}
