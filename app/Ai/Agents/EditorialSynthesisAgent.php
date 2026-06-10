<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\CachesSystemPrompt;
use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Enums\EditorialPersona;
use App\Enums\EditorialSectionType;
use App\Models\Book;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.4)]
#[MaxTokens(8192)]
#[Timeout(300)]
class EditorialSynthesisAgent implements Agent, BelongsToBook, HasMiddleware, HasProviderOptions, HasStructuredOutput
{
    use CachesSystemPrompt, Promptable;

    public function __construct(
        public Book $book,
        public EditorialSectionType $sectionType,
        public string $aggregatedData,
    ) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        $persona = EditorialPersona::Lektor;

        $bookContext = "You are synthesizing a manuscript-wide editorial assessment for '{$this->book->title}' by {$this->book->author}. The manuscript is written in {$this->book->language}.";

        $genreSnippet = $this->book->genreSnippet();
        if ($genreSnippet) {
            $bookContext .= ' '.$genreSnippet;
        }

        $sectionInstructions = match ($this->sectionType) {
            EditorialSectionType::Plot => 'Analyze the plot structure across the entire manuscript. Assess story arc completeness, logical consistency, and the strength of the opening, midpoint, and climax. Name the structural choices that carry the story — a well-placed reversal, an effective setup that pays off, a climax that earns its weight — and identify plot holes, unresolved threads, and structural weaknesses that undermine the narrative.',
            EditorialSectionType::Characters => 'Analyze character development across the entire manuscript. Assess character arcs, motivation consistency, voice distinctiveness between characters, relationship dynamics, and whether characters change meaningfully. Name the characters and relationships that genuinely live on the page and why they work, and identify flat characters, inconsistent behavior, or underdeveloped relationships.',
            EditorialSectionType::Pacing => 'Analyze the pacing across the entire manuscript. Assess the tension curve, chapter rhythm, and scene-to-scene momentum. Point out where the rhythm serves the story — well-timed quiet chapters, effective acceleration toward turning points — and identify sagging middles, rushed endings, and sections that drag or feel hurried.',
            EditorialSectionType::NarrativeVoice => 'Analyze the narrative voice across the entire manuscript. Assess POV consistency, tense consistency, tone shifts, authorial voice strength, and narrative distance. Name where the voice is distinctive and controlled, and identify unintentional voice breaks or inconsistencies that pull the reader out of the story.',
            EditorialSectionType::Themes => 'Analyze the thematic content across the entire manuscript. Assess thematic coherence, recurring motifs, and whether themes are developed and resolved. Name the thematic threads that genuinely enrich the narrative and how they are woven in, and identify themes that are introduced but abandoned or that feel heavy-handed.',
            EditorialSectionType::SceneCraft => 'Analyze scene craft across the entire manuscript. Assess whether each scene serves a clear purpose, the show-vs-tell balance, sensory detail usage, dialogue quality, and scene transitions. Point to scenes that demonstrate strong craft — and what makes them work — and identify scenes that lack purpose or conflict.',
            EditorialSectionType::ProseStyle => 'Analyze the prose style across the entire manuscript. Assess sentence variety, word repetitions, filter words, readability, and stylistic consistency. Name the stylistic habits worth keeping — strong verbs, effective rhythm, distinctive imagery — and identify prose-level patterns that weaken the writing, such as overused phrases, monotonous rhythm, or excessive adverb usage.',
            EditorialSectionType::ChapterNotes => 'Synthesize the per-chapter editorial notes into manuscript-wide patterns. Identify recurring issues across chapters, track chapter-to-chapter progression of quality, highlight standout chapters (both strong and weak), and note patterns that only become visible when looking across the full manuscript — including recurring strengths the author should keep building on.',
        };

        $break = self::CACHE_BREAKPOINT;

        // Everything that is identical across the eight section calls — persona,
        // book context, output spec, and rubric — sits before the cache
        // breakpoint so it is cached once and reused per section. Only the
        // section name, its instructions, and the aggregated data vary per call.
        return <<<INSTRUCTIONS
        {$persona->instructions()}

        {$bookContext}

        You will be given one editorial dimension (section) and the aggregated chapter-level data for it.

        Produce a comprehensive synthesis with:
        - A score from 0-100 reflecting the manuscript's quality in this dimension
        - A concise summary of your assessment — a fair overall picture that names both what carries
          this dimension and the most important problem
        - Genuine strengths in this dimension: each one specific, tied to concrete chapters, scenes, or
          techniques, with a short note on why it works so the author can build on it. Only list real
          strengths — if there is only one, list one. Never invent strengths to fill a quota.
        - Specific findings with severity levels, descriptions, chapter references (use chapter IDs as provided), and recommendations
        - Actionable recommendations for improvement, framed as concrete revision moves

        {$persona->scoreCalibration()}

        {$persona->severityDefinitions()}

        {$persona->antiPatternRules()}

        {$persona->languageRule($this->book->language)}

        Reference concrete examples from the data. Be specific about what works and what fails.

        {$break}

        Section: {$this->sectionType->value}

        {$sectionInstructions}

        Below is the aggregated chapter-level data for this editorial section:

        {$this->aggregatedData}
        INSTRUCTIONS;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'score' => $schema->integer()->required(),
            'summary' => $schema->string()->required(),
            'strengths' => $schema->array()->items($schema->string())->required(),
            'findings' => $schema->array()->items(
                $schema->object([
                    'severity' => $schema->string()->enum(['critical', 'warning', 'suggestion'])->required(),
                    'description' => $schema->string()->required(),
                    'chapter_references' => $schema->array()->items($schema->integer())->required(),
                    'recommendation' => $schema->string()->required(),
                ])->withoutAdditionalProperties()
            )->required(),
            'recommendations' => $schema->array()->items($schema->string())->required(),
        ];
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials,
        ];
    }
}
