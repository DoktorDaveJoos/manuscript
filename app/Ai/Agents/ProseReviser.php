<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesTaskCategoryModel;
use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Enums\AiTaskCategory;
use App\Models\Book;
use App\Models\Chapter;
use App\Services\StoryBibleService;
use Illuminate\Support\Str;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Attributes\UseSmartestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxTokens(16384)]
#[Temperature(0.4)]
#[Timeout(180)]
#[UseSmartestModel]
class ProseReviser implements Agent, BelongsToBook, HasMiddleware
{
    use Promptable, UsesTaskCategoryModel;

    public static function taskCategory(): AiTaskCategory
    {
        return AiTaskCategory::Writing;
    }

    public function __construct(
        protected Book $book,
        protected Chapter $chapter,
    ) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        $writingStyle = $this->book->writingStyleSnippet();

        $rules = Book::globalProsePassRules();
        $enabledRules = collect($rules)->filter(fn ($rule) => $rule['enabled']);
        $rulesSection = $enabledRules->isNotEmpty()
            ? "\n\nApply these prose revision rules:\n".$enabledRules->map(fn ($rule) => "- {$rule['label']}: {$rule['description']}")->implode("\n")
            : '';

        $contextSections = $this->buildContextSections();

        return <<<INSTRUCTIONS
        You are an expert prose editor revising a chapter of '{$this->book->title}' by {$this->book->author}.
        The manuscript is written in {$this->book->language}.

        Revise the provided text to improve:
        - Prose quality and readability
        - Sentence variety and rhythm
        - Show-don't-tell where appropriate
        - Dialogue naturalness
        - Consistent narrative voice

        Preserve ALL existing HTML formatting (<strong>, <em>, <u>, <s>, <blockquote>, <br>, <hr>, <h1>, <h2>, <h3>, <ul>, <ol>, <li>).
        The <hr> tags mark scene boundaries — do not add or remove <hr> tags.

        Preserve the author's intent, plot, and character voice. Do not change plot points or character actions.
        Return ONLY the revised text, without commentary or explanations.{$writingStyle}{$rulesSection}{$contextSections}
        INSTRUCTIONS;
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials,
        ];
    }

    private function buildContextSections(): string
    {
        $this->chapter->loadMissing(['characters', 'wikiEntries']);

        $sections = [];

        $sections[] = $this->buildCharactersSection();
        $sections[] = $this->buildWikiEntriesSection();
        $sections[] = $this->buildStoryBibleSection();
        $sections[] = $this->buildNarrativePositionSection();

        $content = collect($sections)->filter()->implode("\n");

        return $content ? "\n\n--- MANUSCRIPT CONTEXT ---\n{$content}" : '';
    }

    private function buildCharactersSection(): string
    {
        if ($this->chapter->characters->isEmpty()) {
            return '';
        }

        $lines = ["\n### Characters in This Chapter"];
        foreach ($this->chapter->characters as $character) {
            $parts = ["- **{$character->name}**"];
            if ($character->aliases) {
                $aliases = is_array($character->aliases) ? implode(', ', $character->aliases) : $character->aliases;
                $parts[] = "(aliases: {$aliases})";
            }
            if ($character->pivot->role) {
                $parts[] = "[{$character->pivot->role}]";
            }
            $line = implode(' ', $parts);
            if ($character->description) {
                $line .= ": {$character->description}";
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    private function buildWikiEntriesSection(): string
    {
        if ($this->chapter->wikiEntries->isEmpty()) {
            return '';
        }

        $grouped = $this->chapter->wikiEntries->groupBy(fn ($entry) => $entry->kind->value);

        $lines = ["\n### World Entities in This Chapter"];
        foreach ($grouped as $kind => $entries) {
            $label = Str::plural(Str::ucfirst($kind));
            $lines[] = "\n**{$label}:**";
            foreach ($entries as $entry) {
                $line = "- {$entry->name}";
                if ($entry->description) {
                    $line .= ": {$entry->description}";
                }
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    private function buildStoryBibleSection(): string
    {
        return app(StoryBibleService::class)->getContext($this->book);
    }

    private function buildNarrativePositionSection(): string
    {
        $currentOrder = $this->chapter->reader_order;

        if ($currentOrder === null) {
            return '';
        }

        $surrounding = $this->book->chapters()
            ->whereNotNull('summary')
            ->where('id', '!=', $this->chapter->id)
            ->whereBetween('reader_order', [$currentOrder - 3, $currentOrder + 2])
            ->orderBy('reader_order')
            ->get(['title', 'reader_order', 'summary']);

        if ($surrounding->isEmpty()) {
            return '';
        }

        $lines = ["\n### Narrative Position (Chapter {$currentOrder})"];
        foreach ($surrounding as $ch) {
            $position = $ch->reader_order < $currentOrder ? 'Before' : 'After';
            $lines[] = "- [{$position}] Ch{$ch->reader_order} — {$ch->title}: {$ch->summary}";
        }

        return implode("\n", $lines);
    }
}
