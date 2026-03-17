<?php

use App\Enums\AiProvider;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Chunk;
use App\Models\Storyline;
use App\Services\EmbeddingService;
use Laravel\Ai\Embeddings;

test('embed chunks generates and stores embeddings', function () {
    Embeddings::fake();

    $book = Book::factory()->withAi(AiProvider::Openai)->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $version = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
    ]);

    $chunks = collect([
        Chunk::factory()->for($version)->create(['content' => 'First chunk of text.']),
        Chunk::factory()->for($version)->create(['content' => 'Second chunk of text.', 'position' => 1]),
    ]);

    $service = app(EmbeddingService::class);
    $service->embedChunks($chunks, $book);

    Embeddings::assertGenerated(fn ($prompt) => true);

    foreach ($chunks as $chunk) {
        expect($chunk->hasEmbedding())->toBeTrue();
    }
});

test('embed chunks handles empty collection', function () {
    Embeddings::fake();

    $book = Book::factory()->withAi(AiProvider::Openai)->create();

    $service = app(EmbeddingService::class);
    $service->embedChunks(collect(), $book);

    Embeddings::assertNothingGenerated();
});

test('embed query returns vector for text', function () {
    Embeddings::fake();

    $book = Book::factory()->withAi(AiProvider::Openai)->create();

    $service = app(EmbeddingService::class);
    $result = $service->embedQuery('test query', $book);

    expect($result)->toBeArray()->not->toBeEmpty();

    Embeddings::assertGenerated(fn ($prompt) => true);
});
