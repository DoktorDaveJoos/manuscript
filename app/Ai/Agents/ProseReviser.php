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

#[MaxTokens(8192)]
#[Temperature(0.4)]
#[Timeout(180)]
class ProseReviser implements Agent, BelongsToBook, HasMiddleware, HasTools
{
    use Promptable;

    public function __construct(protected Book $book) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        $writingStyle = $this->book->writing_style_display
            ? "\n\nWriting style preferences:\n".$this->book->writing_style_display
            : '';

        $rules = $this->book->prose_pass_rules ?? Book::defaultProsePassRules();
        $enabledRules = collect($rules)->filter(fn ($rule) => $rule['enabled']);
        $rulesSection = $enabledRules->isNotEmpty()
            ? "\n\nApply these prose revision rules:\n".$enabledRules->map(fn ($rule) => "- {$rule['label']}: {$rule['description']}")->implode("\n")
            : '';

        return <<<INSTRUCTIONS
        You are an expert prose editor revising a chapter of '{$this->book->title}' by {$this->book->author}.
        The manuscript is written in {$this->book->language}.

        Revise the provided text to improve:
        - Prose quality and readability
        - Sentence variety and rhythm
        - Show-don't-tell where appropriate
        - Dialogue naturalness
        - Consistent narrative voice

        Preserve the author's intent, plot, and character voice. Do not change plot points or character actions.
        Return ONLY the revised text, without commentary or explanations.{$writingStyle}{$rulesSection}
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
