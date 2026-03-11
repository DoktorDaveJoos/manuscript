<?php

namespace App\Ai\Agents;

use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Ai\Tools\SearchSimilarChunks;
use App\Models\Book;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.3)]
#[Timeout(120)]
#[MaxTokens(4096)]
class StoryBibleBuilder implements Agent, BelongsToBook, HasMiddleware, HasStructuredOutput, HasTools
{
    use Promptable;

    public function __construct(protected Book $book) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        return <<<INSTRUCTIONS
        You are a literary analyst extracting high-level insights for '{$this->book->title}' by {$this->book->author}. The manuscript is written in {$this->book->language}.

        Characters, plot points, and chapter summaries are already tracked separately. From the provided data, extract only what those models do NOT cover:
        1. Themes — recurring themes and motifs
        2. Style rules — narrative voice, tone, and stylistic conventions
        3. Genre rules — genre expectations and how the manuscript meets them
        4. Timeline — key events in chronological order with approximate chapter references

        Verify key facts against the manuscript text using the search tool.
        The book ID is {$this->book->id}. Use this when calling the search tool.

        Be concise. This will be used as supplementary context for other AI agents.
        INSTRUCTIONS;
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'themes' => $schema->array()->items($schema->string())->required(),
            'style_rules' => $schema->array()->items($schema->string())->required(),
            'genre_rules' => $schema->array()->items($schema->string())->required(),
            'timeline' => $schema->array()->items($schema->string())->required(),
        ];
    }

    public function tools(): iterable
    {
        return [
            new SearchSimilarChunks,
        ];
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials,
        ];
    }
}
