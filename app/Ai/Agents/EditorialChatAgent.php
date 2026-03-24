<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesTaskCategoryModel;
use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Ai\Tools\RetrieveManuscriptContext;
use App\Ai\Tools\SearchSimilarChunks;
use App\Enums\AiTaskCategory;
use App\Models\Book;
use App\Models\EditorialReview;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.5)]
#[MaxTokens(4096)]
#[Timeout(120)]
class EditorialChatAgent implements Agent, BelongsToBook, Conversational, HasMiddleware, HasTools
{
    use Promptable, UsesTaskCategoryModel;

    public static function taskCategory(): AiTaskCategory
    {
        return AiTaskCategory::Analysis;
    }

    public function __construct(
        protected Book $book,
        protected EditorialReview $review,
        protected string $editorialContext,
        protected array $history = [],
    ) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        return <<<INSTRUCTIONS
        You are an editorial review assistant for the book '{$this->book->title}' by {$this->book->author}.
        The manuscript is written in {$this->book->language}.

        You are helping the author discuss findings from an editorial review of their manuscript.
        Use the available tools to search through the manuscript and retrieve relevant context.

        The book ID is {$this->book->id}. Use this when calling tools.

        Editorial context:
        {$this->editorialContext}

        Be concise, helpful, and grounded in the actual text. Provide specific, actionable advice.
        When referencing parts of the manuscript, mention chapter titles or numbers.
        INSTRUCTIONS;
    }

    public function messages(): iterable
    {
        return array_map(
            fn (array $entry) => $entry['role'] === 'user'
                ? new UserMessage($entry['content'])
                : new AssistantMessage($entry['content']),
            $this->history,
        );
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
