<?php

namespace App\Jobs\Preparation;

use App\Jobs\Concerns\DetectsTransientErrors;
use App\Models\AiPreparation;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chunk;
use App\Services\ChunkingService;
use App\Services\EmbeddingService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ChunkAndEmbedChapter implements ShouldQueue
{
    use Batchable, DetectsTransientErrors, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [15];

    public int $timeout = 300;

    public function __construct(
        private Book $book,
        private AiPreparation $preparation,
        private int $chapterId,
    ) {}

    public function handle(ChunkingService $chunking, EmbeddingService $embedding): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $this->preparation->refresh();
        if ($this->preparation->shouldCircuitBreak()) {
            $this->preparation->appendPhaseError('chunking', "Chapter #{$this->chapterId}", 'Skipped: too many consecutive failures.');
            $this->preparation->increment('current_phase_progress');

            return;
        }

        $chapter = $this->book->chapters()
            ->with(['currentVersion', 'scenes'])
            ->find($this->chapterId);

        if (! $chapter || ! $chapter->currentVersion?->content) {
            $this->preparation->increment('current_phase_progress');
            $this->preparation->increment('processed_chapters');

            return;
        }

        $existingChunkIds = $chapter->currentVersion->chunks()->pluck('id')->all();
        if (! empty($existingChunkIds)) {
            Chunk::deleteEmbeddingsForChunks($existingChunkIds);
        }

        $chunks = $chunking->chunkVersion($chapter->currentVersion, $chapter);

        $setting = AiSetting::activeProvider();
        if ($setting?->provider->supportsEmbeddings() && $chunks->isNotEmpty()) {
            $embedding->embedChunks($chunks, $this->book);
            $this->preparation->increment('embedded_chunks', $chunks->count());
        }

        $this->preparation->resetConsecutiveFailures();
        $this->preparation->increment('current_phase_progress');
        $this->preparation->increment('processed_chapters');
    }

    public function failed(Throwable $exception): void
    {
        $this->preparation->appendPhaseError('chunking', "Chapter #{$this->chapterId}", $exception->getMessage());
        $this->preparation->increment('current_phase_progress');

        $failures = $this->preparation->recordConsecutiveFailure();
        if ($failures >= AiPreparation::CIRCUIT_BREAKER_THRESHOLD) {
            $this->batch()?->cancel();
        }
    }
}
