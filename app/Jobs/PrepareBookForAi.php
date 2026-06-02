<?php

namespace App\Jobs;

use App\Enums\PreparationStep;
use App\Jobs\Preparation\AnalyzeChapter;
use App\Jobs\Preparation\BuildStoryBible;
use App\Jobs\Preparation\ChunkAndEmbedChapter;
use App\Jobs\Preparation\CompletePreparation;
use App\Jobs\Preparation\ConsolidateEntities;
use App\Jobs\Preparation\ExtractWritingStyle;
use App\Jobs\Preparation\PhaseTransition;
use App\Models\AiPreparation;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Throwable;

class PrepareBookForAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        private Book $book,
        private AiPreparation $preparation
    ) {}

    public function handle(): void
    {
        $setting = AiSetting::activeProvider();

        if (! $setting || ! $setting->isConfigured()) {
            $this->markFailed('No AI provider configured.');

            return;
        }

        $setting->injectConfig();

        $chapters = $this->book->chapters()
            ->with(['currentVersion', 'scenes'])
            ->orderBy('reader_order')
            ->get();

        // Backfill content hashes for chapters that don't have one yet
        foreach ($chapters as $chapter) {
            if ($chapter->content_hash === null && $chapter->scenes->isNotEmpty()) {
                $chapter->refreshContentHash();
            }
        }

        $steps = $this->preparation->steps ?: PreparationStep::values();

        $this->preparation->update([
            'total_chapters' => $chapters->count(),
            'status' => 'running',
        ]);

        $dirtyChapters = $chapters->filter(fn ($ch) => $ch->needsAiPreparation());

        $jobs = $this->buildJobList($steps, $dirtyChapters);

        $batch = Bus::batch($jobs)
            ->allowFailures()
            ->dispatch();

        $this->preparation->update(['batch_id' => $batch->id]);
    }

    /**
     * Build the flat list of jobs for the batch pipeline, including only the
     * selected steps. Each stage announces itself with a PhaseTransition that
     * also marks the prior stage's phases complete.
     *
     * @param  list<string>  $steps
     * @param  Collection<int, Chapter>  $dirtyChapters
     * @return list<object>
     */
    private function buildJobList(array $steps, Collection $dirtyChapters): array
    {
        $has = fn (string $step) => in_array($step, $steps, true);
        $dirtyWithContent = $dirtyChapters->filter(fn ($ch) => $ch->currentVersion?->content);

        /** @var list<array{phases: list<string>, current: string, total: int, jobs: list<object>}> $stages */
        $stages = [];

        // Semantic index: chunk + embed dirty chapters.
        if ($has('semantic_index')) {
            $stages[] = [
                'phases' => ['chunking', 'embedding'],
                'current' => 'chunking',
                'total' => $dirtyChapters->count(),
                'jobs' => $dirtyChapters
                    ->map(fn ($ch) => new ChunkAndEmbedChapter($this->book, $this->preparation, $ch->id))
                    ->values()
                    ->all(),
            ];
        }

        // Writing style extraction.
        if ($has('writing_style')) {
            $stages[] = [
                'phases' => ['writing_style'],
                'current' => 'writing_style',
                'total' => 1,
                'jobs' => [new ExtractWritingStyle($this->book, $this->preparation)],
            ];
        }

        // Chapter analysis and/or wiki extraction (both run inside AnalyzeChapter).
        $runAnalysis = $has('chapter_analysis');
        $runEntities = $has('wiki');

        if ($runAnalysis || $runEntities) {
            $jobs = $dirtyWithContent
                ->map(fn ($ch) => new AnalyzeChapter($this->book, $this->preparation, $ch->id, $runAnalysis, $runEntities))
                ->values()
                ->all();

            $phases = [];
            if ($runAnalysis) {
                $phases[] = 'chapter_analysis';
            }
            if ($runEntities) {
                $phases[] = 'entity_extraction';
                $jobs[] = new ConsolidateEntities($this->book, $this->preparation);
            }

            $stages[] = [
                'phases' => $phases,
                'current' => $runAnalysis ? 'chapter_analysis' : 'entity_extraction',
                'total' => $dirtyWithContent->count() + ($runEntities ? 1 : 0),
                'jobs' => $jobs,
            ];
        }

        // Story bible.
        if ($has('story_bible')) {
            $stages[] = [
                'phases' => ['story_bible'],
                'current' => 'story_bible',
                'total' => 1,
                'jobs' => [new BuildStoryBible($this->book, $this->preparation)],
            ];
        }

        // Health analysis — the snapshot itself is computed by CompletePreparation.
        $healthSelected = $has('health');
        if ($healthSelected) {
            $stages[] = [
                'phases' => ['health_analysis'],
                'current' => 'health_analysis',
                'total' => 1,
                'jobs' => [],
            ];
        }

        $jobs = [];
        $completedSoFar = [];

        foreach ($stages as $stage) {
            $jobs[] = new PhaseTransition(
                preparation: $this->preparation,
                startPhase: $stage['current'],
                phaseTotal: $stage['total'],
                completedPhases: $completedSoFar,
            );

            array_push($jobs, ...$stage['jobs']);

            $completedSoFar = $stage['phases'];
        }

        // Terminal job: mark the final stage's phases complete, optionally compute
        // the health snapshot, and flip the preparation to completed.
        $jobs[] = new CompletePreparation(
            $this->book,
            $this->preparation,
            finalPhases: $completedSoFar,
            runHealthSnapshot: $healthSelected,
        );

        return $jobs;
    }

    public function failed(?Throwable $exception): void
    {
        $this->markFailed($exception?->getMessage() ?? 'Unknown error');
    }

    private function markFailed(string $message): void
    {
        $this->preparation->update([
            'status' => 'failed',
            'error_message' => $message,
        ]);
    }
}
