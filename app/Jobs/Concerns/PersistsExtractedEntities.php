<?php

namespace App\Jobs\Concerns;

use App\Models\Book;
use App\Models\Chapter;
use App\Support\EntityNameMatcher;
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
        $characters = $response['characters'] ?? [];
        $entities = $response['entities'] ?? [];

        if (empty($characters) && empty($entities)) {
            return;
        }

        $matcher = new EntityNameMatcher(
            $book->characters()->get(),
            $book->wikiEntries()->get(),
        );

        $this->persistCharacters($book, $chapter, $characters, $matcher);
        $this->persistWikiEntries($book, $chapter, $entities, $matcher);
    }

    /**
     * @param  array<int, array<string, mixed>>  $characters
     */
    private function persistCharacters(Book $book, Chapter $chapter, array $characters, EntityNameMatcher $matcher): void
    {
        if (empty($characters)) {
            return;
        }

        $readerOrderCache = [$chapter->id => $chapter->reader_order];

        foreach ($characters as $characterData) {
            if (! is_array($characterData) || empty($characterData['name'])) {
                continue;
            }

            $name = $characterData['name'];
            $character = $matcher->findCharacter($name);
            $isNew = ! $character;

            if ($isNew) {
                $character = $book->characters()->make(['name' => $name]);
            }

            $character->aliases = array_values(array_unique(array_merge(
                $character->aliases ?? [],
                $characterData['aliases'] ?? [],
            )));

            $newDescription = $characterData['description'] ?? null;
            if (! $character->ai_description || mb_strlen($newDescription ?? '') > mb_strlen($character->ai_description)) {
                $character->ai_description = $newDescription;
            }

            if ($isNew) {
                $character->is_ai_extracted = true;
            }

            $this->resolveFirstAppearance($character, $chapter, $readerOrderCache);

            $character->save();

            $character->chapters()->syncWithoutDetaching([
                $chapter->id => ['role' => $characterData['role'] ?? 'mentioned'],
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $entities
     */
    private function persistWikiEntries(Book $book, Chapter $chapter, array $entities, EntityNameMatcher $matcher): void
    {
        if (empty($entities)) {
            return;
        }

        $readerOrderCache = [$chapter->id => $chapter->reader_order];

        foreach ($entities as $entityData) {
            if (! is_array($entityData) || empty($entityData['name']) || empty($entityData['kind'])) {
                continue;
            }

            $entry = $matcher->findWikiEntry($entityData['name'], $entityData['kind']);
            $isNew = ! $entry;

            if ($isNew) {
                $entry = $book->wikiEntries()->make([
                    'name' => $entityData['name'],
                    'kind' => $entityData['kind'],
                ]);
            }

            $newDescription = $entityData['description'] ?? null;
            if (! $entry->ai_description || mb_strlen($newDescription ?? '') > mb_strlen($entry->ai_description)) {
                $entry->ai_description = $newDescription;
            }

            $entry->type = $entityData['type'] ?? $entry->type;

            if ($isNew) {
                $entry->is_ai_extracted = true;
            }

            $this->resolveFirstAppearance($entry, $chapter, $readerOrderCache);

            $entry->save();

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
