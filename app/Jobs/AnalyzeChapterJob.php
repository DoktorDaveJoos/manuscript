<?php

namespace App\Jobs;

use App\Ai\Agents\ChapterAnalyzer;
use App\Ai\Agents\EntityExtractor;
use App\Ai\Agents\ManuscriptAnalyzer;
use App\Ai\Support\TextPrep;
use App\Enums\AnalysisType;
use App\Jobs\Concerns\PersistsChapterAnalysis;
use App\Jobs\Concerns\PersistsExtractedEntities;
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
    use Dispatchable, InteractsWithQueue, PersistsChapterAnalysis, PersistsExtractedEntities, Queueable, SerializesModels;

    public int $tries = 1;

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

        $this->runChapterAnalysis();
        $this->runEntityExtraction();
        $this->runManuscriptAnalyses();

        $this->chapter->update([
            'analysis_status' => 'completed',
            'analysis_error' => null,
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
        $response = $agent->prompt("Analyze this chapter:\n\nTitle: {$this->chapter->title}\n\n{$capped}");

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
        $response = $agent->prompt("Extract all characters and narratively important entities from the following chapter text:\n\n{$capped}");

        $this->persistExtractedEntities($this->book, $this->chapter, $response->toArray());
    }

    private function runManuscriptAnalyses(): void
    {
        $analysisTypes = [
            AnalysisType::CharacterConsistency,
            AnalysisType::PlotDeviation,
        ];

        foreach ($analysisTypes as $type) {
            $agent = new ManuscriptAnalyzer($this->book, $type);

            $prompt = "Perform a {$type->value} analysis of the manuscript (book ID: {$this->book->id})."
                ." Focus on chapter '{$this->chapter->title}' (ID: {$this->chapter->id}).";

            $response = $agent->prompt($prompt);

            $this->book->analyses()->updateOrCreate(
                [
                    'chapter_id' => $this->chapter->id,
                    'type' => $type,
                ],
                [
                    'result' => $response->toArray(),
                    'ai_generated' => true,
                ],
            );
        }
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
