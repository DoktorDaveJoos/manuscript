<?php

use App\Enums\AiProvider;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Chunk;
use App\Models\Storyline;
use App\Services\EmbeddingService;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Ai\Embeddings;

/**
 * Build a Laravel HTTP-client RequestException for the given status, mirroring
 * what `Laravel\Ai` re-throws when a provider returns a non-failoverable error.
 */
function embeddingRequestException(int $status, string $message): RequestException
{
    return new RequestException(new Response(new GuzzleResponse(
        $status,
        [],
        json_encode(['error' => ['message' => $message, 'type' => 'invalid_request_error']]),
    )));
}

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

test('embed chunks degrades gracefully when the provider rejects the request', function () {
    Exceptions::fake();

    // OpenAI/Cloudflare answers an oversized/header-rejected embeddings POST with
    // a 431 that Laravel\Ai surfaces as a raw RequestException (not failoverable).
    Embeddings::fake(function () {
        throw embeddingRequestException(431, 'Request headers are too large.');
    });

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

    // Background indexing is best-effort: it must never bubble the failure up to
    // crash the editorial review's queue worker.
    $service->embedChunks($chunks, $book);

    foreach ($chunks as $chunk) {
        expect($chunk->hasEmbedding())->toBeFalse();
    }

    // The failure is still surfaced — as a handled report, not an uncaught crash.
    Exceptions::assertReported(RequestException::class);
});

test('embed chunks preserves embeddings from batches that succeed before a failure', function () {
    Exceptions::fake();

    // First batch (20 chunks) succeeds, the second batch is rejected.
    $calls = 0;
    Embeddings::fake(function () use (&$calls) {
        if ($calls++ === 0) {
            return null; // null => the fake generates valid embeddings
        }

        throw embeddingRequestException(431, 'Request headers are too large.');
    });

    $book = Book::factory()->withAi(AiProvider::Openai)->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $version = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
    ]);

    $chunks = collect(range(0, 24))->map(
        fn (int $i) => Chunk::factory()->for($version)->create(['content' => "Chunk {$i}.", 'position' => $i]),
    );

    app(EmbeddingService::class)->embedChunks($chunks, $book);

    expect($chunks->take(20)->every(fn (Chunk $c) => $c->hasEmbedding()))->toBeTrue();
    expect($chunks->slice(20)->contains(fn (Chunk $c) => $c->hasEmbedding()))->toBeFalse();

    Exceptions::assertReported(RequestException::class);
});

test('embed query returns vector for text', function () {
    Embeddings::fake();

    $book = Book::factory()->withAi(AiProvider::Openai)->create();

    $service = app(EmbeddingService::class);
    $result = $service->embedQuery('test query', $book);

    expect($result)->toBeArray()->not->toBeEmpty();

    Embeddings::assertGenerated(fn ($prompt) => true);
});
