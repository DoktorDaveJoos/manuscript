<?php

use App\Models\Chapter;
use App\Models\Scene;

test('refreshContentHash computes correct xxh128 from scenes', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];

    $expected = hash('xxh128', $chapter->getFullContent());

    expect($chapter->content_hash)->toBe($expected);
});

test('empty chapter produces null hash', function () {
    $chapter = Chapter::factory()->create();
    Scene::factory()->for($chapter)->create([
        'content' => '',
        'sort_order' => 0,
        'word_count' => 0,
    ]);

    $chapter->load('scenes');
    $chapter->refreshContentHash();

    expect($chapter->content_hash)->toBeNull();
});

test('needsAiPreparation returns false when both hashes are null', function () {
    $chapter = Chapter::factory()->create([
        'content_hash' => null,
        'prepared_content_hash' => null,
    ]);

    expect($chapter->needsAiPreparation())->toBeFalse();
});

test('needsAiPreparation returns true when hash set but prepared is null', function () {
    $chapter = Chapter::factory()->create([
        'content_hash' => 'abc123',
        'prepared_content_hash' => null,
    ]);

    expect($chapter->needsAiPreparation())->toBeTrue();
});

test('needsAiPreparation returns false when hashes are equal', function () {
    $chapter = Chapter::factory()->create([
        'content_hash' => 'abc123',
        'prepared_content_hash' => 'abc123',
    ]);

    expect($chapter->needsAiPreparation())->toBeFalse();
});

test('needsAiPreparation returns true when hashes differ', function () {
    $chapter = Chapter::factory()->create([
        'content_hash' => 'abc123',
        'prepared_content_hash' => 'def456',
    ]);

    expect($chapter->needsAiPreparation())->toBeTrue();
});

test('scene content update triggers hash refresh', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];
    $originalHash = $chapter->content_hash;

    $scene = $chapter->scenes()->first();
    $scene->update([
        'content' => '<p>Completely new content here.</p>',
        'word_count' => 4,
    ]);
    $chapter->recalculateWordCount();

    $chapter->refresh();
    expect($chapter->content_hash)->not->toBe($originalHash)
        ->and($chapter->content_hash)->toBe(hash('xxh128', $chapter->getFullContent()));
});

test('replaceScenesWithContent triggers hash refresh', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];
    $originalHash = $chapter->content_hash;

    $chapter->replaceScenesWithContent('<p>Brand new scene content.</p>');

    $chapter->refresh();
    expect($chapter->content_hash)->not->toBe($originalHash)
        ->and($chapter->content_hash)->not->toBeNull();
});

test('scene reorder triggers hash refresh via controller', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];

    // Add a second scene
    $scene2 = Scene::factory()->for($chapter)->create([
        'content' => '<p>Second scene.</p>',
        'sort_order' => 1,
        'word_count' => 2,
    ]);
    $chapter->load('scenes');
    $chapter->refreshContentHash();
    $originalHash = $chapter->fresh()->content_hash;

    $scene1 = $chapter->scenes()->where('sort_order', 0)->first();

    // Reorder via controller
    $this->postJson(
        route('scenes.reorder', [$book, $chapter]),
        ['order' => [$scene2->id, $scene1->id]]
    )->assertSuccessful();

    $chapter->refresh();
    expect($chapter->content_hash)->not->toBe($originalHash);
});
