<?php

namespace App\Jobs\Concerns;

use App\Models\Book;
use App\Models\Chapter;
use Illuminate\Database\Eloquent\Model;

trait PersistsExtractedEntities
{
    /**
     * Persist extracted characters and wiki entries from an EntityExtractor response.
     *
     * @param  array<string, mixed>  $response
     */
    protected function persistExtractedEntities(Book $book, Chapter $chapter, array $response): void
    {
        $this->persistCharacters($book, $chapter, $response['characters'] ?? []);
        $this->persistWikiEntries($book, $chapter, $response['entities'] ?? []);
    }

    /**
     * @param  array<int, array<string, mixed>>  $characters
     */
    private function persistCharacters(Book $book, Chapter $chapter, array $characters): void
    {
        if (empty($characters)) {
            return;
        }

        // Pre-fetch all existing characters for this book in one query
        $existingCharacters = $book->characters()->get()->keyBy('name');
        $readerOrderCache = [$chapter->id => $chapter->reader_order];

        foreach ($characters as $characterData) {
            if (! is_array($characterData) || empty($characterData['name'])) {
                continue;
            }

            $name = $characterData['name'];
            $character = $existingCharacters->get($name);

            if (! $character) {
                $character = $book->characters()->make(['name' => $name]);
            }

            $character->aliases = array_values(array_unique(array_merge(
                $character->aliases ?? [],
                $characterData['aliases'] ?? [],
            )));

            $newDescription = $characterData['description'] ?? null;
            if (! $character->description || mb_strlen($newDescription ?? '') > mb_strlen($character->description)) {
                $character->description = $newDescription;
            }

            $character->is_ai_extracted = true;

            $this->resolveFirstAppearance($character, $chapter, $readerOrderCache);

            $character->save();
            $existingCharacters->put($name, $character);

            $character->chapters()->syncWithoutDetaching([
                $chapter->id => ['role' => $characterData['role'] ?? 'mentioned'],
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $entities
     */
    private function persistWikiEntries(Book $book, Chapter $chapter, array $entities): void
    {
        if (empty($entities)) {
            return;
        }

        // Pre-fetch all existing wiki entries for this book in one query
        $existingEntries = $book->wikiEntries()->get()->keyBy(fn ($e) => $e->name.'|'.$e->kind->value);
        $readerOrderCache = [$chapter->id => $chapter->reader_order];

        foreach ($entities as $entityData) {
            if (! is_array($entityData) || empty($entityData['name']) || empty($entityData['kind'])) {
                continue;
            }

            $key = $entityData['name'].'|'.$entityData['kind'];
            $entry = $existingEntries->get($key);

            if (! $entry) {
                $entry = $book->wikiEntries()->make([
                    'name' => $entityData['name'],
                    'kind' => $entityData['kind'],
                ]);
            }

            $newDescription = $entityData['description'] ?? null;
            if (! $entry->description || mb_strlen($newDescription ?? '') > mb_strlen($entry->description)) {
                $entry->description = $newDescription;
            }

            $entry->type = $entityData['type'] ?? null;
            $entry->is_ai_extracted = true;

            $this->resolveFirstAppearance($entry, $chapter, $readerOrderCache);

            $entry->save();
            $existingEntries->put($key, $entry);

            $entry->chapters()->syncWithoutDetaching([$chapter->id => []]);
        }
    }

    /**
     * Set first_appearance to the earliest chapter by reader_order.
     *
     * @param  array<int, int>  $readerOrderCache
     */
    private function resolveFirstAppearance(Model $entity, Chapter $chapter, array &$readerOrderCache): void
    {
        if ($entity->first_appearance) {
            $currentFirstOrder = $readerOrderCache[$entity->first_appearance]
                ??= Chapter::where('id', $entity->first_appearance)->value('reader_order');
        } else {
            $currentFirstOrder = null;
        }

        if (is_null($currentFirstOrder) || $chapter->reader_order < $currentFirstOrder) {
            $entity->first_appearance = $chapter->id;
        }
    }
}
