<?php

namespace App\Ai\Agents;

use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Models\Book;
use App\Models\PlotPoint;
use Illuminate\Support\Collection;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Generates a back-cover blurb (German: "Klappentext") from a book's plot board.
 *
 * The instructions encode a researched blurb framework — hook → protagonist →
 * inciting incident → escalation/stakes → cliffhanger → optional positioning line —
 * so the model analyses the planned story and writes selling copy rather than a recap.
 */
#[MaxTokens(800)]
#[Temperature(0.85)]
#[Timeout(120)]
class BlurbAgent implements Agent, BelongsToBook, HasMiddleware
{
    use Promptable;

    public function __construct(protected Book $book) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        $context = $this->buildPlotContext();

        return <<<INSTRUCTIONS
        You are an expert publishing copywriter who writes back-cover blurbs that sell novels to browsing readers.

        Write a back-cover blurb for '{$this->book->title}' by {$this->book->author}.

        A blurb is sales copy, NOT a synopsis. Its power comes from what it WITHHOLDS. Study the planned story below, find its most magnetic thread, and build enticing copy around the SETUP and the central question it raises — never the answers.

        LANGUAGE: write the blurb in {$this->book->language}.

        THE GOLDEN RULE — DO NOT SPOIL:
        Stay inside the opening of the story: the protagonist, the inciting incident, and the question the book poses. Reveal nothing about how the conflict develops, what the mysteries mean, what secrets or twists turn out to be, who is really behind anything, or how any of it resolves. Treat every mystery as a mystery — evoke its pull and the danger around it, never explain it. Do not recap the plot or walk through events. When in doubt, withhold. A reader should finish the blurb intrigued and full of questions, never informed of what happens.

        Shape the blurb to this arc, holding that restraint throughout:
        1. SETUP — Introduce ONE protagonist (a name, a defining role or trait) and the single event that upends their world. Concrete, but contained to the opening.
        2. THE TURN — How that event changes their standing and pulls them deeper, conveyed through threat and atmosphere — not through later plot events.
        3. THE CENTRAL MYSTERY — Name the enigma at the heart of the story (an object, a secret, a force) and make it magnetic. Describe its pull and the danger around it; NEVER what it actually is or how it resolves.
        4. STAKES AS ATMOSPHERE — Let the walls close in. Convey what is at risk through tension and tone, not by narrating events.
        5. CLOSING QUESTION — End the body on a single open, resonant question.
        6. POSITIONING LINE — Close with one short, standalone sentence naming the tone, genre and themes, in the form "A [tone] [genre] about [theme], [theme] and [theme]." Do NOT invent review quotes, awards, author endorsements, or comparisons to real books.

        Craft:
        - Length: about 200–260 words across 4–5 short paragraphs, plus the closing positioning line.
        - Voice: third-person jacket voice in present tense, atmospheric and literary, matched to the genre. Use rhetorical contrast and varied rhythm — short, charged sentences set against longer ones.
        - Ground every line in a concrete specific — a place, an object, a role — never vague abstractions ("an epic journey of self-discovery") or clichés ("heart-stopping, unputdownable").
        - Name at most two characters. No backstory dumps. No chronological list of events.
        - Do NOT use chapter numbers, beat labels, the words "plot point" / "beat", or any meta language about story structure.
        - Output ONLY the finished blurb prose (paragraphs plus the closing positioning line). No title, no headings, no labels, no preamble or sign-off.

        Base the blurb on the planned story below.{$context}
        INSTRUCTIONS;
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials,
        ];
    }

    private function buildPlotContext(): string
    {
        $this->book->loadMissing([
            'acts' => fn ($q) => $q->orderBy('sort_order'),
            'plotPoints' => fn ($q) => $q->orderBy('sort_order'),
            'plotPoints.beats' => fn ($q) => $q->orderBy('sort_order'),
            'characters',
        ]);

        $sections = array_filter([
            $this->buildOverviewSection(),
            $this->buildStructureSection(),
            $this->buildCharactersSection(),
        ]);

        if (empty($sections)) {
            return '';
        }

        return "\n\n--- PLANNED STORY ---\n".implode("\n", $sections);
    }

    private function buildOverviewSection(): string
    {
        $lines = ['### Overview'];

        if ($genre = $this->book->genre?->label()) {
            $lines[] = "- Genre: {$genre}";
        }
        if (filled($this->book->premise)) {
            $lines[] = "- Premise: {$this->book->premise}";
        }

        // Only the genre header is unconditional; suppress the section if nothing else landed.
        if (count($lines) === 1) {
            return '';
        }

        return implode("\n", $lines);
    }

    private function buildStructureSection(): string
    {
        if ($this->book->plotPoints->isEmpty()) {
            return '';
        }

        $plotPointsByAct = $this->book->plotPoints->groupBy('act_id');

        $lines = ["\n### Story Arc"];

        foreach ($this->book->acts as $act) {
            $actPlotPoints = $plotPointsByAct->get($act->id, collect());
            if ($actPlotPoints->isEmpty()) {
                continue;
            }

            $title = $act->title ? ": {$act->title}" : '';
            $description = $act->description ? " — {$act->description}" : '';
            $lines[] = "\n**Act {$act->number}{$title}**{$description}";
            $this->appendPlotPoints($lines, $actPlotPoints);
        }

        // Plot points not pinned to any act still inform the conflict.
        $orphans = $plotPointsByAct->get(null, collect());
        if ($orphans->isNotEmpty()) {
            $lines[] = "\n**Further plot points**";
            $this->appendPlotPoints($lines, $orphans);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, string>  $lines
     * @param  Collection<int, PlotPoint>  $plotPoints
     */
    private function appendPlotPoints(array &$lines, $plotPoints): void
    {
        foreach ($plotPoints as $plotPoint) {
            $description = $plotPoint->description ? " — {$plotPoint->description}" : '';
            $lines[] = "- {$plotPoint->title}{$description}";

            foreach ($plotPoint->beats as $beat) {
                $beatDescription = $beat->description ? " — {$beat->description}" : '';
                $lines[] = "  - {$beat->title}{$beatDescription}";
            }
        }
    }

    private function buildCharactersSection(): string
    {
        if ($this->book->characters->isEmpty()) {
            return '';
        }

        $lines = ["\n### Characters"];

        foreach ($this->book->characters as $character) {
            $aliases = ! empty($character->aliases)
                ? ' (aliases: '.implode(', ', $character->aliases).')'
                : '';
            $description = $character->description ? ": {$character->description}" : '';
            $lines[] = "- {$character->name}{$aliases}{$description}";
        }

        return implode("\n", $lines);
    }
}
