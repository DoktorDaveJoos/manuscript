<?php

namespace App\Ai\Agents;

use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Ai\Tools\RetrieveManuscriptContext;
use App\Ai\Tools\SearchSimilarChunks;
use App\Models\Book;
use App\Models\Chapter;
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
#[Timeout(120)]
#[UseSmartestModel]
class BookChatAgent implements Agent, BelongsToBook, Conversational, HasMiddleware, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(
        protected Book $book,
        protected ?Chapter $chapter = null,
    ) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        $writingStyle = $this->book->writingStyleSnippet("The author's prose style (use this to match their voice when generating example text)");

        $chapterContext = '';
        if ($this->chapter) {
            $chapterContext = "\n\nThe user is currently editing Chapter {$this->chapter->reader_order}: \"{$this->chapter->title}\".";
            $chapterContext .= "\nWhen they refer to \"this chapter\", they mean this one.";
            $chapterContext .= "\nTo retrieve its content, use the RetrieveManuscriptContext tool with chapter_id={$this->chapter->id}.";
        }

        return <<<INSTRUCTIONS
        You are a helpful writing assistant for the book '{$this->book->title}' by {$this->book->author}.
        The manuscript is written in {$this->book->language}.

        You can answer questions about the manuscript, its characters, plot, themes, and writing style.
        Use the available tools to search through the manuscript and retrieve relevant context before answering.

        The book ID is {$this->book->id}. Use this when calling tools.

        LANGUAGE RULE: ALL text content you produce MUST be written in {$this->book->language}. Do not mix languages.

        Be concise, helpful, and grounded in the actual text. If you're unsure about something, say so rather than guessing.
        When referencing specific parts of the manuscript, mention the chapter title or number.{$writingStyle}{$chapterContext}
        INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [
            new RetrieveManuscriptContext($this->book->id),
            new SearchSimilarChunks($this->book->id),
        ];
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials,
        ];
    }
}
