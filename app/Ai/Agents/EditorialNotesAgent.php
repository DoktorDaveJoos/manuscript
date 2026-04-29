<?php

namespace App\Ai\Agents;

use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Enums\EditorialPersona;
use App\Models\Book;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Attributes\UseSmartestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.3)]
#[MaxTokens(4096)]
#[Timeout(180)]
#[UseSmartestModel]
class EditorialNotesAgent implements Agent, BelongsToBook, HasMiddleware, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public Book $book,
        public ?string $existingAnalysis = null,
        public ?string $writingStyle = null,
        public ?string $characterData = null,
    ) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        $persona = EditorialPersona::Lektor;

        $context = "You are producing editorial notes for a chapter of '{$this->book->title}' by {$this->book->author}. The manuscript is written in {$this->book->language}.";

        $genreSnippet = $this->book->genreSnippet();
        if ($genreSnippet) {
            $context .= ' '.$genreSnippet;
        }

        if ($this->existingAnalysis) {
            $context .= "\n\nExisting chapter analysis (for reference — do not repeat these findings, complement them):\n{$this->existingAnalysis}";
        }

        if ($this->writingStyle) {
            $context .= "\n\nWriting style profile:\n{$this->writingStyle}";
        }

        if ($this->characterData) {
            $context .= "\n\nCharacter data:\n{$this->characterData}";
        }

        return <<<INSTRUCTIONS
        {$persona->instructions()}

        {$context}

        Analyze the chapter text and produce editorial observations that complement (not duplicate) the existing analysis. Focus on:

        1. **Narrative voice:** Identify the POV type, tense, any inconsistencies or shifts, and tone observations.
        2. **Themes:** Note recurring motifs, thematic throughlines, and how they connect to other parts of the manuscript.
        3. **Scene craft:** Assess scene purposes, identify show-vs-tell moments, and evaluate sensory detail usage.
        4. **Prose style patterns:** Analyze sentence rhythm, word repetitions, and vocabulary patterns.
        5. **Chapter note:** Write a concise editor's note (1-2 short paragraphs) as if you were a working editor writing margin notes. Be direct — lead with what needs the author's attention and why, then acknowledge what's working well. End with a forward-looking note about what to focus on in revision. This is the note the author will see when editing this chapter.

        {$persona->antiPatternRules()}

        {$persona->languageRule($this->book->language)}

        Be specific and reference concrete passages. Provide actionable observations, not generic praise.
        INSTRUCTIONS;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'narrative_voice' => $schema->object([
                'pov' => $schema->string()->required(),
                'tense' => $schema->string()->required(),
                'observations' => $schema->array()->items($schema->string())->required(),
                'tone_notes' => $schema->string()->required(),
            ])->withoutAdditionalProperties()->required(),
            'themes' => $schema->object([
                'motifs' => $schema->array()->items($schema->string())->required(),
                'observations' => $schema->array()->items($schema->string())->required(),
            ])->withoutAdditionalProperties()->required(),
            'scene_craft' => $schema->object([
                'scene_purposes' => $schema->array()->items($schema->string())->required(),
                'show_vs_tell' => $schema->array()->items($schema->string())->required(),
                'sensory_detail' => $schema->string()->required(),
            ])->withoutAdditionalProperties()->required(),
            'prose_style_patterns' => $schema->object([
                'sentence_rhythm' => $schema->string()->required(),
                'repetitions' => $schema->array()->items($schema->string())->required(),
                'vocabulary_notes' => $schema->string()->required(),
            ])->withoutAdditionalProperties()->required(),
            'chapter_note' => $schema->string()->required(),
        ];
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials,
        ];
    }
}
