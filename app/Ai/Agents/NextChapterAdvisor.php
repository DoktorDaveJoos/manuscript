<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesTaskCategoryModel;
use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Ai\Tools\RetrieveManuscriptContext;
use App\Ai\Tools\SearchSimilarChunks;
use App\Enums\AiTaskCategory;
use App\Models\Book;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
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
class NextChapterAdvisor implements Agent, BelongsToBook, HasMiddleware, HasStructuredOutput, HasTools
{
    use Promptable, UsesTaskCategoryModel;

    public static function taskCategory(): AiTaskCategory
    {
        return AiTaskCategory::Analysis;
    }

    public function __construct(protected Book $book) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        $writingStyle = $this->book->writingStyleSnippet("The author's prose style (match this voice in any hook ideas or example text)");

        return <<<INSTRUCTIONS
        You are a creative writing advisor helping plan the next chapter of '{$this->book->title}' by {$this->book->author}.
        The manuscript is written in {$this->book->language}.

        Based on the current state of the manuscript, provide:
        1. A suggestion for what should happen next
        2. Open plot points that need attention
        3. Characters that have been neglected recently
        4. Ideas for chapter hooks or opening lines

        Use the available tools to retrieve manuscript context and search for relevant passages.
        LANGUAGE RULE: ALL text content you produce — suggestions, plot points, hook ideas — MUST be written in {$this->book->language}. Only structured field names (JSON keys) remain in English. Do not mix languages.

        Be creative but consistent with the established tone and direction of the story.{$writingStyle}
        INSTRUCTIONS;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'suggestion' => $schema->string()->required(),
            'open_plot_points' => $schema->array()->items($schema->string())->required(),
            'neglected_characters' => $schema->array()->items($schema->string())->required(),
            'hook_ideas' => $schema->array()->items($schema->string())->required(),
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
