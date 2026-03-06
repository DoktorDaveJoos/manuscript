<?php

use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Storyline;
use App\Services\ChunkingService;

test('chunks short text into single chunk', function () {
    $service = new ChunkingService;
    $result = $service->chunk('This is a short text.');

    expect($result)->toHaveCount(1)
        ->and($result[0]['content'])->toBe('This is a short text.')
        ->and($result[0]['position'])->toBe(0);
});

test('chunks empty text into empty array', function () {
    $service = new ChunkingService;
    $result = $service->chunk('');

    expect($result)->toBeEmpty();
});

test('chunks html content by stripping tags', function () {
    $service = new ChunkingService;
    $result = $service->chunk('<p>Hello <strong>world</strong></p>');

    expect($result)->toHaveCount(1)
        ->and($result[0]['content'])->toBe('Hello world');
});

test('chunks long text into multiple overlapping chunks', function () {
    $service = new ChunkingService;
    $words = array_fill(0, 1200, 'word');
    $text = implode(' ', $words);

    $result = $service->chunk($text);

    expect($result)->toHaveCount(3);
    expect($result[0]['position'])->toBe(0);
    expect($result[1]['position'])->toBe(1);
    expect($result[2]['position'])->toBe(2);
});

test('chunk version creates chunk models and deletes existing', function () {
    $book = \App\Models\Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $version = ChapterVersion::factory()->for($chapter)->create([
        'content' => 'Some test content for chunking.',
        'is_current' => true,
    ]);

    $service = new ChunkingService;

    // First chunking
    $chunks = $service->chunkVersion($version);
    expect($chunks)->toHaveCount(1);

    // Second chunking replaces existing
    $chunks2 = $service->chunkVersion($version);
    expect($chunks2)->toHaveCount(1);
    expect($version->chunks()->count())->toBe(1);
});

test('chunk version handles null content', function () {
    $book = \App\Models\Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $version = ChapterVersion::factory()->for($chapter)->create([
        'content' => null,
        'is_current' => true,
    ]);

    $service = new ChunkingService;
    $chunks = $service->chunkVersion($version);

    expect($chunks)->toBeEmpty();
});
