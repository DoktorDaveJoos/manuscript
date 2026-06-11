<?php

namespace App\Ai\Agents;

use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Models\Book;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Derives a book's prose voice from a manuscript sample. The result is
 * injected into every prose-generating agent (continue writing, rewrite,
 * revise), so the output must be terse, reproducible directives — every
 * extra word here is paid again on each downstream call.
 */
#[Temperature(0.2)]
#[Timeout(150)]
#[UseCheapestModel]
class WritingStyleExtractor implements Agent, BelongsToBook, HasMiddleware, HasStructuredOutput
{
    use Promptable;

    public function __construct(protected Book $book) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
        You are a ghostwriter studying an author's prose voice from a manuscript excerpt.
        Your output is used as style directives for AI writing assistance, so every field must be
        a concrete, reproducible instruction another writer could follow to imitate the voice —
        not academic analysis.

        Rules:
        - Describe only patterns that are consistent across the excerpt AND distinctive to this
          author. Where a dimension is unremarkable, state the plain fact in a few words
          (e.g. "Conventional past tense throughout") — never pad it with invented detail.
        - Each field: at most 3 short sentences. No introductions, no hedging ("seems",
          "appears", "somewhat"), and no repeating another field's content.
        - Do not quote the excerpt, except in distinctive_features when the fingerprint IS a
          recurring phrase or construction.
        - distinctive_features: at most 5 entries, true author fingerprints only. Return an
          empty list rather than inventing entries.
        - Write all field values in the same language the manuscript is written in.
        INSTRUCTIONS;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'narrative_voice' => $schema->string()
                ->description('POV and narrator distance, e.g. "close third person, single POV per scene".')
                ->required(),
            'tense' => $schema->string()
                ->description('Primary tense; mention shifts only when they form a deliberate pattern.')
                ->required(),
            'tone' => $schema->string()
                ->description('Emotional register in a few precise adjectives, plus shifts if systematic.')
                ->required(),
            'sentence_rhythm' => $schema->string()
                ->description('Sentence length pattern and cadence; signature structures (fragments, run-ons) if present.')
                ->required(),
            'paragraph_style' => $schema->string()
                ->description('Paragraph length and density; use of white space or one-line paragraphs.')
                ->required(),
            'vocabulary' => $schema->string()
                ->description('Register and concreteness; sensory or domain preferences only if distinctive.')
                ->required(),
            'figurative_language' => $schema->string()
                ->description('Frequency of metaphor/simile and whether it is ornamental or structural.')
                ->required(),
            'pacing' => $schema->string()
                ->description('Scene-to-summary balance and time compression habits.')
                ->required(),
            'distinctive_features' => $schema->array()->items($schema->string())
                ->description('Up to 5 true author fingerprints — recurring motifs, tics, structural habits. Empty if none stand out.')
                ->required(),
        ];
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials,
        ];
    }
}
