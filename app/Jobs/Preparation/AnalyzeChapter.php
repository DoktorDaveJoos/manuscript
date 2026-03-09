<?php

namespace App\Jobs\Preparation;

use App\Ai\Agents\ChapterAnalyzer;
use App\Ai\Agents\CharacterExtractor;
use App\Models\AiPreparation;
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
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

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

        $chapter = $this->book->chapters()
            ->with('currentVersion')
            ->find($this->chapterId);

        if (! $chapter || ! $chapter->currentVersion?->content) {
            $this->preparation->increment('current_phase_progress');

            return;
        }

        $this->runChapterAnalysis($chapter);
        $this->runCharacterExtraction($chapter);

        $this->preparation->increment('current_phase_progress');
    }

    private function runChapterAnalysis(Chapter $chapter): void
    {
        $content = $chapter->currentVersion->content;
        $plainText = strip_tags($content);
        $words = preg_split('/\s+/', $plainText);
        $capped = count($words) > 3000 ? implode(' ', array_slice($words, 0, 3000)) : $plainText;

        // Build rolling context from preceding chapters (same pattern as AnalyzeChapterJob)
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

            $chapter->update([
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
                    'actual_chapter_id' => $chapter->id,
                    'sort_order' => $chapter->reader_order,
                    'is_ai_derived' => true,
                ]);
            }
        } catch (Throwable $e) {
            $this->preparation->appendPhaseError('chapter_analysis', $chapter->title, $e->getMessage());
        }
    }

    private function runCharacterExtraction(Chapter $chapter): void
    {
        $content = $chapter->currentVersion->content;

        try {
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
                        'first_appearance' => $chapter->id,
                    ],
                );
            }
        } catch (Throwable $e) {
            $this->preparation->appendPhaseError('character_extraction', $chapter->title, $e->getMessage());
        }
    }
}
