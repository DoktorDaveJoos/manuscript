<?php

namespace App\Ai\Agents;

use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Models\Book;
use App\Models\Chapter;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxTokens(2048)]
#[Temperature(0.7)]
#[Timeout(120)]
class ContinueWritingAgent implements Agent, BelongsToBook, HasMiddleware
{
    use Promptable;

    private const PRECEDING_CHAPTER_TAIL_WORDS = 400;

    public function __construct(
        protected Book $book,
        protected Chapter $chapter,
        protected ?string $hint = null,
        protected int $wordGoal = 120,
    ) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        $writingStyle = $this->book->writingStyleSnippet(
            "The author's prose style — match this rhythm, vocabulary, paragraph length, tense, and voice exactly",
        );

        $context = $this->buildContextSections();
        $hintSection = $this->buildHintSection();

        $wordGoal = $this->wordGoal;

        return <<<INSTRUCTIONS
        You are continuing the draft of '{$this->book->title}' by {$this->book->author}, written in {$this->book->language}.

        Your task: continue the prose from exactly where the chapter prose ends. Write approximately {$wordGoal} words, then finish the current sentence and stop. Do not write past the end of that sentence.

        Rules:
        - Match the rhythm, paragraph length, vocabulary, and tense already established in the chapter.
        - Identify which numbered beat the next paragraph should advance based on the prose written so far, and advance it.
        - Output ONLY the continuation prose itself. No commentary, no headings, no labels, no quotation marks framing the output, no "Here is the continuation:" preamble.
        - Do not repeat or restate the last sentence already written; pick up cleanly from it.
        - LANGUAGE: write in {$this->book->language}.{$writingStyle}{$context}{$hintSection}
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
        $this->chapter->loadMissing(['scenes', 'characters', 'wikiEntries', 'beats.plotPoint', 'act', 'storyline']);

        $sections = array_filter([
            $this->buildNarrativePositionSection(),
            $this->buildPrecedingChapterSection(),
            $this->buildBeatsSection(),
            $this->buildCharactersSection(),
            $this->buildWikiEntriesSection(),
            $this->buildChapterProseSection(),
        ]);

        if (empty($sections)) {
            return '';
        }

        return "\n\n--- MANUSCRIPT CONTEXT ---\n".implode("\n", $sections);
    }

    private function buildNarrativePositionSection(): string
    {
        $parts = [];

        if ($this->chapter->act && $this->chapter->act->title) {
            $parts[] = "Act: {$this->chapter->act->title}";
        }
        if ($this->chapter->storyline && $this->chapter->storyline->name) {
            $parts[] = "Storyline: {$this->chapter->storyline->name}";
        }

        $plotPoint = $this->chapter->beats->first()?->plotPoint ?? null;
        if ($plotPoint) {
            $line = "Plot point: {$plotPoint->title}";
            if ($plotPoint->description) {
                $line .= " — {$plotPoint->description}";
            }
            $parts[] = $line;
        }

        if (empty($parts)) {
            return '';
        }

        return "\n### Narrative Position\n".implode("\n", array_map(fn ($p) => "- {$p}", $parts));
    }

    private function buildPrecedingChapterSection(): string
    {
        $currentOrder = $this->chapter->reader_order;
        if ($currentOrder === null || $currentOrder <= 1) {
            return '';
        }

        $preceding = $this->book->chapters()
            ->where('reader_order', $currentOrder - 1)
            ->first();

        if (! $preceding) {
            return '';
        }

        if ($preceding->summary) {
            return "\n### Preceding Chapter (Ch{$preceding->reader_order} — {$preceding->title})\n{$preceding->summary}";
        }

        $preceding->load('scenes');
        $content = strip_tags($preceding->getFullContent());
        if (trim($content) === '') {
            return '';
        }

        $words = preg_split('/\s+/', trim($content));
        $tail = implode(' ', array_slice($words, -self::PRECEDING_CHAPTER_TAIL_WORDS));

        return "\n### Preceding Chapter (Ch{$preceding->reader_order} — {$preceding->title}, last excerpt)\n{$tail}";
    }

    private function buildBeatsSection(): string
    {
        if ($this->chapter->beats->isEmpty()) {
            return '';
        }

        $beats = $this->chapter->beats->sortBy(fn ($beat) => $beat->pivot->sort_order)->values();

        $lines = ["\n### Beats In This Chapter (advance these in order)"];
        foreach ($beats as $index => $beat) {
            $position = $index + 1;
            $title = trim((string) $beat->title);
            $description = trim((string) $beat->description);
            $line = "{$position}.";
            if ($title !== '') {
                $line .= " {$title}";
            }
            if ($description !== '') {
                $line .= ($title !== '' ? ' — ' : ' ').$description;
            }
            if ($title === '' && $description === '') {
                $line .= ' (no description)';
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    private function buildCharactersSection(): string
    {
        if ($this->chapter->characters->isEmpty()) {
            return '';
        }

        $lines = ["\n### Characters In This Chapter"];
        foreach ($this->chapter->characters as $character) {
            $parts = ["- **{$character->name}**"];
            if ($character->aliases) {
                $aliases = is_array($character->aliases)
                    ? implode(', ', $character->aliases)
                    : $character->aliases;
                $parts[] = "(aliases: {$aliases})";
            }
            if ($character->pivot->role) {
                $parts[] = "[{$character->pivot->role}]";
            }
            $line = implode(' ', $parts);
            $desc = $character->fullDescription();
            if ($desc) {
                $line .= ": {$desc}";
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

        $lines = ["\n### World Entities In This Chapter"];
        foreach ($grouped as $entries) {
            $lines[] = "\n**{$entries->first()->kind->pluralLabel()}:**";
            foreach ($entries as $entry) {
                $line = "- {$entry->name}";
                $desc = $entry->fullDescription();
                if ($desc) {
                    $line .= ": {$desc}";
                }
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    private function buildChapterProseSection(): string
    {
        $title = $this->chapter->title ?: 'Untitled chapter';
        $content = $this->chapter->getFullContent();

        if (trim(strip_tags($content)) === '') {
            return "\n### Chapter: {$title}\n(no prose written yet — write the opening paragraph)";
        }

        return "\n### Chapter: {$title} (prose so far — continue from the end)\n{$content}";
    }

    private function buildHintSection(): string
    {
        $hint = trim((string) $this->hint);
        if ($hint === '') {
            return '';
        }

        return "\n\n--- AUTHOR NOTE FOR THIS PARAGRAPH ---\n{$hint}";
    }
}
