<?php

namespace App\Jobs\Editorial;

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

/**
 * Refreshes the semantic index for one stale chapter as part of an editorial
 * review run: re-chunks the current version and re-embeds the chunks when the
 * active provider supports embeddings. Failures are reported but never fail
 * the review — retrieval quality degrades while the review still completes.
 */
class EmbedReviewChapterJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [15];

    public int $timeout = 300;

    public function __construct(
        public Book $book,
        public int $chapterId,
    ) {}

    public function handle(ChunkingService $chunking, EmbeddingService $embedding): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $chapter = $this->book->chapters()
            ->with(['currentVersion', 'scenes'])
            ->find($this->chapterId);

        if (! $chapter || ! $chapter->currentVersion?->content) {
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
        }
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
