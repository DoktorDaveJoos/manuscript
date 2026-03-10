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
        $readerOrderCache = [];

        foreach ($response['characters'] ?? [] as $characterData) {
            if (! is_array($characterData) || empty($characterData['name'])) {
                continue;
            }

            $character = $book->characters()->firstOrNew(['name' => $characterData['name']]);

            $character->aliases = array_values(array_unique(array_merge(
                $character->aliases ?? [],
                $characterData['aliases'] ?? [],
            )));

            $newDescription = $characterData['description'] ?? null;
            if (! $character->description || mb_strlen($newDescription ?? '') > mb_strlen($character->description)) {
                $character->description = $newDescription;
            }

            $character->is_ai_extracted = true;

            // Resolve first_appearance using reader_order
            if ($character->first_appearance) {
                $currentFirstOrder = $readerOrderCache[$character->first_appearance]
                    ??= Chapter::where('id', $character->first_appearance)->value('reader_order');
            } else {
                $currentFirstOrder = null;
            }

            if (is_null($currentFirstOrder) || $chapter->reader_order < $currentFirstOrder) {
                $character->first_appearance = $chapter->id;
                $readerOrderCache[$chapter->id] = $chapter->reader_order;
            }

            $character->save();

            // Populate character-chapter pivot with role
            $character->chapters()->syncWithoutDetaching([
                $chapter->id => ['role' => $characterData['role'] ?? 'mentioned'],
            ]);
        }

        foreach ($response['entities'] ?? [] as $entityData) {
            if (! is_array($entityData) || empty($entityData['name']) || empty($entityData['kind'])) {
                continue;
            }

            $entry = $book->wikiEntries()->firstOrNew([
                'name' => $entityData['name'],
                'kind' => $entityData['kind'],
            ]);

            $newDescription = $entityData['description'] ?? null;
            if (! $entry->description || mb_strlen($newDescription ?? '') > mb_strlen($entry->description)) {
                $entry->description = $newDescription;
            }

            $entry->type = $entityData['type'] ?? null;
            $entry->is_ai_extracted = true;

            // Resolve first_appearance using reader_order
            if ($entry->first_appearance) {
                $currentFirstOrder = $readerOrderCache[$entry->first_appearance]
                    ??= Chapter::where('id', $entry->first_appearance)->value('reader_order');
            } else {
                $currentFirstOrder = null;
            }

            if (is_null($currentFirstOrder) || $chapter->reader_order < $currentFirstOrder) {
                $entry->first_appearance = $chapter->id;
                $readerOrderCache[$chapter->id] = $chapter->reader_order;
            }

            $entry->save();

            // Populate wiki entry-chapter pivot
            $entry->chapters()->syncWithoutDetaching([
                $chapter->id => [],
            ]);
        }
    }
}
