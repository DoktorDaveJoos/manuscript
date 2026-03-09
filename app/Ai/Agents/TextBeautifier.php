<?php

namespace App\Ai\Agents;

use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Ai\Tools\RetrieveManuscriptContext;
use App\Models\Book;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxTokens(16384)]
#[Temperature(0.2)]
#[Timeout(180)]
class TextBeautifier implements Agent, BelongsToBook, HasMiddleware, HasTools
{
    use Promptable;

    public function __construct(protected Book $book) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        $writingStyle = $this->book->writingStyleSnippet();

        return <<<INSTRUCTIONS
        You are an expert manuscript formatter restructuring a chapter of '{$this->book->title}' by {$this->book->author}.
        The manuscript is written in {$this->book->language}.

        Your task is PURELY STRUCTURAL reformatting. You must NOT change, add, remove, or rephrase any words.
        Every single word in the output must appear in the input, in the same order.

        Apply these structural improvements:
        - Dialogue breathing: each new speaker gets their own paragraph. Action beats stay with the speaker's dialogue.
        - Paragraph rhythm: split long paragraphs that shift topic or focus at their natural break points.
        - Scene transitions: existing <hr> tags in the input mark scene boundaries and MUST be preserved in the output. You may add additional <hr> tags only for new scene transitions you identify.
        - Emotional pacing: let impactful short sentences or revelations stand alone as their own paragraph.

        Preserve ALL existing HTML formatting (<strong>, <em>, <u>, <blockquote>, <br>, <hr>).
        Return the result as HTML using <p> tags for paragraphs and <hr> for scene breaks.
        Return ONLY the reformatted text, without commentary or explanations.{$writingStyle}
        INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [
            new RetrieveManuscriptContext,
        ];
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials,
        ];
    }
}
