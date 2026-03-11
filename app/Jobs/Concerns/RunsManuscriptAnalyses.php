<?php

namespace App\Jobs\Concerns;

use App\Ai\Agents\ManuscriptAnalyzer;
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

        foreach ($analysisTypes as $type) {
            $agent = new ManuscriptAnalyzer($book, $type);

            $prompt = "Perform a {$type->value} analysis of the manuscript (book ID: {$book->id})."
                ." Focus on chapter '{$chapter->title}' (ID: {$chapter->id}).";

            $response = $agent->prompt($prompt);

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
