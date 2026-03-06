<?php

namespace App\Jobs;

use App\Models\Book;
use App\Models\ChapterVersion;
use App\Services\ChunkingService;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateEmbeddingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        private Book $book,
        private ChapterVersion $chapterVersion,
    ) {}

    public function handle(ChunkingService $chunking, EmbeddingService $embedding): void
    {
        $chunks = $chunking->chunkVersion($this->chapterVersion);

        if ($chunks->isNotEmpty()) {
            $embedding->embedChunks($chunks, $this->book);
        }
    }
}
