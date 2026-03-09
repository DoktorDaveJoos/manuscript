<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Chunk;
use App\Services\SqliteVec\SqliteVecService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $service = app(SqliteVecService::class);

    if (! $service->isLoaded(DB::connection()->getPdo())) {
        $this->markTestSkipped('sqlite-vec extension is not available in this environment.');
    }
});

it('FTS5 keyword search finds matching chunks scoped to book', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    $chapterA = Chapter::factory()->for($bookA)->create();
    $chapterB = Chapter::factory()->for($bookB)->create();
    $versionA = ChapterVersion::factory()->for($chapterA)->create();
    $versionB = ChapterVersion::factory()->for($chapterB)->create();

    // Create chunks — FTS5 triggers will auto-sync
    $chunkA = Chunk::factory()->create([
        'chapter_version_id' => $versionA->id,
        'content' => 'The dragon Vermithor roared across the valley.',
    ]);
    $chunkB = Chunk::factory()->create([
        'chapter_version_id' => $versionB->id,
        'content' => 'The dragon Syrax slept in the dragonpit.',
    ]);

    $results = Chunk::findByKeywordForBook($bookA->id, 'dragon', 10);

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($chunkA->id);
});

it('FTS5 returns empty for non-matching queries', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();
    $version = ChapterVersion::factory()->for($chapter)->create();

    Chunk::factory()->create([
        'chapter_version_id' => $version->id,
        'content' => 'The castle walls stood tall.',
    ]);

    $results = Chunk::findByKeywordForBook($book->id, 'xyznonexistent', 10);

    expect($results)->toBeEmpty();
});

it('FTS5 handles special characters in queries', function () {
    $book = Book::factory()->create();

    $results = Chunk::findByKeywordForBook($book->id, 'test AND OR NOT "quoted"', 10);

    expect($results)->toBeEmpty(); // Should not throw
});

it('hybrid search combines vector and keyword results via RRF', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();
    $version = ChapterVersion::factory()->for($chapter)->create();

    $chunkKeyword = Chunk::factory()->create([
        'chapter_version_id' => $version->id,
        'content' => 'Commander Varys ordered the cavalry to charge at dawn.',
    ]);
    $chunkVector = Chunk::factory()->create([
        'chapter_version_id' => $version->id,
        'content' => 'The morning attack was swift and decisive.',
    ]);

    // Store embeddings with different distances from query
    $chunkKeyword->storeEmbedding(array_fill(0, 1536, 0.3), $book->id);
    $chunkVector->storeEmbedding(array_fill(0, 1536, 0.9), $book->id);

    // Query embedding close to chunkVector
    $queryEmbedding = array_fill(0, 1536, 0.9);

    $results = Chunk::hybridSearchForBook($book->id, $queryEmbedding, 'Varys cavalry', 10);

    // Both should appear — keyword finds chunkKeyword, vector finds chunkVector
    expect($results)->toHaveCount(2);
    $ids = $results->pluck('id')->all();
    expect($ids)->toContain($chunkKeyword->id)
        ->toContain($chunkVector->id);
});

it('storeEmbedding with partition key and findSimilarForBook works correctly', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();
    $version = ChapterVersion::factory()->for($chapter)->create();

    $chunk = Chunk::factory()->create(['chapter_version_id' => $version->id]);
    $chunk->storeEmbedding(array_fill(0, 1536, 0.5), $book->id);

    $results = Chunk::findSimilarForBook($book->id, array_fill(0, 1536, 0.5), 5);

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($chunk->id);
});

it('partition key scopes results to correct book', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    $chapterA = Chapter::factory()->for($bookA)->create();
    $chapterB = Chapter::factory()->for($bookB)->create();
    $versionA = ChapterVersion::factory()->for($chapterA)->create();
    $versionB = ChapterVersion::factory()->for($chapterB)->create();

    $chunkA = Chunk::factory()->create(['chapter_version_id' => $versionA->id]);
    $chunkB = Chunk::factory()->create(['chapter_version_id' => $versionB->id]);

    $embedding = array_fill(0, 1536, 0.5);
    $chunkA->storeEmbedding($embedding, $bookA->id);
    $chunkB->storeEmbedding($embedding, $bookB->id);

    $results = Chunk::findSimilarForBook($bookA->id, $embedding, 10);

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($chunkA->id);
});
