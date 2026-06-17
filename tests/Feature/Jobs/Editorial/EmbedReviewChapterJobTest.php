<?php

use App\Enums\AiProvider;
use App\Jobs\Editorial\EmbedReviewChapterJob;
use App\Models\AiSetting;
use App\Services\ChunkingService;
use App\Services\EmbeddingService;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Ai\Embeddings;

test('EmbedReviewChapterJob chunks the chapter current version into chunk records', function () {
    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $chapter = $chapters[0];

    (new EmbedReviewChapterJob($book, $chapter->id))
        ->handle(app(ChunkingService::class), app(EmbeddingService::class));

    expect($chapter->currentVersion()->first()->chunks()->count())->toBeGreaterThan(0);
});

test('EmbedReviewChapterJob replaces existing chunks instead of duplicating them', function () {
    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $chapter = $chapters[0];

    $job = new EmbedReviewChapterJob($book, $chapter->id);
    $job->handle(app(ChunkingService::class), app(EmbeddingService::class));
    $firstCount = $chapter->currentVersion()->first()->chunks()->count();

    $job->handle(app(ChunkingService::class), app(EmbeddingService::class));

    expect($chapter->currentVersion()->first()->chunks()->count())->toBe($firstCount);
});

test('EmbedReviewChapterJob completes when the embeddings provider rejects the request', function () {
    Exceptions::fake();

    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $chapter = $chapters[0];

    // The editorial helper defaults to Anthropic (no embeddings); switch the
    // active provider to one that supports embeddings so the job reaches the
    // embedding path.
    AiSetting::query()->first()->update(['provider' => AiProvider::Openai]);

    Embeddings::fake(function () {
        throw new RequestException(new Response(new GuzzleResponse(431, [], json_encode([
            'error' => ['message' => 'Request headers are too large.'],
        ]))));
    });

    // A failed semantic re-index degrades retrieval quality but must never fail
    // the editorial review's queue worker.
    (new EmbedReviewChapterJob($book, $chapter->id))
        ->handle(app(ChunkingService::class), app(EmbeddingService::class));

    // Chunks were still written; only the embeddings are missing.
    expect($chapter->currentVersion()->first()->chunks()->count())->toBeGreaterThan(0);
    Exceptions::assertReported(RequestException::class);
});

test('EmbedReviewChapterJob does nothing for a chapter without content', function () {
    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $chapter = $chapters[0];
    $chapter->currentVersion()->update(['content' => null]);

    (new EmbedReviewChapterJob($book, $chapter->id))
        ->handle(app(ChunkingService::class), app(EmbeddingService::class));

    expect($chapter->currentVersion()->first()->chunks()->count())->toBe(0);
});
