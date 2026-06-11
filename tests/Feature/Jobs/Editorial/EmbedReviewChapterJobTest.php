<?php

use App\Jobs\Editorial\EmbedReviewChapterJob;
use App\Services\ChunkingService;
use App\Services\EmbeddingService;

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

test('EmbedReviewChapterJob does nothing for a chapter without content', function () {
    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $chapter = $chapters[0];
    $chapter->currentVersion()->update(['content' => null]);

    (new EmbedReviewChapterJob($book, $chapter->id))
        ->handle(app(ChunkingService::class), app(EmbeddingService::class));

    expect($chapter->currentVersion()->first()->chunks()->count())->toBe(0);
});
