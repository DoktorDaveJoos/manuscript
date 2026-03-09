<?php

namespace App\Ai\Tools;

use App\Models\Book;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class LookupExistingCharacters implements Tool
{
    public function description(): Stringable|string
    {
        return 'Looks up existing characters for a book, including names, aliases, and descriptions. Useful for avoiding duplicate extraction and matching aliases.';
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
        $characters = $book->characters()->get();

        if ($characters->isEmpty()) {
            return 'No existing characters found for this book.';
        }

        $results = [];
        foreach ($characters as $character) {
            $aliases = ! empty($character->aliases) ? ' (aliases: '.implode(', ', $character->aliases).')' : '';
            $results[] = "- {$character->name}{$aliases}: {$character->description}";
        }

        return "## Existing Characters\n\n".implode("\n", $results);
    }
}
