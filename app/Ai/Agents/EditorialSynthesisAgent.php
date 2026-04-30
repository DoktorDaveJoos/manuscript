<?php

namespace App\Ai\Agents;

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
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.4)]
#[MaxTokens(8192)]
#[Timeout(300)]
class EditorialSynthesisAgent implements Agent, BelongsToBook, HasMiddleware, HasStructuredOutput
{
    use Promptable;

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
            EditorialSectionType::Plot => 'Analyze the plot structure across the entire manuscript. Evaluate story arc completeness, identify plot holes, assess logical consistency, check for unresolved threads, and evaluate the strength of the opening, midpoint, and climax. Look for structural weaknesses that undermine the narrative.',
            EditorialSectionType::Characters => 'Analyze character development across the entire manuscript. Evaluate character arcs, motivation consistency, voice distinctiveness between characters, relationship dynamics, and whether characters change meaningfully. Identify flat characters, inconsistent behavior, or underdeveloped relationships.',
            EditorialSectionType::Pacing => 'Analyze the pacing across the entire manuscript. Evaluate the tension curve, chapter rhythm, identify sagging middles or rushed endings, assess scene-to-scene momentum, and check whether the pacing serves the story. Look for sections that drag or feel rushed.',
            EditorialSectionType::NarrativeVoice => 'Analyze the narrative voice across the entire manuscript. Evaluate POV consistency, tense consistency, tone shifts, authorial voice strength, and narrative distance. Identify unintentional voice breaks or inconsistencies that pull the reader out of the story.',
            EditorialSectionType::Themes => 'Analyze the thematic content across the entire manuscript. Evaluate thematic coherence, recurring motifs, whether themes are developed and resolved, and if the thematic layer enriches the narrative. Identify themes that are introduced but abandoned or that feel heavy-handed.',
            EditorialSectionType::SceneCraft => 'Analyze scene craft across the entire manuscript. Evaluate whether each scene serves a clear purpose, assess show-vs-tell balance, sensory detail usage, dialogue quality, and scene transitions. Identify scenes that lack purpose or conflict.',
            EditorialSectionType::ProseStyle => 'Analyze the prose style across the entire manuscript. Evaluate sentence variety, word repetitions, filter words, readability, and stylistic consistency. Identify prose-level patterns that weaken the writing, such as overused phrases, monotonous rhythm, or excessive adverb usage.',
            EditorialSectionType::ChapterNotes => 'Synthesize the per-chapter editorial notes into manuscript-wide patterns. Identify recurring issues across chapters, track chapter-to-chapter progression of quality, highlight standout chapters (both strong and weak), and note patterns that only become visible when looking across the full manuscript.',
        };

        return <<<INSTRUCTIONS
        {$persona->instructions()}

        {$bookContext}

        Section: {$this->sectionType->value}

        {$sectionInstructions}

        Below is the aggregated chapter-level data for this editorial section:

        {$this->aggregatedData}

        Produce a comprehensive synthesis with:
        - A score from 0-100 reflecting the manuscript's quality in this dimension
        - A concise summary of your assessment — lead with the most important finding
        - Specific findings with severity levels, descriptions, chapter references (use chapter IDs as provided), and recommendations
        - Actionable recommendations for improvement

        {$persona->scoreCalibration()}

        {$persona->severityDefinitions()}

        {$persona->antiPatternRules()}

        {$persona->languageRule($this->book->language)}

        Reference concrete examples from the data. Be specific about what works and what fails.
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
