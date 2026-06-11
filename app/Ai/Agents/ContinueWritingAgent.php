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

    private const PRECEDING_CHAPTERS_COUNT = 3;

    public function __construct(
        protected Book $book,
        protected Chapter $chapter,
        protected ?string $hint = null,
        protected int $wordGoal = 120,
        protected ?string $beforeProse = null,
        protected ?string $afterProse = null,
        protected bool $afterTruncated = false,
        protected bool $sceneFollows = false,
        protected string $chapterLink = 'auto',
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
        $rulesSection = $this->buildStyleRulesSection();
        $hasHint = trim((string) $this->hint) !== '';
        $isInline = $this->isInlineMode();

        $wordGoal = $this->wordGoal;

        $anchor = $isInline
            ? 'from exactly the last character of PROSE BEFORE CURSOR — even mid-sentence or mid-word. Do not insert a leading space, do not force capitalization, and do not break into a new paragraph'
            : 'from exactly where the chapter prose ends';

        $taskLine = $hasHint
            ? "Your task: continue the prose {$anchor}. The author has issued a directive (see AUTHOR DIRECTIVE below) — the next approximately {$wordGoal} words MUST cover what the directive says. After roughly {$wordGoal} words, finish the current sentence and stop. The directive overrides default beat progression for this paragraph; treat beats, characters, and world entities as background only."
            : "Your task: continue the prose {$anchor}. Write approximately {$wordGoal} words, then finish the current sentence and stop. Do not write past the end of that sentence.";

        $progressionRule = $hasHint
            ? '- The AUTHOR DIRECTIVE decides what this paragraph covers. Do not advance beats at the expense of the directive — beats are reference material, not the agenda for this stretch of prose.'
            : '- Identify which numbered beat the next paragraph should advance based on the prose written so far, and advance it.';

        $inlineRules = $isInline
            ? "\n        - You are INSERTING prose between PROSE BEFORE CURSOR and PROSE AFTER CURSOR. PROSE AFTER CURSOR is the author's existing draft that comes LATER in the chapter — it is NOT what you are about to write."
                ."\n        - Bridge the gap: pick up from the end of PROSE BEFORE CURSOR, and shape your final sentence so that the first sentence of PROSE AFTER CURSOR reads as its natural next sentence."
                ."\n        - Hand off, do not echo: never restate, paraphrase, or pre-empt any beat, line, or event from PROSE AFTER CURSOR — everything it describes must still happen after your insertion."
            : $this->buildSceneFollowsRule();

        return <<<INSTRUCTIONS
        You are continuing the draft of '{$this->book->title}' by {$this->book->author}, written in {$this->book->language}.

        {$taskLine}

        Rules:
        - Match the rhythm, paragraph length, vocabulary, and tense already established in the chapter.
        {$progressionRule}
        - Output ONLY the continuation prose itself. No commentary, no headings, no labels, no quotation marks framing the output, no "Here is the continuation:" preamble.
        - Do not repeat or restate the last sentence already written; pick up cleanly from it.{$inlineRules}
        - LANGUAGE: write in {$this->book->language}.{$writingStyle}{$rulesSection}{$context}{$hintSection}
        INSTRUCTIONS;
    }

    /**
     * The user message sent alongside the instructions. Mode-aware: insertion
     * vs. append, with a pointer to the author directive when one exists.
     */
    public function userMessage(): string
    {
        $message = $this->isInlineMode()
            ? 'Insert the continuation at the cursor, between the prose before and after it.'
            : 'Continue writing the chapter from where the prose ends.';

        if (trim((string) $this->hint) !== '') {
            $message .= ' Follow the AUTHOR DIRECTIVE.';
        }

        return $message;
    }

    private function buildSceneFollowsRule(): string
    {
        if (! $this->sceneFollows) {
            return '';
        }

        return "\n        - Another scene follows later in this chapter. Continue the current scene only — do not wrap up the chapter or write closing lines.";
    }

    private function buildStyleRulesSection(): string
    {
        $enabled = collect($this->book->generationApplicableProsePassRules())
            ->filter(fn ($rule) => $rule['enabled']);

        if ($enabled->isEmpty()) {
            return '';
        }

        $bullets = $enabled
            ->map(fn ($rule) => "- {$rule['label']}: {$rule['description']}")
            ->implode("\n");

        return "\n\nStyle rules (apply while writing):\n{$bullets}";
    }

    private function hasSplit(): bool
    {
        return $this->beforeProse !== null || $this->afterProse !== null;
    }

    private function isInlineMode(): bool
    {
        return trim((string) $this->afterProse) !== '';
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
            ->with(['scenes', 'storyline'])
            ->get();

        $chapters = $preceding
            ->map(fn (Chapter $prior) => $this->formatPrecedingChapter($prior))
            ->filter()
            ->implode('');

        if ($chapters === '') {
            return '';
        }

        return "\n### Preceding Chapters\n{$this->precedingChaptersRole()}".$chapters;
    }

    /**
     * What the preceding chapters are FOR. Without an explicit role the model
     * tends to weld the new prose onto the previous chapter's final scene.
     */
    private function precedingChaptersRole(): string
    {
        return match ($this->resolvedChapterLink()) {
            'fresh' => "Continuity background only. The author wants a FRESH opening for this chapter: do not continue the preceding chapter's final scene and do not echo its closing lines. Open a new scene — a different time, place, or thread — guided by this chapter's beats.",
            'continue' => 'The author wants this chapter to pick up DIRECTLY where the most recent preceding chapter ends — same scene, same moment, continuous action. Use its ending as your starting point.',
            default => 'Continuity background only: keep names, facts, timeline, and tone consistent with these chapters, but do not treat them as the start of your continuation — the current chapter stands on its own unless its beats say otherwise.',
        };
    }

    /**
     * The chapter-link choice only makes sense when the continuation opens the
     * chapter; once prose exists before the cursor it degrades to auto.
     */
    private function resolvedChapterLink(): string
    {
        if (! in_array($this->chapterLink, ['fresh', 'continue'], true)) {
            return 'auto';
        }

        return $this->isChapterOpening() ? $this->chapterLink : 'auto';
    }

    private function isChapterOpening(): bool
    {
        if ($this->hasSplit()) {
            return trim((string) $this->beforeProse) === '';
        }

        return trim(strip_tags($this->chapter->getFullContent())) === '';
    }

    private function formatPrecedingChapter(Chapter $prior): string
    {
        $label = "Ch{$prior->reader_order} — {$prior->title}";
        if ($prior->storyline && $prior->storyline->name) {
            $label .= " (Storyline: {$prior->storyline->name})";
        }

        if ($prior->summary) {
            return "\n#### {$label}\n{$prior->summary}";
        }

        $content = strip_tags($prior->getFullContent());
        if (trim($content) === '') {
            return '';
        }

        $words = preg_split('/\s+/', trim($content));
        $tail = implode(' ', array_slice($words, -self::PRECEDING_CHAPTER_TAIL_WORDS));

        return "\n#### {$label} (last excerpt)\n{$tail}";
    }

    private function buildBeatsSection(): string
    {
        if ($this->chapter->beats->isEmpty()) {
            return '';
        }

        $beats = $this->chapter->beats->sortBy(fn ($beat) => $beat->pivot->sort_order)->values();

        $header = trim((string) $this->hint) !== ''
            ? '### Beats In This Chapter (background reference — the author directive leads)'
            : '### Beats In This Chapter (advance these in order)';

        $lines = ["\n{$header}"];
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

        if ($this->hasSplit()) {
            $before = trim((string) $this->beforeProse);
            $after = trim((string) $this->afterProse);

            if ($before === '' && $after === '') {
                return "\n### Chapter: {$title}\n(no prose written yet — write the opening paragraph)";
            }

            $sections = ["\n### Chapter: {$title}"];

            if ($before === '') {
                $sections[] = '(no prose before the cursor — write the opening)';
            } else {
                $sections[] = "#### Prose Before Cursor (continue from the end of this)\n{$before}";
            }

            if ($after !== '') {
                $afterBlock = "#### Prose After Cursor (the existing draft that follows your insertion — flow into its first sentence; do not repeat it)\n{$after}";
                if ($this->afterTruncated) {
                    $afterBlock .= "\n[The excerpt above is truncated — the chapter draft continues beyond it.]";
                }
                $sections[] = $afterBlock;
            }

            return implode("\n", $sections);
        }

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

        return "\n\n--- AUTHOR DIRECTIVE (HIGHEST PRIORITY) ---\nThe next ~{$this->wordGoal} words MUST cover the following. This is the author's explicit decision about what happens in this stretch of prose — let it override default beat progression. Beats, characters, and world entities remain available as background.\n\n{$hint}";
    }
}
