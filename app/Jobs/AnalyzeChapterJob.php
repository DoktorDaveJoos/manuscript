<?php

namespace App\Jobs;

use App\Ai\Agents\ChapterAnalyzer;
use App\Ai\Agents\EntityExtractor;
use App\Ai\Support\TextPrep;
use App\Jobs\Concerns\PersistsChapterAnalysis;
use App\Jobs\Concerns\PersistsExtractedEntities;
use App\Jobs\Concerns\RunsManuscriptAnalyses;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class AnalyzeChapterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, PersistsChapterAnalysis, PersistsExtractedEntities, Queueable, RunsManuscriptAnalyses, SerializesModels;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [15];

    public int $timeout = 600;

    public function __construct(
        private Book $book,
        private Chapter $chapter,
    ) {}

    public function handle(): void
    {
        $setting = AiSetting::activeProvider();

        if (! $setting || ! $setting->isConfigured()) {
            $this->markFailed('No AI provider configured.');

            return;
        }

        $setting->injectConfig();

        $this->chapter->update(['analysis_status' => 'running']);

        try {
            $this->runChapterAnalysis();
        } catch (Throwable $e) {
            $this->markFailed($e->getMessage());

            throw $e;
        }

        $partialFailures = [];

        try {
            $this->runEntityExtraction();
        } catch (Throwable $e) {
            report($e);
            $partialFailures[] = 'entity extraction: '.$e->getMessage();
        }

        try {
            $this->runManuscriptAnalyses($this->book, $this->chapter);
        } catch (Throwable $e) {
            report($e);
            $partialFailures[] = 'manuscript analysis: '.$e->getMessage();
        }

        $this->chapter->refreshContentHash();

        if (empty($partialFailures)) {
            $this->chapter->update([
                'analysis_status' => 'completed',
                'analysis_error' => null,
                'prepared_content_hash' => $this->chapter->content_hash,
                'ai_prepared_at' => now(),
            ]);

            return;
        }

        // Don't advance prepared_content_hash — re-runs should retry the failed step.
        $this->chapter->update([
            'analysis_status' => 'partial',
            'analysis_error' => implode('; ', $partialFailures),
        ]);
    }

    private function runChapterAnalysis(): void
    {
        $this->chapter->loadMissing('currentVersion');
        $content = $this->chapter->currentVersion?->content;

        if (! $content) {
            return;
        }

        $capped = TextPrep::plainTextCapped($content);

        // Build rolling context from preceding chapters
        $precedingChapters = $this->book->chapters()
            ->where('reader_order', '<', $this->chapter->reader_order)
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

        $agent = new ChapterAnalyzer($this->book, $rollingContext);
        $response = $agent->prompt("Analyze this chapter:\n\nTitle: {$this->chapter->title}\n\n{$capped}", timeout: 180);

        $this->persistChapterAnalysis($this->book, $this->chapter, $response->toArray());
    }

    private function runEntityExtraction(): void
    {
        $content = $this->chapter->currentVersion?->content;

        if (! $content) {
            return;
        }

        $capped = TextPrep::plainTextCapped($content);

        $agent = new EntityExtractor($this->book);
        $response = $agent->prompt("Extract all characters and narratively important entities from the following chapter text:\n\n{$capped}", timeout: 180);

        $this->persistExtractedEntities($this->book, $this->chapter, $response->toArray());
    }

    public function failed(?Throwable $exception): void
    {
        $this->markFailed($exception?->getMessage() ?? 'Unknown error');
    }

    private function markFailed(string $message): void
    {
        $this->chapter->update([
            'analysis_status' => 'failed',
            'analysis_error' => $message,
        ]);
    }
}
