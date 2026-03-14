<?php

namespace App\Ai\Tools;

use App\Models\AppSetting;
use App\Models\Book;
use App\Models\Chunk;
use App\Services\EmbeddingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Reranking;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchSimilarChunks implements Tool
{
    public function description(): Stringable|string
    {
        return 'Searches for text chunks in the manuscript using semantic similarity, keyword matching, or hybrid (both). Useful for finding related passages, character names, themes, or specific references.';
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'book_id' => $schema->integer()->required(),
            'query' => $schema->string()->required(),
            'limit' => $schema->integer()->min(1)->max(20),
            'search_mode' => $schema->string()->enum(['semantic', 'keyword', 'hybrid']),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $bookId = $request['book_id'];
        $query = $request['query'];
        $limit = $request['limit'] ?? 5;
        $searchMode = $request['search_mode'] ?? 'hybrid';

        try {
            $chunks = $this->search($bookId, $query, $limit, $searchMode);
        } catch (\Throwable) {
            // Semantic/hybrid search may fail if sqlite-vec isn't loaded or embeddings don't exist.
            // Fall back to keyword search which uses FTS5 (built into SQLite).
            try {
                $chunks = Chunk::findByKeywordForBook($bookId, $query, $limit);
            } catch (\Throwable) {
                return 'No manuscript chunks available. The manuscript may need to be prepared first.';
            }
        }

        if ($chunks->isEmpty()) {
            return 'No similar chunks found.';
        }

        $chunks->load('scene');

        $results = [];
        foreach ($chunks as $chunk) {
            $sceneLabel = $chunk->scene ? " | scene: {$chunk->scene->title}" : '';
            $results[] = "--- Chunk (position: {$chunk->position}{$sceneLabel}) ---\n{$chunk->content}";
        }

        return implode("\n\n", $results);
    }

    /**
     * Execute the appropriate search strategy.
     *
     * @return Collection<int, Chunk>
     */
    private function search(int $bookId, string $query, int $limit, string $searchMode): Collection
    {
        if ($searchMode === 'keyword') {
            return Chunk::findByKeywordForBook($bookId, $query, $limit);
        }

        $book = Book::query()->findOrFail($bookId);
        $queryEmbedding = app(EmbeddingService::class)->embedQuery($query, $book);

        if ($searchMode === 'hybrid') {
            $chunks = Chunk::hybridSearchForBook($bookId, $queryEmbedding, $query, $limit);
        } else {
            // semantic mode
            $rerankingEnabled = $this->isRerankingEnabled();
            $fetchLimit = $rerankingEnabled ? $limit * 2 : $limit;
            $chunks = Chunk::findSimilarForBook($bookId, $queryEmbedding, $fetchLimit);

            if ($rerankingEnabled && $chunks->count() > 1) {
                $chunks = $this->rerank($chunks, $query, $limit);
            }
        }

        return $chunks;
    }

    /**
     * Check if reranking is enabled and configured.
     */
    private function isRerankingEnabled(): bool
    {
        return (bool) AppSetting::get('reranking_enabled', false)
            && (bool) AppSetting::get('cohere_api_key');
    }

    /**
     * Rerank chunks using Cohere, falling back to original order on failure.
     *
     * @param  Collection<int, Chunk>  $chunks
     * @return Collection<int, Chunk>
     */
    private function rerank(Collection $chunks, string $query, int $limit): Collection
    {
        try {
            $cohereKey = AppSetting::get('cohere_api_key');
            config()->set('ai.providers.cohere.key', $cohereKey);

            $documents = $chunks->pluck('content')->all();

            $response = Reranking::of($documents)->limit($limit)->rerank($query);

            $rerankedChunks = new Collection;
            foreach ($response->results as $result) {
                $rerankedChunks->push($chunks->values()->get($result->index));
            }

            return $rerankedChunks;
        } catch (\Throwable) {
            // Silently fall back to KNN order on reranking failure
            return $chunks->take($limit)->values();
        }
    }
}
