<?php

namespace App\Ai\Agents;

use App\Ai\Middleware\InjectProviderCredentials;
use App\Ai\Tools\RetrieveManuscriptContext;
use App\Ai\Tools\SearchSimilarChunks;
use App\Enums\AnalysisType;
use App\Models\Book;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.3)]
#[Timeout(120)]
class ManuscriptAnalyzer implements Agent, HasMiddleware, HasStructuredOutput, HasTools
{
    use Promptable;

    public function __construct(
        protected Book $book,
        protected AnalysisType $analysisType,
    ) {}

    public function instructions(): Stringable|string
    {
        $bookContext = "You are analyzing the manuscript '{$this->book->title}' by {$this->book->author}. The manuscript is written in {$this->book->language}.";

        return match ($this->analysisType) {
            AnalysisType::Pacing => "{$bookContext} Analyze the pacing of the manuscript. Evaluate chapter lengths, scene transitions, tension arcs, and narrative momentum. Identify sections that feel rushed or drag.",
            AnalysisType::Plothole => "{$bookContext} Identify plot holes and inconsistencies in the manuscript. Look for contradictions, unresolved threads, timeline issues, and logical gaps in the story.",
            AnalysisType::CharacterConsistency => "{$bookContext} Analyze character consistency across the manuscript. Check for voice consistency, behavioral contradictions, knowledge inconsistencies, and character arc coherence.",
            AnalysisType::Density => "{$bookContext} Analyze the prose density of the manuscript. Evaluate the balance of dialogue, description, action, and exposition. Identify sections that are too dense or too sparse.",
            AnalysisType::PlotDeviation => "{$bookContext} Compare the manuscript's actual plot progression against the planned plot points. Identify deviations, abandoned threads, and unplanned developments.",
            AnalysisType::NextChapterSuggestion => "{$bookContext} Based on the current state of the manuscript, suggest what should happen in the next chapter. Consider open plot threads, character arcs, and pacing.",
            AnalysisType::ChapterHook => "{$bookContext} Analyze the chapter endings (hooks) across the manuscript. Evaluate how effectively each chapter ending compels the reader to continue. Score the overall hook quality.",
            AnalysisType::SceneAudit => "{$bookContext} Audit individual scenes for purpose, conflict, and contribution to the overall narrative. Identify scenes that lack tension or purpose.",
            AnalysisType::ThrillerHealth => "{$bookContext} Evaluate the overall health of the manuscript as a thriller. Assess suspense, stakes, antagonist presence, and reader tension throughout.",
        };
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'score' => $schema->integer()->min(1)->max(10)->required(),
            'findings' => $schema->array()->required(),
            'recommendations' => $schema->array()->required(),
        ];
    }

    public function tools(): iterable
    {
        return [
            new RetrieveManuscriptContext,
            new SearchSimilarChunks,
        ];
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials($this->book),
        ];
    }
}
