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
        protected Book $book,
        protected string $sectionSummaries = '',
    ) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        $context = "You are a professional editor producing the executive summary for an editorial review of '{$this->book->title}' by {$this->book->author}. The manuscript is written in {$this->book->language}.";

        $genreSnippet = $this->book->genreSnippet();
        if ($genreSnippet) {
            $context .= ' '.$genreSnippet;
        }

        if ($this->sectionSummaries) {
            $context .= "\n\nSection scores and summaries:\n{$this->sectionSummaries}";
        }

        return <<<INSTRUCTIONS
        {$context}

        Based on all 8 editorial section scores and summaries, produce:
        1. An overall score (0-100) that reflects the manuscript's overall quality
        2. An executive summary (2-3 paragraphs) that captures the key takeaways
        3. The top 3 strengths of the manuscript
        4. The top 3 areas for improvement

        Be balanced and constructive. The overall score should reflect a weighted consideration of all sections, not just an average.
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
            'top_strengths' => $schema->array()->items($schema->string())->required(),
            'top_improvements' => $schema->array()->items($schema->string())->required(),
        ];
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials,
        ];
    }
}
