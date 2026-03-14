<?php

namespace App\Jobs\Preparation;

use App\Ai\Agents\ChapterAnalyzer;
use App\Ai\Agents\EntityExtractor;
use App\Ai\Support\TextPrep;
use App\Jobs\Concerns\DetectsTransientErrors;
use App\Jobs\Concerns\PersistsChapterAnalysis;
use App\Jobs\Concerns\PersistsExtractedEntities;
use App\Jobs\Concerns\RunsManuscriptAnalyses;
use App\Models\AiPreparation;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chapter;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class AnalyzeChapter implements ShouldQueue
{
    use Batchable, DetectsTransientErrors, Dispatchable, InteractsWithQueue, PersistsChapterAnalysis, PersistsExtractedEntities, Queueable, RunsManuscriptAnalyses, SerializesModels;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [15];

    public int $timeout = 300;

    public function __construct(
        private Book $book,
        private AiPreparation $preparation,
        private int $chapterId,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $this->preparation->refresh();
        if ($this->preparation->shouldCircuitBreak()) {
            $this->preparation->appendPhaseError('chapter_analysis', "Chapter #{$this->chapterId}", 'Skipped: too many consecutive failures.');
            $this->preparation->increment('current_phase_progress');

            return;
        }

        $setting = AiSetting::activeProvider();
        if (! $setting || ! $setting->isConfigured()) {
            $this->preparation->appendPhaseError('chapter_analysis', "Chapter #{$this->chapterId}", 'No AI provider configured.');

            return;
        }
        $setting->injectConfig();

        $chapter = $this->book->chapters()
            ->with('currentVersion')
            ->find($this->chapterId);

        if (! $chapter || ! $chapter->currentVersion?->content) {
            $this->preparation->increment('current_phase_progress');

            return;
        }

        $capped = TextPrep::plainTextCapped($chapter->currentVersion->content);

        $analysisOk = $this->runChapterAnalysis($chapter, $capped);
        $entitiesOk = $this->runEntityExtraction($chapter, $capped);

        try {
            $this->runManuscriptAnalyses($this->book, $chapter);
        } catch (Throwable $e) {
            $this->preparation->appendPhaseError('manuscript_analysis', $chapter->title, $e->getMessage());
        }

        if ($analysisOk && $entitiesOk) {
            $chapter->update([
                'prepared_content_hash' => $chapter->content_hash,
                'ai_prepared_at' => now(),
            ]);
            $this->preparation->resetConsecutiveFailures();
        }

        $this->preparation->increment('current_phase_progress');
    }

    /**
     * Handle a final failure after all retries are exhausted.
     */
    public function failed(Throwable $exception): void
    {
        $chapter = $this->book->chapters()->find($this->chapterId);
        $chapterLabel = $chapter?->title ?? "Chapter #{$this->chapterId}";

        $this->preparation->appendPhaseError('chapter_analysis', $chapterLabel, $exception->getMessage());
        $this->preparation->increment('current_phase_progress');

        $failures = $this->preparation->recordConsecutiveFailure();
        if ($failures >= AiPreparation::CIRCUIT_BREAKER_THRESHOLD) {
            $this->batch()?->cancel();
        }
    }

    private function runChapterAnalysis(Chapter $chapter, string $capped): bool
    {
        // Build rolling context from preceding chapters
        $precedingChapters = $this->book->chapters()
            ->where('reader_order', '<', $chapter->reader_order)
            ->whereNotNull('summary')
            ->orderBy('reader_order')
            ->get(['reader_order', 'title', 'summary']);

        $rollingContext = '';
        foreach ($precedingChapters as $ch) {
            $rollingContext .= "Ch{$ch->reader_order} ({$ch->title}): {$ch->summary}\n";
        }

        $contextWords = preg_split('/\s+/', $rollingContext);
        if (count($contextWords) > 750) {
            $rollingContext = implode(' ', array_slice($contextWords, -750));
        }

        try {
            $agent = new ChapterAnalyzer($this->book, $rollingContext);
            $response = $agent->prompt("Analyze this chapter:\n\nTitle: {$chapter->title}\n\n{$capped}");

            $this->persistChapterAnalysis($this->book, $chapter, $response->toArray());

            return true;
        } catch (Throwable $e) {
            if ($this->isTransient($e)) {
                throw $e;
            }

            $this->preparation->appendPhaseError('chapter_analysis', $chapter->title, $e->getMessage());

            return false;
        }
    }

    private function runEntityExtraction(Chapter $chapter, string $capped): bool
    {
        try {
            $agent = new EntityExtractor($this->book);
            $response = $agent->prompt("Extract all characters and narratively important entities from the following chapter text:\n\n{$capped}");

            $this->persistExtractedEntities($this->book, $chapter, $response->toArray());

            return true;
        } catch (Throwable $e) {
            if ($this->isTransient($e)) {
                throw $e;
            }

            $this->preparation->appendPhaseError('entity_extraction', $chapter->title, $e->getMessage());

            return false;
        }
    }
}
