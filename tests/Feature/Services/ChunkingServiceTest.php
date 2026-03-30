<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Scene;
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
    $book = Book::factory()->create();
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
    $book = Book::factory()->create();
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

test('chunkByScenes creates one chunk per small scene', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create([
        'title' => 'Scene A',
        'content' => '<p>Short scene content here.</p>',
        'sort_order' => 0,
    ]);
    Scene::factory()->for($chapter)->create([
        'title' => 'Scene B',
        'content' => '<p>Another short scene.</p>',
        'sort_order' => 1,
    ]);

    $service = new ChunkingService;
    $result = $service->chunkByScenes($chapter);

    expect($result)->toHaveCount(2)
        ->and($result[0]['scene_id'])->toBe($chapter->scenes()->orderBy('sort_order')->first()->id)
        ->and($result[0]['position'])->toBe(0)
        ->and($result[1]['position'])->toBe(1);
});

test('chunkByScenes splits large scenes on paragraph boundaries', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    // Create a scene with ~1000 words across two paragraphs
    $paragraph1 = implode(' ', array_fill(0, 500, 'alpha'));
    $paragraph2 = implode(' ', array_fill(0, 500, 'beta'));
    Scene::factory()->for($chapter)->create([
        'title' => 'Long Scene',
        'content' => "<p>{$paragraph1}</p><p>{$paragraph2}</p>",
        'sort_order' => 0,
    ]);

    $service = new ChunkingService;
    $result = $service->chunkByScenes($chapter);

    expect($result)->toHaveCount(2)
        ->and($result[0]['content'])->toContain('alpha')
        ->and($result[1]['content'])->toContain('beta');
});

test('chunkByScenes skips empty scenes', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create([
        'content' => '',
        'sort_order' => 0,
    ]);
    Scene::factory()->for($chapter)->create([
        'content' => '<p>Has content.</p>',
        'sort_order' => 1,
    ]);

    $service = new ChunkingService;
    $result = $service->chunkByScenes($chapter);

    expect($result)->toHaveCount(1);
});

test('chunkByScenes returns empty when chapter has no scenes', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $service = new ChunkingService;
    $result = $service->chunkByScenes($chapter);

    expect($result)->toBeEmpty();
});

test('chunkVersion uses scene-based chunking when chapter is provided', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $version = ChapterVersion::factory()->for($chapter)->create([
        'content' => 'Version content that should not be used.',
        'is_current' => true,
    ]);
    Scene::factory()->for($chapter)->create([
        'content' => '<p>Scene-based content.</p>',
        'sort_order' => 0,
    ]);

    $service = new ChunkingService;
    $chunks = $service->chunkVersion($version, $chapter);

    expect($chunks)->toHaveCount(1)
        ->and($chunks->first()->content)->toContain('Scene-based content')
        ->and($chunks->first()->scene_id)->not->toBeNull();
});

test('chunkVersion falls back to word-based when chapter has no scenes', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $version = ChapterVersion::factory()->for($chapter)->create([
        'content' => 'Fallback content here.',
        'is_current' => true,
    ]);

    $service = new ChunkingService;
    $chunks = $service->chunkVersion($version, $chapter);

    expect($chunks)->toHaveCount(1)
        ->and($chunks->first()->content)->toContain('Fallback content here')
        ->and($chunks->first()->scene_id)->toBeNull();
});
