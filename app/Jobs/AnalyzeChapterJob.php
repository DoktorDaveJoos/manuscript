<?php

namespace App\Jobs;

use App\Ai\Agents\ChapterAnalyzer;
use App\Ai\Agents\CharacterExtractor;
use App\Ai\Agents\ManuscriptAnalyzer;
use App\Enums\AnalysisType;
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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        $this->runCharacterExtraction();
        $this->runManuscriptAnalyses();

        $this->chapter->update([
            'analysis_status' => 'completed',
            'analysis_error' => null,
            'analyzed_at' => now(),
        ]);
    }

    private function runChapterAnalysis(): void
    {
        $this->chapter->loadMissing('currentVersion');
        $content = $this->chapter->currentVersion?->content;

        if (! $content) {
            return;
        }

        $plainText = strip_tags($content);
        $words = preg_split('/\s+/', $plainText);
        $capped = count($words) > 3000 ? implode(' ', array_slice($words, 0, 3000)) : $plainText;

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

        $this->chapter->update([
            'summary' => $response['summary'] ?? null,
            'tension_score' => $response['tension_score'] ?? null,
            'hook_score' => $response['hook_score'] ?? null,
            'hook_type' => $response['hook_type'] ?? null,
        ]);

        $plotPoints = $response['plot_points'] ?? [];
        foreach ($plotPoints as $point) {
            if (! is_array($point) || empty($point['description'])) {
                continue;
            }

            $this->book->plotPoints()->create([
                'title' => $point['title'] ?? $point['description'],
                'description' => $point['description'],
                'type' => $point['type'] ?? 'worldbuilding',
                'status' => 'fulfilled',
                'actual_chapter_id' => $this->chapter->id,
                'sort_order' => $this->chapter->reader_order,
                'is_ai_derived' => true,
            ]);
        }
    }

    private function runCharacterExtraction(): void
    {
        $content = $this->chapter->currentVersion?->content;

        if (! $content) {
            return;
        }

        $agent = new CharacterExtractor($this->book);
        $response = $agent->prompt("Extract all characters from the following chapter text:\n\n{$content}");

        $characters = $response['characters'] ?? [];

        foreach ($characters as $characterData) {
            if (! is_array($characterData) || empty($characterData['name'])) {
                continue;
            }

            $this->book->characters()->updateOrCreate(
                ['name' => $characterData['name']],
                [
                    'aliases' => $characterData['aliases'] ?? null,
                    'description' => $characterData['description'] ?? null,
                    'is_ai_extracted' => true,
                    'first_appearance' => $this->chapter->id,
                ],
            );
        }
    }

    private function runManuscriptAnalyses(): void
    {
        $analysisTypes = [
            AnalysisType::Pacing,
            AnalysisType::Density,
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
