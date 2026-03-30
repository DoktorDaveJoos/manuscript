<?php

namespace App\Support;

use App\Models\Character;
use App\Models\WikiEntry;
use Illuminate\Support\Collection;

class EntityNameMatcher
{
    /** @var array<string, Character> */
    private array $characterIndex = [];

    /** @var array<string, array<string, WikiEntry>> */
    private array $wikiEntryIndex = [];

    private const ARTICLES = ['the', 'a', 'an', 'der', 'die', 'das', 'ein', 'eine', 'le', 'la', 'les', 'el', 'los', 'las'];

    /**
     * @param  Collection<int, Character>  $characters
     * @param  Collection<int, WikiEntry>  $wikiEntries
     */
    public function __construct(Collection $characters, Collection $wikiEntries)
    {
        $this->buildCharacterIndex($characters);
        $this->buildWikiEntryIndex($wikiEntries);
    }

    public function findCharacter(string $name): ?Character
    {
        $normalized = self::normalize($name);

        return $this->characterIndex[$normalized] ?? null;
    }

    public function findWikiEntry(string $name, string $kind): ?WikiEntry
    {
        $normalized = self::normalize($name);

        return $this->wikiEntryIndex[$kind][$normalized] ?? null;
    }

    public static function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));

        // Strip leading articles
        foreach (self::ARTICLES as $article) {
            $prefix = $article.' ';
            if (str_starts_with($name, $prefix)) {
                $name = substr($name, strlen($prefix));
                break;
            }
        }

        return trim($name);
    }

    /**
     * @param  Collection<int, Character>  $characters
     */
    private function buildCharacterIndex(Collection $characters): void
    {
        foreach ($characters as $character) {
            $this->characterIndex[self::normalize($character->name)] = $character;

            foreach ($character->aliases ?? [] as $alias) {
                $normalizedAlias = self::normalize($alias);
                $this->characterIndex[$normalizedAlias] ??= $character;
            }
        }
    }

    /**
     * @param  Collection<int, WikiEntry>  $wikiEntries
     */
    private function buildWikiEntryIndex(Collection $wikiEntries): void
    {
        foreach ($wikiEntries as $entry) {
            $kind = $entry->kind->value;

            $this->wikiEntryIndex[$kind][self::normalize($entry->name)] = $entry;

            foreach ($entry->metadata['aliases'] ?? [] as $alias) {
                $normalizedAlias = self::normalize($alias);
                $this->wikiEntryIndex[$kind][$normalizedAlias] ??= $entry;
            }
        }
    }
}
