<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesTaskCategoryModel;
use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Enums\AiTaskCategory;
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
        protected Book $book,
        protected string $existingAnalysis = '',
        protected string $writingStyle = '',
        protected string $characterData = '',
    ) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        $context = "You are an editorial analyst reviewing a chapter of '{$this->book->title}' by {$this->book->author}. The manuscript is written in {$this->book->language}.";

        $genreSnippet = $this->book->genreSnippet();
        if ($genreSnippet) {
            $context .= ' '.$genreSnippet;
        }

        if ($this->existingAnalysis) {
            $context .= "\n\nExisting chapter analysis data:\n{$this->existingAnalysis}";
        }

        if ($this->writingStyle) {
            $context .= "\n\nAuthor's writing style profile:\n{$this->writingStyle}";
        }

        if ($this->characterData) {
            $context .= "\n\nCharacter/entity data:\n{$this->characterData}";
        }

        return <<<INSTRUCTIONS
        {$context}

        Provide editorial observations that complement the existing chapter analysis. Focus on dimensions NOT covered by the existing analysis data:

        1. **Narrative Voice**: POV type, tense, tone shifts, authorial voice observations
        2. **Themes**: Motifs, thematic throughlines, thematic observations
        3. **Scene Craft**: Scene purposes, show vs. tell moments, sensory detail quality, dialogue quality
        4. **Prose Style Patterns**: Sentence rhythm, repetitions, vocabulary notes

        Be specific and cite examples from the text. Focus on observations that would help during a professional editorial review.
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
