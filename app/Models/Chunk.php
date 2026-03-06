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
     * Store an embedding vector in the chunk_embeddings virtual table.
     *
     * @param  array<int, float>  $embedding
     */
    public function storeEmbedding(array $embedding): void
    {
        DB::statement(
            'INSERT OR REPLACE INTO chunk_embeddings (chunk_id, embedding) VALUES (?, ?)',
            [$this->id, self::serializeEmbedding($embedding)],
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
     * Find similar chunks scoped to a specific book.
     *
     * @param  array<int, float>  $embedding
     * @return Collection<int, static>
     */
    public static function findSimilarForBook(int $bookId, array $embedding, int $limit = 10): Collection
    {
        // vec0 KNN queries don't support JOINs, so we fetch a broader set
        // of candidates then filter by book. We request more results to
        // ensure we have enough after filtering.
        $candidateLimit = $limit * 5;

        $results = DB::select(<<<'SQL'
            SELECT ce.chunk_id, ce.distance
            FROM chunk_embeddings ce
            WHERE ce.embedding MATCH ?
            AND ce.chunk_id IN (
                SELECT c.id FROM chunks c
                JOIN chapter_versions cv ON cv.id = c.chapter_version_id
                JOIN chapters ch ON ch.id = cv.chapter_id
                WHERE ch.book_id = ?
            )
            ORDER BY ce.distance
            LIMIT ?
        SQL, [self::serializeEmbedding($embedding), $bookId, $candidateLimit]);

        return self::hydrateByDistance($results, $limit);
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
}
