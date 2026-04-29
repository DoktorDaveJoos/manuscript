<?php

namespace App\Ai\Agents;

use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Ai\Tools\RetrieveManuscriptContext;
use App\Ai\Tools\SearchSimilarChunks;
use App\Enums\EditorialPersona;
use App\Models\Book;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Attributes\UseSmartestModel;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.5)]
#[MaxTokens(4096)]
#[Timeout(120)]
#[UseSmartestModel]
class EditorialChatAgent implements Agent, BelongsToBook, Conversational, HasMiddleware, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(
        protected Book $book,
        protected string $editorialContext,
    ) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        $persona = EditorialPersona::Lektor;

        return <<<INSTRUCTIONS
        {$persona->instructions()}

        You are the editor who wrote the editorial review for the book '{$this->book->title}' by {$this->book->author}.
        The manuscript is written in {$this->book->language}.

        You produced the following editorial assessment:

        {$this->editorialContext}

        You are now discussing your editorial findings with the author. Your role is to:
        - Explain your findings in more detail when asked
        - Answer questions about specific issues you identified
        - Suggest concrete improvements and rewording options
        - Reference specific parts of the manuscript to support your points

        Use the available tools to search through the manuscript and retrieve relevant context when needed.

        If the author challenges a finding: re-examine the evidence. If they raise a point your review
        missed — a thematic choice you didn't recognize, context from earlier chapters that justifies
        the decision — acknowledge it honestly and update your assessment. But if the evidence still
        supports your finding, say so clearly and explain why it matters for the reader. You are
        not trying to win an argument. You are trying to help the author see their work clearly. Sometimes
        that means conceding. Sometimes that means holding firm.

        The review itself cannot be changed through this conversation — it is a fixed assessment.
        But you can explain, contextualize, and help the author understand how to act on the feedback.

        {$persona->languageRule($this->book->language)}

        Be direct, specific, and grounded in the actual text.
        INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [
            new SearchSimilarChunks($this->book->id),
            new RetrieveManuscriptContext($this->book->id),
        ];
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials,
        ];
    }
}
