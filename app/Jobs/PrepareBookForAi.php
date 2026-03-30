<?php

namespace App\Jobs;

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

        $this->preparation->update([
            'total_chapters' => $chapters->count(),
            'status' => 'running',
        ]);

        $dirtyChapters = $chapters->filter(fn ($ch) => $ch->needsAiPreparation());

        if ($dirtyChapters->isEmpty()) {
            $this->completeImmediately();

            return;
        }

        $jobs = $this->buildJobList($chapters, $dirtyChapters);

        $batch = Bus::batch($jobs)
            ->allowFailures()
            ->dispatch();

        $this->preparation->update(['batch_id' => $batch->id]);
    }

    private function completeImmediately(): void
    {
        $this->preparation->update([
            'status' => 'completed',
            'completed_phases' => [
                'chunking', 'embedding', 'writing_style',
                'chapter_analysis', 'entity_extraction',
                'story_bible', 'health_analysis',
            ],
        ]);
    }

    /**
     * Build the flat list of jobs for the batch pipeline.
     *
     * @param  Collection<int, Chapter>  $chapters
     * @param  Collection<int, Chapter>  $dirtyChapters
     * @return list<object>
     */
    private function buildJobList(Collection $chapters, Collection $dirtyChapters): array
    {
        $jobs = [];

        // Phase 1+2: Chunk and embed dirty chapters
        $jobs[] = new PhaseTransition(
            preparation: $this->preparation,
            startPhase: 'chunking',
            phaseTotal: $dirtyChapters->count(),
        );

        foreach ($dirtyChapters as $chapter) {
            $jobs[] = new ChunkAndEmbedChapter($this->book, $this->preparation, $chapter->id);
        }

        // Phase 3: Writing style extraction (always runs)
        $jobs[] = new PhaseTransition(
            preparation: $this->preparation,
            startPhase: 'writing_style',
            phaseTotal: 1,
            completedPhases: ['chunking', 'embedding'],
        );

        $jobs[] = new ExtractWritingStyle($this->book, $this->preparation);

        // Phase 4+5: Chapter analysis + entity extraction (dirty chapters only)
        $dirtyWithContent = $dirtyChapters->filter(fn ($ch) => $ch->currentVersion?->content);

        $jobs[] = new PhaseTransition(
            preparation: $this->preparation,
            startPhase: 'chapter_analysis',
            phaseTotal: $dirtyWithContent->count() + 1,
            completedPhases: ['writing_style'],
        );

        foreach ($dirtyChapters as $chapter) {
            if ($chapter->currentVersion?->content) {
                $jobs[] = new AnalyzeChapter($this->book, $this->preparation, $chapter->id);
            }
        }

        $jobs[] = new ConsolidateEntities($this->book, $this->preparation);

        // Phase 6: Story bible (always runs)
        $jobs[] = new PhaseTransition(
            preparation: $this->preparation,
            startPhase: 'story_bible',
            phaseTotal: 1,
            completedPhases: ['chapter_analysis', 'entity_extraction'],
        );

        $jobs[] = new BuildStoryBible($this->book, $this->preparation);

        // Phase 7: Health analysis + completion (always runs)
        $jobs[] = new PhaseTransition(
            preparation: $this->preparation,
            startPhase: 'health_analysis',
            phaseTotal: 1,
            completedPhases: ['story_bible'],
        );

        $jobs[] = new CompletePreparation($this->book, $this->preparation);

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
