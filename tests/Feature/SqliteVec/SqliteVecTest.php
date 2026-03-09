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

it('loads the sqlite-vec extension successfully', function () {
    $service = app(SqliteVecService::class);
    $pdo = DB::connection()->getPdo();

    expect($service->isLoaded($pdo))->toBeTrue();
});

it('returns a version string from vec_version()', function () {
    $version = DB::scalar('SELECT vec_version()');

    expect($version)
        ->toBeString()
        ->toStartWith('v');
});

it('can store and verify an embedding exists', function () {
    $book = Book::factory()->create();
    $chunk = Chunk::factory()->create();
    $embedding = array_fill(0, 1536, 0.1);

    $chunk->storeEmbedding($embedding, $book->id);

    expect($chunk->hasEmbedding())->toBeTrue();
});

it('can delete an embedding', function () {
    $book = Book::factory()->create();
    $chunk = Chunk::factory()->create();
    $chunk->storeEmbedding(array_fill(0, 1536, 0.1), $book->id);

    $chunk->deleteEmbedding();

    expect($chunk->hasEmbedding())->toBeFalse();
});

it('returns correct KNN ordering by distance', function () {
    $book = Book::factory()->create();
    $chapterVersion = ChapterVersion::factory()->create();

    $chunkA = Chunk::factory()->create(['chapter_version_id' => $chapterVersion->id]);
    $chunkB = Chunk::factory()->create(['chapter_version_id' => $chapterVersion->id]);
    $chunkC = Chunk::factory()->create(['chapter_version_id' => $chapterVersion->id]);

    // Embedding A: all 1.0
    $chunkA->storeEmbedding(array_fill(0, 1536, 1.0), $book->id);

    // Embedding B: all 0.5
    $chunkB->storeEmbedding(array_fill(0, 1536, 0.5), $book->id);

    // Embedding C: all 0.0
    $chunkC->storeEmbedding(array_fill(0, 1536, 0.0), $book->id);

    // Query with all 1.0 — A should be closest, then B, then C
    $query = array_fill(0, 1536, 1.0);
    $results = Chunk::findSimilar($query, 3);

    expect($results)->toHaveCount(3);
    expect($results->first()->id)->toBe($chunkA->id);
    expect($results->last()->id)->toBe($chunkC->id);
});

it('scopes similarity search to a specific book via partition key', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();

    $chapterA = Chapter::factory()->create(['book_id' => $bookA->id]);
    $chapterB = Chapter::factory()->create(['book_id' => $bookB->id]);

    $versionA = ChapterVersion::factory()->create(['chapter_id' => $chapterA->id]);
    $versionB = ChapterVersion::factory()->create(['chapter_id' => $chapterB->id]);

    $chunkA = Chunk::factory()->create(['chapter_version_id' => $versionA->id]);
    $chunkB = Chunk::factory()->create(['chapter_version_id' => $versionB->id]);

    $embedding = array_fill(0, 1536, 0.5);
    $chunkA->storeEmbedding($embedding, $bookA->id);
    $chunkB->storeEmbedding($embedding, $bookB->id);

    $results = Chunk::findSimilarForBook($bookA->id, $embedding, 10);

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($chunkA->id);
});
