<?php

namespace App\Ai\Agents;

use App\Ai\Middleware\InjectProviderCredentials;
use App\Ai\Tools\RetrieveManuscriptContext;
use App\Ai\Tools\SearchSimilarChunks;
use App\Models\Book;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.7)]
#[Timeout(120)]
class NextChapterAdvisor implements Agent, HasMiddleware, HasStructuredOutput, HasTools
{
    use Promptable;

    public function __construct(protected Book $book) {}

    public function instructions(): Stringable|string
    {
        return <<<INSTRUCTIONS
        You are a creative writing advisor helping plan the next chapter of '{$this->book->title}' by {$this->book->author}.
        The manuscript is written in {$this->book->language}.

        Based on the current state of the manuscript, provide:
        1. A suggestion for what should happen next
        2. Open plot points that need attention
        3. Characters that have been neglected recently
        4. Ideas for chapter hooks or opening lines

        Use the available tools to retrieve manuscript context and search for relevant passages.
        Be creative but consistent with the established tone and direction of the story.
        INSTRUCTIONS;
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'suggestion' => $schema->string()->required(),
            'open_plot_points' => $schema->array()->required(),
            'neglected_characters' => $schema->array()->required(),
            'hook_ideas' => $schema->array()->required(),
        ];
    }

    public function tools(): iterable
    {
        return [
            new RetrieveManuscriptContext,
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
