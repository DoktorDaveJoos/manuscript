<?php

namespace App\Services;

use App\Jobs\Preparation\AnalyzeChapter;
use App\Jobs\Preparation\BuildStoryBible;
use App\Jobs\Preparation\ChunkAndEmbedChapter;
use App\Jobs\Preparation\ConsolidateEntities;
use App\Jobs\Preparation\ExtractWritingStyle;
use App\Models\AiPreparation;
use App\Models\Book;
use Illuminate\Support\Facades\Bus;

class AiPreparationRetryService
{
    /**
     * Re-dispatch jobs for entries currently recorded in phase_errors, grouped
     * so that each affected chapter / singleton phase runs at most once.
     *
     * @return array{dispatched: int, cleared: list<array{phase: string, chapter_id: ?int}>}
     */
    public function retry(Book $book, AiPreparation $preparation): array
    {
        $errors = $preparation->phase_errors ?? [];

        if (empty($errors)) {
            return ['dispatched' => 0, 'cleared' => []];
        }

        $chapterPhases = ['chunking', 'chapter_analysis', 'entity_extraction', 'manuscript_analysis'];

        $chapterJobsByChapter = [];
        $chunkJobsByChapter = [];
        $singletons = [];
        $cleared = [];

        foreach ($errors as $entry) {
            $phase = $entry['phase'] ?? null;
            $chapterId = $entry['chapter_id'] ?? null;

            if (! $phase) {
                continue;
            }

            $cleared[] = ['phase' => $phase, 'chapter_id' => $chapterId];

            // Chunking runs a distinct job from analysis — track separately.
            if ($phase === 'chunking' && $chapterId !== null) {
                $chunkJobsByChapter[$chapterId] = true;

                continue;
            }

            if (in_array($phase, $chapterPhases, true) && $chapterId !== null) {
                $chapterJobsByChapter[$chapterId] = true;

                continue;
            }

            $singletons[$phase] = true;
        }

        $jobs = [];

        foreach (array_keys($chunkJobsByChapter) as $chapterId) {
            $jobs[] = new ChunkAndEmbedChapter($book, $preparation, (int) $chapterId);
        }

        foreach (array_keys($chapterJobsByChapter) as $chapterId) {
            $jobs[] = new AnalyzeChapter($book, $preparation, (int) $chapterId);
        }

        foreach (array_keys($singletons) as $phase) {
            match ($phase) {
                'writing_style' => $jobs[] = new ExtractWritingStyle($book, $preparation),
                'story_bible' => $jobs[] = new BuildStoryBible($book, $preparation),
                'entity_extraction' => $jobs[] = new ConsolidateEntities($book, $preparation),
                default => null,
            };
        }

        if (empty($jobs)) {
            return ['dispatched' => 0, 'cleared' => []];
        }

        $preparation->clearPhaseErrors($cleared);
        $preparation->resetConsecutiveFailures();

        $preparation->update([
            'status' => 'running',
            'current_phase' => 'retry',
            'current_phase_progress' => 0,
            'current_phase_total' => count($jobs),
        ]);

        $batch = Bus::batch($jobs)->allowFailures()->dispatch();
        $preparation->update(['batch_id' => $batch->id]);

        return ['dispatched' => count($jobs), 'cleared' => $cleared];
    }
}
