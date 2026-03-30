<?php

use App\Ai\Tools\SearchSimilarChunks;
use App\Models\AppSetting;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Chunk;
use App\Models\Scene;
use App\Services\EmbeddingService;
use App\Services\SqliteVec\SqliteVecService;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Reranking;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $service = app(SqliteVecService::class);

    if (! $service->isLoaded(DB::connection()->getPdo())) {
        $this->markTestSkipped('sqlite-vec extension is not available in this environment.');
    }

    $this->book = Book::factory()->create();
    $this->chapter = Chapter::factory()->for($this->book)->create();
    $this->version = ChapterVersion::factory()->for($this->chapter)->create();

    // Mock the embedding service to avoid real API calls
    $this->embeddingService = Mockery::mock(EmbeddingService::class);
    $this->embeddingService->shouldReceive('embedQuery')
        ->andReturn(array_fill(0, 1536, 0.5));
    app()->instance(EmbeddingService::class, $this->embeddingService);
});

it('returns no results message when no chunks exist', function () {
    $tool = new SearchSimilarChunks;
    $request = new Request([
        'book_id' => $this->book->id,
        'query' => 'test query',
        'search_mode' => 'semantic',
    ]);

    $result = $tool->handle($request);

    expect($result)->toBe('No similar chunks found.');
});

it('does not rerank when reranking is disabled', function () {
    Reranking::fake();

    $chunk = Chunk::factory()->create([
        'chapter_version_id' => $this->version->id,
        'content' => 'Test chunk content',
    ]);
    $chunk->storeEmbedding(array_fill(0, 1536, 0.5), $this->book->id);

    $tool = new SearchSimilarChunks;
    $request = new Request([
        'book_id' => $this->book->id,
        'query' => 'test',
        'search_mode' => 'semantic',
    ]);

    $result = $tool->handle($request);

    expect($result)->toContain('Test chunk content');
    Reranking::assertNothingReranked();
});

it('falls back to KNN order when reranking fails', function () {
    Reranking::fake(fn () => throw new Exception('Cohere API error'));

    AppSetting::set('reranking_enabled', true);
    AppSetting::set('cohere_api_key', 'test-key');

    $chunkA = Chunk::factory()->create([
        'chapter_version_id' => $this->version->id,
        'content' => 'Chunk A content',
        'position' => 0,
    ]);
    $chunkB = Chunk::factory()->create([
        'chapter_version_id' => $this->version->id,
        'content' => 'Chunk B content',
        'position' => 1,
    ]);
    $chunkA->storeEmbedding(array_fill(0, 1536, 0.5), $this->book->id);
    $chunkB->storeEmbedding(array_fill(0, 1536, 0.4), $this->book->id);

    $tool = new SearchSimilarChunks;
    $request = new Request([
        'book_id' => $this->book->id,
        'query' => 'test',
        'limit' => 2,
        'search_mode' => 'semantic',
    ]);

    $result = $tool->handle($request);

    // Should still return results despite reranking failure
    expect($result)
        ->toContain('Chunk A content')
        ->toContain('Chunk B content');
});

it('includes scene title when chunk has a scene', function () {
    $scene = Scene::factory()->for($this->chapter)->create(['title' => 'The Revelation']);
    $chunk = Chunk::factory()->create([
        'chapter_version_id' => $this->version->id,
        'scene_id' => $scene->id,
        'content' => 'Scene chunk content',
        'position' => 0,
    ]);
    $chunk->storeEmbedding(array_fill(0, 1536, 0.5), $this->book->id);

    $tool = new SearchSimilarChunks;
    $request = new Request([
        'book_id' => $this->book->id,
        'query' => 'test',
        'search_mode' => 'semantic',
    ]);

    $result = $tool->handle($request);

    expect($result)->toContain('scene: The Revelation');
});

it('uses keyword search mode when specified', function () {
    $chunk = Chunk::factory()->create([
        'chapter_version_id' => $this->version->id,
        'content' => 'Commander Varys ordered the cavalry to charge.',
        'position' => 0,
    ]);

    $tool = new SearchSimilarChunks;
    $request = new Request([
        'book_id' => $this->book->id,
        'query' => 'Varys cavalry',
        'search_mode' => 'keyword',
    ]);

    $result = $tool->handle($request);

    expect($result)->toContain('Commander Varys');
});

it('defaults to hybrid search mode', function () {
    $chunk = Chunk::factory()->create([
        'chapter_version_id' => $this->version->id,
        'content' => 'A passage about ancient swords.',
        'position' => 0,
    ]);
    $chunk->storeEmbedding(array_fill(0, 1536, 0.5), $this->book->id);

    $tool = new SearchSimilarChunks;
    $request = new Request([
        'book_id' => $this->book->id,
        'query' => 'ancient swords',
    ]);

    $result = $tool->handle($request);

    expect($result)->toContain('ancient swords');
});
