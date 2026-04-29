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
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.3)]
#[MaxTokens(2048)]
#[Timeout(120)]
#[UseCheapestModel]
class EditorialSummaryAgent implements Agent, BelongsToBook, HasMiddleware, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public Book $book,
        public string $sectionSummaries,
    ) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        $persona = EditorialPersona::Lektor;

        $context = "You are producing the executive summary for a comprehensive editorial review of '{$this->book->title}' by {$this->book->author}. The manuscript is written in {$this->book->language}.";

        $genreSnippet = $this->book->genreSnippet();
        if ($genreSnippet) {
            $context .= ' '.$genreSnippet;
        }

        return <<<INSTRUCTIONS
        {$persona->instructions()}

        {$context}

        Below are the scores and summaries from all 8 editorial sections:

        {$this->sectionSummaries}

        First, determine if this manuscript is ready for a full editorial review. If the overall quality
        is fundamentally below editorial-review level (overall score would be below 35), set is_pre_editorial
        to true and write the executive_summary as a direct, kind note explaining what foundational work
        is needed before a full review would be useful. List 1-3 specific areas to focus on in top_improvements.
        Leave top_strengths empty and set overall_score to the honest score.

        For manuscripts ready for full review, produce an executive summary that:
        - Provides an overall score (0-100) weighted by section importance (plot and characters weigh most heavily)
        - Writes a 2-3 paragraph executive summary. The opening paragraph may acknowledge the author's ambition
          and the story's potential — not fake praise, but genuine recognition of what they are trying to do.
          Then assess the manuscript's state directly.
        - Lists 1-5 genuine strengths. Only include real strengths — if only 1 exists, list 1. Do not invent strengths to fill a quota.
        - Lists 1-5 areas for improvement, ordered by impact.

        {$persona->scoreCalibration()}

        {$persona->antiPatternRules()}

        {$persona->languageRule($this->book->language)}
        INSTRUCTIONS;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'overall_score' => $schema->integer()->required(),
            'executive_summary' => $schema->string()->required(),
            'top_strengths' => $schema->array()->items($schema->string())->required(),
            'top_improvements' => $schema->array()->items($schema->string())->required(),
            'is_pre_editorial' => $schema->boolean()->required(),
        ];
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials,
        ];
    }
}
