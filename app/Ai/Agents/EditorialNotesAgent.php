<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesTaskCategoryModel;
use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Enums\AiTaskCategory;
use App\Models\Book;
use App\Models\Chapter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.3)]
#[MaxTokens(4096)]
#[Timeout(180)]
class EditorialNotesAgent implements Agent, BelongsToBook, HasMiddleware, HasStructuredOutput
{
    use Promptable, UsesTaskCategoryModel;

    public static function taskCategory(): AiTaskCategory
    {
        return AiTaskCategory::Analysis;
    }

    public function __construct(
        public Book $book,
        public Chapter $chapter,
        public ?array $existingAnalysis = null,
        public ?string $writingStyle = null,
        public ?string $characterData = null,
    ) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        $context = "You are a professional editor (Lektor) producing editorial notes for a chapter of '{$this->book->title}' by {$this->book->author}. The manuscript is written in {$this->book->language}.";

        $genreSnippet = $this->book->genreSnippet();
        if ($genreSnippet) {
            $context .= ' '.$genreSnippet;
        }

        $context .= "\n\nChapter: \"{$this->chapter->title}\"";

        if ($this->existingAnalysis) {
            $analysisJson = json_encode($this->existingAnalysis, JSON_PRETTY_PRINT);
            $context .= "\n\nExisting chapter analysis (for reference — do not repeat these findings, complement them):\n{$analysisJson}";
        }

        if ($this->writingStyle) {
            $context .= "\n\nWriting style profile:\n{$this->writingStyle}";
        }

        if ($this->characterData) {
            $context .= "\n\nCharacter data:\n{$this->characterData}";
        }

        return <<<INSTRUCTIONS
        {$context}

        Analyze the chapter text and produce editorial observations that complement (not duplicate) the existing analysis. Focus on:

        1. **Narrative voice:** Identify the POV type, tense, any inconsistencies or shifts, and tone observations.
        2. **Themes:** Note recurring motifs, thematic throughlines, and how they connect to other parts of the manuscript.
        3. **Scene craft:** Assess scene purposes, identify show-vs-tell moments, and evaluate sensory detail usage.
        4. **Prose style patterns:** Analyze sentence rhythm, word repetitions, and vocabulary patterns.

        Be specific and reference concrete passages. Provide actionable observations, not generic praise.
        Respond in the same language as the manuscript ({$this->book->language}).
        INSTRUCTIONS;
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'narrative_voice' => $schema->object([
                'pov' => $schema->string()->required(),
                'tense' => $schema->string()->required(),
                'observations' => $schema->array()->items($schema->string())->required(),
                'tone_notes' => $schema->string()->required(),
            ])->required(),
            'themes' => $schema->object([
                'motifs' => $schema->array()->items($schema->string())->required(),
                'observations' => $schema->array()->items($schema->string())->required(),
            ])->required(),
            'scene_craft' => $schema->object([
                'scene_purposes' => $schema->array()->items($schema->string())->required(),
                'show_vs_tell' => $schema->array()->items($schema->string())->required(),
                'sensory_detail' => $schema->string()->required(),
            ])->required(),
            'prose_style_patterns' => $schema->object([
                'sentence_rhythm' => $schema->string()->required(),
                'repetitions' => $schema->array()->items($schema->string())->required(),
                'vocabulary_notes' => $schema->string()->required(),
            ])->required(),
        ];
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials,
        ];
    }
}
