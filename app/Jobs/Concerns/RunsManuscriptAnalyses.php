<?php

namespace App\Jobs\Concerns;

use App\Ai\Agents\ManuscriptAnalyzer;
use App\Ai\Tools\RetrieveManuscriptContext;
use App\Enums\AnalysisType;
use App\Models\Book;
use App\Models\Chapter;

trait RunsManuscriptAnalyses
{
    private function runManuscriptAnalyses(Book $book, Chapter $chapter): void
    {
        $analysisTypes = [
            AnalysisType::CharacterConsistency,
            AnalysisType::PlotDeviation,
        ];

        // Build the manuscript context once and inline it for both analyses,
        // instead of letting each agent fetch it through a tool round trip.
        $context = (new RetrieveManuscriptContext($book->id))->render(chapterId: $chapter->id);

        foreach ($analysisTypes as $type) {
            $agent = new ManuscriptAnalyzer($book, $type, inlineContext: $context);

            $prompt = "Perform a {$type->value} analysis of the manuscript."
                ." Focus on chapter {$chapter->reader_order}, '{$chapter->title}'.";

            $response = $agent->prompt($prompt, timeout: 90);

            $book->analyses()->updateOrCreate(
                [
                    'chapter_id' => $chapter->id,
                    'type' => $type,
                ],
                [
                    'result' => $response->toArray(),
                    'ai_generated' => true,
                ],
            );
        }
    }
}
