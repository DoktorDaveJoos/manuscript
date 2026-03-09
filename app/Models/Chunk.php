<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Chunk extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ChapterVersion, $this>
     */
    public function chapterVersion(): BelongsTo
    {
        return $this->belongsTo(ChapterVersion::class);
    }

    /**
     * @return BelongsTo<Scene, $this>
     */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    /**
     * Store an embedding vector in the chunk_embeddings virtual table.
     *
     * @param  array<int, float>  $embedding
     */
    public function storeEmbedding(array $embedding, int $bookId): void
    {
        DB::statement(
            'INSERT OR REPLACE INTO chunk_embeddings (book_id, chunk_id, embedding) VALUES (?, ?, ?)',
            [$bookId, $this->id, self::serializeEmbedding($embedding)],
        );
    }

    /**
     * Delete this chunk's embedding from the virtual table.
     */
    public function deleteEmbedding(): void
    {
        DB::delete('DELETE FROM chunk_embeddings WHERE chunk_id = ?', [$this->id]);
    }

    /**
     * Check if this chunk has an embedding stored.
     */
    public function hasEmbedding(): bool
    {
        return (bool) DB::selectOne(
            'SELECT 1 FROM chunk_embeddings WHERE chunk_id = ?',
            [$this->id],
        );
    }

    /**
     * Find chunks with the most similar embeddings using KNN search.
     *
     * @param  array<int, float>  $queryEmbedding
     * @return Collection<int, static>
     */
    public static function findSimilar(array $queryEmbedding, int $limit = 10): Collection
    {
        $results = DB::select(
            'SELECT chunk_id, distance FROM chunk_embeddings WHERE embedding MATCH ? ORDER BY distance LIMIT ?',
            [self::serializeEmbedding($queryEmbedding), $limit],
        );

        return self::hydrateByDistance($results, $limit);
    }

    /**
     * Find similar chunks scoped to a specific book using partition key.
     *
     * @param  array<int, float>  $embedding
     * @return Collection<int, static>
     */
    public static function findSimilarForBook(int $bookId, array $embedding, int $limit = 10): Collection
    {
        $results = DB::select(
            'SELECT chunk_id, distance FROM chunk_embeddings WHERE book_id = ? AND embedding MATCH ? ORDER BY distance LIMIT ?',
            [$bookId, self::serializeEmbedding($embedding), $limit],
        );

        return self::hydrateByDistance($results, $limit);
    }

    /**
     * Find chunks matching a keyword query using FTS5, scoped to a book.
     *
     * @return Collection<int, static>
     */
    public static function findByKeywordForBook(int $bookId, string $query, int $limit = 10): Collection
    {
        $ids = self::findKeywordIdsForBook($bookId, $query, $limit);

        return self::hydrateInOrder($ids);
    }

    /**
     * Hybrid search combining vector KNN and FTS5 keyword results using Reciprocal Rank Fusion.
     *
     * @param  array<int, float>  $embedding
     * @return Collection<int, static>
     */
    public static function hybridSearchForBook(int $bookId, array $embedding, string $query, int $limit = 10): Collection
    {
        $vectorIds = self::findSimilarIdsForBook($bookId, $embedding, $limit * 2);
        $keywordIds = self::findKeywordIdsForBook($bookId, $query, $limit * 2);

        // Reciprocal Rank Fusion: score = Σ 1/(k + rank) where k=60
        $k = 60;
        $scores = [];

        foreach ($vectorIds as $rank => $id) {
            $scores[$id] = ($scores[$id] ?? 0) + (1 / ($k + $rank));
        }

        foreach ($keywordIds as $rank => $id) {
            $scores[$id] = ($scores[$id] ?? 0) + (1 / ($k + $rank));
        }

        arsort($scores);
        $topIds = array_slice(array_keys($scores), 0, $limit);

        return self::hydrateInOrder($topIds);
    }

    /**
     * Return ordered chunk IDs from a vector KNN search (no model hydration).
     *
     * @param  array<int, float>  $embedding
     * @return array<int, int>
     */
    private static function findSimilarIdsForBook(int $bookId, array $embedding, int $limit): array
    {
        $results = DB::select(
            'SELECT chunk_id FROM chunk_embeddings WHERE book_id = ? AND embedding MATCH ? ORDER BY distance LIMIT ?',
            [$bookId, self::serializeEmbedding($embedding), $limit],
        );

        return array_map(fn ($row) => $row->chunk_id, $results);
    }

    /**
     * Return ordered chunk IDs from an FTS5 keyword search (no model hydration).
     *
     * @return array<int, int>
     */
    private static function findKeywordIdsForBook(int $bookId, string $query, int $limit): array
    {
        $sanitizedQuery = self::sanitizeFtsQuery($query);

        if ($sanitizedQuery === '') {
            return [];
        }

        $results = DB::select(<<<'SQL'
            SELECT c.id
            FROM chunks_fts fts
            JOIN chunks c ON c.id = fts.rowid
            JOIN chapter_versions cv ON cv.id = c.chapter_version_id
            JOIN chapters ch ON ch.id = cv.chapter_id
            WHERE chunks_fts MATCH ?
            AND ch.book_id = ?
            ORDER BY rank
            LIMIT ?
        SQL, [$sanitizedQuery, $bookId, $limit]);

        return array_map(fn ($row) => $row->id, $results);
    }

    /**
     * Sanitize a query string for FTS5 MATCH.
     */
    private static function sanitizeFtsQuery(string $query): string
    {
        // Remove FTS5 special characters, keep alphanumeric and spaces
        $sanitized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $query);
        $sanitized = trim(preg_replace('/\s+/', ' ', $sanitized));

        // Remove FTS5 operator keywords that could cause syntax errors
        $words = preg_split('/\s+/', $sanitized);
        $words = array_filter($words, fn ($w) => ! in_array(strtoupper($w), ['AND', 'OR', 'NOT', 'NEAR'], true));

        return trim(implode(' ', $words));
    }

    /**
     * Serialize an embedding array to JSON for the vec0 virtual table.
     *
     * @param  array<int, float>  $embedding
     */
    private static function serializeEmbedding(array $embedding): string
    {
        return json_encode(array_values($embedding));
    }

    /**
     * Hydrate Chunk models from embedding query results, sorted by distance.
     *
     * @param  array<int, object{chunk_id: int, distance: float}>  $results
     * @return Collection<int, static>
     */
    private static function hydrateByDistance(array $results, int $limit): Collection
    {
        if (empty($results)) {
            return new Collection;
        }

        $ids = array_map(fn ($row) => $row->chunk_id, $results);
        $distances = collect($results)->keyBy('chunk_id');

        return static::query()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (self $chunk) => $distances[$chunk->id]->distance)
            ->take($limit)
            ->values();
    }

    /**
     * Hydrate Chunk models preserving the given ID order.
     *
     * @param  array<int, int>  $orderedIds
     * @return Collection<int, static>
     */
    private static function hydrateInOrder(array $orderedIds): Collection
    {
        if (empty($orderedIds)) {
            return new Collection;
        }

        $chunks = static::query()->whereIn('id', $orderedIds)->get()->keyBy('id');

        $sorted = new Collection;
        foreach ($orderedIds as $id) {
            if ($chunks->has($id)) {
                $sorted->push($chunks->get($id));
            }
        }

        return $sorted;
    }
}
