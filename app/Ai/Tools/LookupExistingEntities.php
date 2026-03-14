<?php

namespace App\Ai\Tools;

use App\Models\Book;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class LookupExistingEntities implements Tool
{
    public function description(): Stringable|string
    {
        return 'Looks up existing characters and world entities for a book, including names, aliases, and descriptions. Useful for avoiding duplicate extraction and matching aliases.';
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'book_id' => $schema->integer()->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $book = Book::query()->findOrFail($request['book_id']);
        $characters = $book->characters()->get(['id', 'name', 'aliases', 'description']);
        $wikiEntries = $book->wikiEntries()->get(['id', 'name', 'kind', 'type', 'description', 'metadata']);

        if ($characters->isEmpty() && $wikiEntries->isEmpty()) {
            return 'No existing characters or entities found for this book.';
        }

        $sections = [];

        if ($characters->isNotEmpty()) {
            $results = [];
            foreach ($characters as $character) {
                $aliases = ! empty($character->aliases) ? ' (aliases: '.implode(', ', $character->aliases).')' : '';
                $results[] = "- {$character->name}{$aliases}: {$character->description}";
            }
            $sections[] = "## Existing Characters\n\n".implode("\n", $results);
        }

        if ($wikiEntries->isNotEmpty()) {
            $results = [];
            foreach ($wikiEntries as $entry) {
                $type = $entry->type ? " ({$entry->type})" : '';
                $aliases = ! empty($entry->metadata['aliases']) ? ' (aliases: '.implode(', ', $entry->metadata['aliases']).')' : '';
                $results[] = "- [{$entry->kind->value}] {$entry->name}{$aliases}{$type}: {$entry->description}";
            }
            $sections[] = "## Existing World Entities\n\n".implode("\n", $results);
        }

        return implode("\n\n", $sections);
    }
}
