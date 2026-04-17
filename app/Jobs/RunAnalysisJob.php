<?php

namespace App\Jobs;

use App\Ai\Agents\ManuscriptAnalyzer;
use App\Enums\AnalysisType;
use App\Jobs\Concerns\ReportsJobFailures;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsJobFailures, SerializesModels;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(
        private Book $book,
        private AnalysisType $analysisType,
        private ?Chapter $chapter = null,
    ) {}

    public function handle(): void
    {
        $setting = AiSetting::activeProvider();

        if (! $setting || ! $setting->isConfigured()) {
            return;
        }

        $setting->injectConfig();

        $agent = new ManuscriptAnalyzer($this->book, $this->analysisType);

        $chapterContext = $this->chapter
            ? " Focus on chapter {$this->chapter->reader_order}, '{$this->chapter->title}'."
            : ' Analyze the entire manuscript.';

        $prompt = "Perform a {$this->analysisType->value} analysis of the manuscript (book ID: {$this->book->id}).{$chapterContext}";

        try {
            $response = $agent->prompt($prompt, timeout: 180);

            $this->book->analyses()->create([
                'chapter_id' => $this->chapter?->id,
                'type' => $this->analysisType,
                'result' => $response->toArray(),
                'ai_generated' => true,
            ]);
        } catch (Throwable $e) {
            report($e);

            throw $e;
        }
    }
}
