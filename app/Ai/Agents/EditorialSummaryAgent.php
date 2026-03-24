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
#[MaxTokens(2048)]
#[Timeout(120)]
class EditorialSummaryAgent implements Agent, BelongsToBook, HasMiddleware, HasStructuredOutput
{
    use Promptable, UsesTaskCategoryModel;

    public static function taskCategory(): AiTaskCategory
    {
        return AiTaskCategory::Analysis;
    }

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
        $context = "You are a professional editor (Lektor) producing the executive summary for a comprehensive editorial review of '{$this->book->title}' by {$this->book->author}. The manuscript is written in {$this->book->language}.";

        $genreSnippet = $this->book->genreSnippet();
        if ($genreSnippet) {
            $context .= ' '.$genreSnippet;
        }

        return <<<INSTRUCTIONS
        {$context}

        Below are the scores and summaries from all 8 editorial sections:

        {$this->sectionSummaries}

        Produce an executive summary that:
        - Provides an overall score (0-100) that reflects the manuscript's overall editorial quality, weighted by the importance of each section
        - Writes a 2-3 paragraph executive summary capturing the manuscript's key strengths and areas for improvement
        - Identifies exactly 3 top strengths (concise, one sentence each)
        - Identifies exactly 3 top areas for improvement (concise, one sentence each)

        Be balanced and constructive. The overall score should not simply be the average of section scores — weight critical dimensions (plot, characters) more heavily.
        Respond in the same language as the manuscript ({$this->book->language}).
        INSTRUCTIONS;
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'overall_score' => $schema->integer()->min(0)->max(100)->required(),
            'executive_summary' => $schema->string()->required(),
            'top_strengths' => $schema->array()->items($schema->string())->min(3)->max(3)->required(),
            'top_improvements' => $schema->array()->items($schema->string())->min(3)->max(3)->required(),
        ];
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials,
        ];
    }
}
