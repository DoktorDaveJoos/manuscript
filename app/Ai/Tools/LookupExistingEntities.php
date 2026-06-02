<?php

namespace App\Ai\Tools;

use App\Models\Book;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class LookupExistingEntities implements Tool
{
    private const DESCRIPTION_CHAR_LIMIT = 200;

    public function __construct(private int $bookId) {}

    public function description(): Stringable|string
    {
        return 'Looks up existing characters and world entities for the current book, including names, aliases, and descriptions. Useful for avoiding duplicate extraction and matching aliases.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Stringable|string
    {
        $book = Book::query()->findOrFail($this->bookId);
        $characters = $book->characters()->get(['id', 'name', 'aliases', 'description', 'ai_description']);
        $wikiEntries = $book->wikiEntries()->get(['id', 'name', 'kind', 'type', 'description', 'ai_description', 'metadata']);

        if ($characters->isEmpty() && $wikiEntries->isEmpty()) {
            return 'No existing characters or entities found for this book.';
        }

        $sections = [];

        if ($characters->isNotEmpty()) {
            $results = [];
            foreach ($characters as $character) {
                $aliases = ! empty($character->aliases) ? ' (aliases: '.implode(', ', $character->aliases).')' : '';
                $description = $this->truncate($character->fullDescription());
                $results[] = "- id={$character->id} {$character->name}{$aliases}: {$description}";
            }
            $sections[] = "## Existing Characters\n\n".implode("\n", $results);
        }

        if ($wikiEntries->isNotEmpty()) {
            $results = [];
            foreach ($wikiEntries as $entry) {
                $type = $entry->type ? " ({$entry->type})" : '';
                $aliases = ! empty($entry->metadata['aliases']) ? ' (aliases: '.implode(', ', $entry->metadata['aliases']).')' : '';
                $description = $this->truncate($entry->fullDescription());
                $results[] = "- id={$entry->id} [{$entry->kind->value}] {$entry->name}{$aliases}{$type}: {$description}";
            }
            $sections[] = "## Existing World Entities\n\n".implode("\n", $results);
        }

        $sections[] = '_Use the `id` value when proposing an update or referencing an existing entity (e.g. `{"type":"wiki_entry","data":{"id":42,...}}`)._';

        return implode("\n\n", $sections);
    }

    private function truncate(?string $description): string
    {
        if ($description === null || $description === '') {
            return '(no description)';
        }

        return Str::limit(Str::squish($description), self::DESCRIPTION_CHAR_LIMIT);
    }
}
