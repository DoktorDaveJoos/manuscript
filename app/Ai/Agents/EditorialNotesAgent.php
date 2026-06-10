<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\CachesSystemPrompt;
use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Enums\EditorialPersona;
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

#[Temperature(0.3)]
#[MaxTokens(4096)]
#[Timeout(180)]
class EditorialNotesAgent implements Agent, BelongsToBook, HasMiddleware, HasProviderOptions, HasStructuredOutput
{
    use CachesSystemPrompt, Promptable;

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

        // Book-level context (writing style, character data) is identical for
        // every chapter, so it stays in the static, cacheable prefix.
        if ($this->writingStyle) {
            $context .= "\n\nWriting style profile:\n{$this->writingStyle}";
        }

        if ($this->characterData) {
            $context .= "\n\nCharacter data:\n{$this->characterData}";
        }

        $static = <<<INSTRUCTIONS
        {$persona->instructions()}

        {$context}

        Analyze the chapter text and produce editorial observations that complement (not duplicate) the chapter's existing analysis. Focus on:

        1. **Narrative voice:** Identify the POV type, tense, any inconsistencies or shifts, and tone observations.
        2. **Themes:** Note recurring motifs, thematic throughlines, and how they connect to other parts of the manuscript.
        3. **Scene craft:** Assess scene purposes, identify show-vs-tell moments, and evaluate sensory detail usage.
        4. **Prose style patterns:** Analyze sentence rhythm, word repetitions, and vocabulary patterns.
        5. **Chapter note:** Write an editor's note (2-3 short paragraphs) the way a working editor writes
           margin notes — this is the note the author will see when editing this chapter, so it must be
           worth their time. Requirements:
           - Anchor every observation in the text: name the scene, beat, or character moment it refers to,
             or quote a short phrase. A note that could be pasted under any chapter is a failed note.
           - For each problem, name the craft issue at stake (e.g. the scene's goal is unclear, the
             reveal is told rather than discovered, the dialogue carries no subtext) and give one
             concrete revision move the author could try — not "improve the pacing" but what to cut,
             move, expand, or reframe.
           - Name at least one thing in this chapter that genuinely works and why it works, so the
             author knows what to keep through revision. Be as specific about this as about the problems.
           - Close with the single highest-impact focus for revising this chapter.

        {$persona->antiPatternRules()}

        {$persona->languageRule($this->book->language)}

        Be specific and reference concrete passages. Every observation must be actionable or name
        something worth keeping — never generic praise, never a diagnosis without a next step.
        INSTRUCTIONS;

        // The per-chapter existing analysis varies, so it sits after the cache
        // breakpoint and is left uncached.
        if (! $this->existingAnalysis) {
            return $static;
        }

        return $static."\n\n".self::CACHE_BREAKPOINT
            ."\n\nExisting chapter analysis (for reference — do not repeat these findings, complement them):\n{$this->existingAnalysis}";
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
