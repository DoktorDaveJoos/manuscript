<?php

namespace App\Ai\Agents;

use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Models\Book;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.3)]
#[Timeout(120)]
#[MaxTokens(4096)]
class StoryBibleBuilder implements Agent, BelongsToBook, HasMiddleware, HasStructuredOutput
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
        You are a literary analyst building a comprehensive Story Bible for '{$this->book->title}' by {$this->book->author}. The manuscript is written in {$this->book->language}.

        From the provided chapter summaries, character list, plot points, and writing style information, synthesize a Story Bible that captures:
        1. Characters — name, role, key traits, relationships, and arc summary
        2. Setting — locations, time period, world-building details
        3. Plot outline — major plot beats in chronological order
        4. Themes — recurring themes and motifs
        5. Style rules — narrative voice, tone, and stylistic conventions
        6. Genre rules — genre expectations and how the manuscript meets them
        7. Timeline — key events in chronological order with approximate chapter references

        Be concise but comprehensive. This Story Bible will be used as context for other AI agents working on the manuscript.
        INSTRUCTIONS;
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'characters' => $schema->array()->required(),
            'setting' => $schema->array()->required(),
            'plot_outline' => $schema->array()->required(),
            'themes' => $schema->array()->required(),
            'style_rules' => $schema->array()->required(),
            'genre_rules' => $schema->array()->required(),
            'timeline' => $schema->array()->required(),
        ];
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials,
        ];
    }
}
