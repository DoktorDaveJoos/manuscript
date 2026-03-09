<?php

namespace App\Ai\Agents;

use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Ai\Tools\RetrieveManuscriptContext;
use App\Ai\Tools\SearchSimilarChunks;
use App\Models\Book;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.5)]
#[Timeout(120)]
class BookChatAgent implements Agent, BelongsToBook, HasMiddleware, HasTools
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
        You are a helpful writing assistant for the book '{$this->book->title}' by {$this->book->author}.
        The manuscript is written in {$this->book->language}.

        You can answer questions about the manuscript, its characters, plot, themes, and writing style.
        Use the available tools to search through the manuscript and retrieve relevant context before answering.

        Be concise, helpful, and grounded in the actual text. If you're unsure about something, say so rather than guessing.
        When referencing specific parts of the manuscript, mention the chapter title or number.
        INSTRUCTIONS;
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
