<?php

namespace App\Ai\Tools;

use App\Models\Book;
use App\Models\Chunk;
use App\Services\EmbeddingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchSimilarChunks implements Tool
{
    public function description(): Stringable|string
    {
        return 'Searches for similar text chunks in the manuscript using semantic similarity. Useful for finding related passages, themes, or repeated content.';
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
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $bookId = $request['book_id'];
        $query = $request['query'];
        $limit = $request['limit'] ?? 5;

        $book = Book::query()->findOrFail($bookId);
        $queryEmbedding = app(EmbeddingService::class)->embedQuery($query, $book);

        $chunks = Chunk::findSimilarForBook($bookId, $queryEmbedding, $limit);

        if ($chunks->isEmpty()) {
            return 'No similar chunks found.';
        }

        $results = [];
        foreach ($chunks as $chunk) {
            $results[] = "--- Chunk (position: {$chunk->position}) ---\n{$chunk->content}";
        }

        return implode("\n\n", $results);
    }
}
