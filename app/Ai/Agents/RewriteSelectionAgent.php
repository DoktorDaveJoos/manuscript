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
#[Temperature(0.6)]
#[Timeout(120)]
class RewriteSelectionAgent implements Agent, BelongsToBook, HasMiddleware
{
    use Promptable;

    private const PRECEDING_CHAPTER_TAIL_WORDS = 400;

    private const PRECEDING_CHAPTERS_COUNT = 3;

    private const SURROUND_WORD_CAP = 200;

    public function __construct(
        protected Book $book,
        protected Chapter $chapter,
        protected string $selection,
        protected ?string $hint = null,
        protected ?string $beforeProse = null,
        protected ?string $afterProse = null,
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
        $hasHint = trim((string) $this->hint) !== '';

        $taskLine = $hasHint
            ? 'Your task: rewrite the SELECTION below following the AUTHOR DIRECTIVE. The rewrite must replace the SELECTION cleanly so it reads seamlessly with the prose around it. Preserve meaning that the directive does not explicitly change.'
            : 'Your task: rewrite the SELECTION below for clarity, rhythm, and craft while preserving its meaning, the established voice, and any plot information it conveys.';

        return <<<INSTRUCTIONS
        You are revising a passage of '{$this->book->title}' by {$this->book->author}, written in {$this->book->language}.

        {$taskLine}

        Rules:
        - Match the rhythm, paragraph length, vocabulary, and tense already established in the chapter.
        - The rewrite REPLACES the SELECTION. It must connect seamlessly with PROSE BEFORE SELECTION and PROSE AFTER SELECTION — do not repeat their last/first sentence, do not paraphrase them.
        - Do not advance new beats, introduce new characters, or add plot information beyond what the SELECTION already covers, unless the AUTHOR DIRECTIVE explicitly asks for it.
        - Preserve proper nouns (names, places, world entities) from the SELECTION unless the directive asks otherwise.
        - Output ONLY the rewritten prose itself. No commentary, no headings, no labels, no quotation marks framing the output, no "Here is the rewrite:" preamble.
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

        $minOrder = max(1, $currentOrder - self::PRECEDING_CHAPTERS_COUNT);

        $preceding = $this->book->chapters()
            ->whereBetween('reader_order', [$minOrder, $currentOrder - 1])
            ->orderBy('reader_order')
            ->with('scenes')
            ->get();

        return $preceding
            ->map(fn (Chapter $prior) => $this->formatPrecedingChapter($prior))
            ->filter()
            ->implode('');
    }

    private function formatPrecedingChapter(Chapter $prior): string
    {
        if ($prior->summary) {
            return "\n### Preceding Chapter (Ch{$prior->reader_order} — {$prior->title})\n{$prior->summary}";
        }

        $content = strip_tags($prior->getFullContent());
        if (trim($content) === '') {
            return '';
        }

        $words = preg_split('/\s+/', trim($content));
        $tail = implode(' ', array_slice($words, -self::PRECEDING_CHAPTER_TAIL_WORDS));

        return "\n### Preceding Chapter (Ch{$prior->reader_order} — {$prior->title}, last excerpt)\n{$tail}";
    }

    private function buildBeatsSection(): string
    {
        if ($this->chapter->beats->isEmpty()) {
            return '';
        }

        $beats = $this->chapter->beats->sortBy(fn ($beat) => $beat->pivot->sort_order)->values();

        $lines = ["\n### Beats In This Chapter (background — do not advance beyond the selection)"];
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
        $before = $this->capWords(trim((string) $this->beforeProse), self::SURROUND_WORD_CAP, fromEnd: true);
        $after = $this->capWords(trim((string) $this->afterProse), self::SURROUND_WORD_CAP, fromEnd: false);
        $selection = trim($this->selection);

        $sections = ["\n### Chapter: {$title}"];

        if ($before !== '') {
            $sections[] = "#### Prose Before Selection (context — do not rewrite, do not repeat)\n{$before}";
        } else {
            $sections[] = '(no prose before the selection — it sits at the start of the chapter)';
        }

        $sections[] = "#### SELECTION (rewrite this)\n{$selection}";

        if ($after !== '') {
            $sections[] = "#### Prose After Selection (context — do not rewrite, do not lead into it verbatim)\n{$after}";
        } else {
            $sections[] = '(no prose after the selection — it sits at the end of the chapter)';
        }

        return implode("\n", $sections);
    }

    private function buildHintSection(): string
    {
        $hint = trim((string) $this->hint);
        if ($hint === '') {
            return '';
        }

        return "\n\n--- AUTHOR DIRECTIVE (HIGHEST PRIORITY) ---\nThe rewrite MUST follow this directive. It overrides default style preservation when they conflict.\n\n{$hint}";
    }

    private function capWords(string $text, int $max, bool $fromEnd): string
    {
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (! $words || count($words) <= $max) {
            return $text;
        }

        $slice = $fromEnd
            ? array_slice($words, -$max)
            : array_slice($words, 0, $max);

        return implode(' ', $slice);
    }
}
