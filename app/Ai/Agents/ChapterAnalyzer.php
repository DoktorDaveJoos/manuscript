<?php

namespace App\Ai\Agents;

use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Models\Book;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.2)]
#[Timeout(90)]
class ChapterAnalyzer implements Agent, BelongsToBook, HasMiddleware, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        protected Book $book,
        protected string $precedingContext = '',
    ) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        $context = "You are a literary analyst performing a combined chapter analysis for '{$this->book->title}' by {$this->book->author}. The manuscript is written in {$this->book->language}.";

        if ($this->precedingContext) {
            $context .= "\n\nContext from preceding chapters:\n{$this->precedingContext}";
        }

        return <<<INSTRUCTIONS
        {$context}

        Analyze the provided chapter text and return:
        1. A concise 2-3 sentence summary of the chapter
        2. Key events that occurred
        3. Characters who appear in this chapter
        4. A tension score (1-10) measuring the overall tension level
        5. A hook score (1-10) measuring how effectively the chapter ending compels continued reading
        6. The hook type: 'cliffhanger' (unresolved danger/revelation), 'soft_hook' (intriguing question/emotional pull), 'closed' (satisfying conclusion that still moves story forward), or 'dead_end' (no forward momentum)
        7. Brief reasoning for the hook classification
        8. Plot points extracted from the chapter with their type and description

        Be precise and analytical. Score tension and hooks honestly — not every chapter needs high scores.
        INSTRUCTIONS;
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
            'key_events' => $schema->array()->required(),
            'characters_present' => $schema->array()->required(),
            'tension_score' => $schema->integer()->min(1)->max(10)->required(),
            'hook_score' => $schema->integer()->min(1)->max(10)->required(),
            'hook_type' => $schema->string()->required(),
            'hook_reasoning' => $schema->string()->required(),
            'plot_points' => $schema->array()->required(),
        ];
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials,
        ];
    }
}
