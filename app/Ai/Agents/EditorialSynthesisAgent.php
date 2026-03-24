<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesTaskCategoryModel;
use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Enums\AiTaskCategory;
use App\Enums\EditorialSectionType;
use App\Models\Book;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
    use Promptable, UsesTaskCategoryModel;

    public static function taskCategory(): AiTaskCategory
    {
        return AiTaskCategory::Analysis;
    }

    public function __construct(
        protected Book $book,
        protected EditorialSectionType $sectionType,
        protected string $aggregatedData = '',
    ) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        $context = "You are a professional editor synthesizing a manuscript-wide editorial review for '{$this->book->title}' by {$this->book->author}. The manuscript is written in {$this->book->language}.";

        $genreSnippet = $this->book->genreSnippet();
        if ($genreSnippet) {
            $context .= ' '.$genreSnippet;
        }

        if ($this->aggregatedData) {
            $context .= "\n\nAggregated chapter-level data for this section:\n{$this->aggregatedData}";
        }

        $sectionInstructions = match ($this->sectionType) {
            EditorialSectionType::Plot => 'Analyze the story structure, arc completeness, plot holes, and logical consistency across the entire manuscript.',
            EditorialSectionType::Characters => 'Analyze character development, motivation, consistency, and voice distinctiveness across the entire manuscript.',
            EditorialSectionType::Pacing => 'Analyze the tension curve, chapter rhythm, sagging middles, and rushed endings across the entire manuscript.',
            EditorialSectionType::NarrativeVoice => 'Analyze POV consistency, tense usage, tone shifts, and authorial voice across the entire manuscript.',
            EditorialSectionType::Themes => 'Analyze thematic coherence, recurring motifs, and whether themes land effectively across the entire manuscript.',
            EditorialSectionType::SceneCraft => 'Analyze scene purpose, show vs. tell balance, sensory detail, and dialogue quality across the entire manuscript.',
            EditorialSectionType::ProseStyle => 'Analyze repetitions, filter words, sentence variety, and readability across the entire manuscript.',
            EditorialSectionType::ChapterNotes => 'Synthesize cross-chapter patterns from per-chapter observations. Identify recurring issues, chapter-to-chapter progression, and standout moments.',
        };

        return <<<INSTRUCTIONS
        {$context}

        {$sectionInstructions}

        Provide:
        1. A score from 0-100 for this editorial dimension
        2. A concise section summary (2-3 paragraphs)
        3. Specific findings with severity levels (critical, warning, suggestion), descriptions, chapter references (use chapter database IDs), and recommendations
        4. Actionable recommendations for improvement

        Be honest and constructive. Not every section needs a high score — identify genuine areas for improvement.
        INSTRUCTIONS;
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'score' => $schema->integer()->min(0)->max(100)->required(),
            'summary' => $schema->string()->required(),
            'findings' => $schema->array()->items($schema->object([
                'severity' => $schema->string()->enum(['critical', 'warning', 'suggestion'])->required(),
                'description' => $schema->string()->required(),
                'chapter_references' => $schema->array()->items($schema->integer())->required(),
                'recommendation' => $schema->string()->required(),
            ]))->required(),
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
