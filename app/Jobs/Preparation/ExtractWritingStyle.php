<?php

namespace App\Jobs\Preparation;

use App\Jobs\Concerns\DetectsTransientErrors;
use App\Models\AiPreparation;
use App\Models\Book;
use App\Services\WritingStyleService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExtractWritingStyle implements ShouldQueue
{
    use Batchable, DetectsTransientErrors, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [15];

    public int $timeout = 120;

    public function __construct(
        private Book $book,
        private AiPreparation $preparation,
    ) {}

    public function handle(WritingStyleService $styleService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $chapters = $this->book->chapters()
            ->with('currentVersion')
            ->orderBy('reader_order')
            ->limit(3)
            ->get();

        $sampleTexts = [];
        foreach ($chapters as $chapter) {
            $content = $chapter->currentVersion?->content;
            if ($content) {
                $sampleTexts[] = strip_tags($content);
            }
        }

        if (empty($sampleTexts)) {
            $this->preparation->increment('current_phase_progress');

            return;
        }

        $combinedSample = implode("\n\n---\n\n", $sampleTexts);
        $words = preg_split('/\s+/', $combinedSample);
        if (count($words) > 5000) {
            $combinedSample = implode(' ', array_slice($words, 0, 5000));
        }

        try {
            $style = $styleService->extract($combinedSample, $this->book);
            $this->book->update(['writing_style' => $style]);
            $this->preparation->increment('current_phase_progress');
        } catch (Throwable $e) {
            if ($this->isTransient($e)) {
                throw $e;
            }

            $this->preparation->appendPhaseError('writing_style', null, $e->getMessage());
            $this->preparation->increment('current_phase_progress');
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->preparation->appendPhaseError('writing_style', null, $exception->getMessage());
        $this->preparation->increment('current_phase_progress');
    }
}
