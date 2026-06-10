<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\CachesSystemPrompt;
use App\Ai\Contracts\BelongsToBook;
use App\Ai\Middleware\InjectProviderCredentials;
use App\Ai\Tools\RetrieveManuscriptContext;
use App\Ai\Tools\SearchSimilarChunks;
use App\Enums\AnalysisType;
use App\Enums\EditorialPersona;
use App\Models\Book;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.3)]
#[Timeout(120)]
class ManuscriptAnalyzer implements Agent, BelongsToBook, HasMiddleware, HasProviderOptions, HasStructuredOutput, HasTools
{
    use CachesSystemPrompt, Promptable;

    /**
     * When $inlineContext is provided the agent runs single-shot: the manuscript
     * context is embedded in the system prompt and no tools are exposed, saving
     * the retrieval round trip that would otherwise replay the full request.
     */
    public function __construct(
        protected Book $book,
        protected AnalysisType $analysisType,
        protected ?string $inlineContext = null,
    ) {}

    public function book(): Book
    {
        return $this->book;
    }

    public function instructions(): Stringable|string
    {
        $persona = EditorialPersona::Lektor;

        $bookContext = "You are analyzing the manuscript '{$this->book->title}' by {$this->book->author}. The manuscript is written in {$this->book->language}.";

        $genreSnippet = $this->book->genreSnippet();
        if ($genreSnippet) {
            $bookContext .= ' '.$genreSnippet;
        }

        $qualityRules = 'Only report genuine problems — inconsistencies, contradictions, logical gaps, or issues that need the author\'s attention. If there are no problems, return an empty findings array. Do not comment on things that are working correctly. Keep each finding to one short sentence — state the problem and where it occurs. No explanations or suggestions in findings. Return at most 3 findings — prioritize the most impactful issues.';

        $summaryRule = 'The summary field should be a brief one-line overall assessment. Findings should list specific issues only — do not repeat the summary content.';

        $personaRules = $persona->instructions()."\n\n".$persona->antiPatternRules()."\n\n".$persona->languageRule($this->book->language);

        $instructions = match ($this->analysisType) {
            AnalysisType::Plothole => "{$personaRules}\n\n{$bookContext} Identify plot holes and inconsistencies in the manuscript. Look for contradictions, unresolved threads, timeline issues, and logical gaps in the story. {$qualityRules}",
            AnalysisType::CharacterConsistency => "{$personaRules}\n\n{$bookContext} Analyze character consistency across the manuscript. Check for voice consistency, behavioral contradictions, knowledge inconsistencies, and character arc coherence. {$qualityRules} {$summaryRule}",
            AnalysisType::PlotDeviation => "{$personaRules}\n\n{$bookContext} Compare the manuscript's actual plot progression against the planned plot points. Identify deviations, abandoned threads, and unplanned developments. {$qualityRules} {$summaryRule}",
            AnalysisType::NextChapterSuggestion => "{$personaRules}\n\n{$bookContext} Based on the current state of the manuscript, suggest what should happen in the next chapter. Consider open plot threads, character arcs, and pacing.",
            AnalysisType::GenreHealth => $this->book->genre
                ? "{$personaRules}\n\n{$bookContext} Evaluate the overall health of the manuscript as a {$this->book->genre->label()}. Assess how well it fulfills genre expectations, conventions, and reader satisfaction. {$qualityRules}"
                : "{$personaRules}\n\n{$bookContext} Evaluate the overall narrative health of the manuscript. Assess suspense, stakes, antagonist presence, and reader tension throughout. {$qualityRules}",
        };

        if ($this->inlineContext === null) {
            return $instructions;
        }

        // The manuscript context varies per chapter, so it sits after the cache
        // breakpoint and is left uncached.
        return $instructions."\n\n".self::CACHE_BREAKPOINT
            ."\n\nManuscript context:\n\n{$this->inlineContext}";
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $base = [
            'score' => $schema->integer()->required(),
            'findings' => $schema->array()->items($schema->string())->required(),
            'recommendations' => $schema->array()->items($schema->string())->required(),
        ];

        if (in_array($this->analysisType, [AnalysisType::CharacterConsistency, AnalysisType::PlotDeviation])) {
            $base['summary'] = $schema->string()->required();
        }

        return $base;
    }

    public function tools(): iterable
    {
        if ($this->inlineContext !== null) {
            return [];
        }

        return [
            new RetrieveManuscriptContext($this->book->id),
            new SearchSimilarChunks($this->book->id),
        ];
    }

    public function middleware(): array
    {
        return [
            new InjectProviderCredentials,
        ];
    }
}
