<?php

namespace App\Ai\Agents;

use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Models\Book;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.0)]
#[Timeout(60)]
#[UseCheapestModel]
class EntityConsolidator implements Agent, BelongsToBook, HasMiddleware, HasStructuredOutput
{
    use Promptable;

    public function __construct(protected Book $book) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
        You are a deduplication specialist for manuscript entity databases. You receive a list of AI-extracted characters and world entities. Your job is to identify duplicate entries that refer to the same real character or entity.

        ## Rules
        - Be CONSERVATIVE. Only merge when you are confident two entries refer to the same character or entity.
        - Consider: name substrings (e.g. "Paulsen" is a substring of "Maja Paulsen"), abbreviations/acronyms (e.g. "GZP" for "Green Zone Protection Party"), nicknames, and description context.
        - The `canonical_id` should be the entry with the most complete name and description.
        - The `canonical_name` should be the full, most complete form of the name.
        - `merged_aliases` should include all aliases from both entries plus the duplicate's name (but NOT the canonical name).
        - If there are no duplicates, return empty arrays.
        INSTRUCTIONS;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $mergeSchema = $schema->object([
            'canonical_id' => $schema->integer()->required(),
            'duplicate_ids' => $schema->array()->items($schema->integer())->required(),
            'canonical_name' => $schema->string()->required(),
            'merged_aliases' => $schema->array()->items($schema->string())->required(),
        ])->withoutAdditionalProperties();

        return [
            'character_merges' => $schema->array()->items($mergeSchema)->required(),
            'entity_merges' => $schema->array()->items($mergeSchema)->required(),
        ];
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials,
        ];
    }
}
