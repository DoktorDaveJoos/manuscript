<?php

use App\Ai\Agents\ChapterAnalyzer;
use App\Ai\Agents\EntityExtractor;
use App\Ai\Agents\StoryBibleBuilder;
use App\Jobs\PrepareBookForAi;
use App\Models\AiPreparation;
use App\Models\Chapter;
use App\Models\Scene;

function fakeAiAgents(): void
{
    ChapterAnalyzer::fake(fn () => [
        'summary' => 'Summary.', 'key_events' => [], 'characters_present' => [],
        'tension_score' => 5, 'hook_score' => 5, 'hook_type' => 'closed',
        'hook_reasoning' => 'OK.', 'plot_points' => [],
    ]);
    EntityExtractor::fake(fn () => ['characters' => [], 'entities' => []]);
    StoryBibleBuilder::fake(fn () => [
        'characters' => [], 'setting' => [], 'plot_outline' => [],
        'themes' => [], 'style_rules' => [], 'genre_rules' => [], 'timeline' => [],
    ]);
}

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

test('pipeline skips entirely when no dirty chapters', function () {
    fakeAiAgents();

    [$book, $chapters] = createBookWithChapters(2);

    // Mark all chapters as already prepared
    foreach ($chapters as $chapter) {
        $chapter->update(['prepared_content_hash' => $chapter->content_hash]);
    }

    $preparation = AiPreparation::create([
        'book_id' => $book->id,
        'status' => 'pending',
    ]);

    $job = new PrepareBookForAi($book, $preparation);
    $job->handle();

    $preparation->refresh();
    expect($preparation->status)->toBe('completed')
        ->and($preparation->completed_phases)->toContain('chunking')
        ->and($preparation->completed_phases)->toContain('health_analysis')
        ->and($preparation->batch_id)->toBeNull();
});

test('pipeline only processes dirty chapters', function () {
    fakeAiAgents();

    [$book, $chapters] = createBookWithChapters(3);

    // Mark first two as already prepared
    $chapters[0]->update(['prepared_content_hash' => $chapters[0]->content_hash]);
    $chapters[1]->update(['prepared_content_hash' => $chapters[1]->content_hash]);

    // Third chapter stays dirty (prepared_content_hash is null)
    $preparation = AiPreparation::create([
        'book_id' => $book->id,
        'status' => 'pending',
    ]);

    $job = new PrepareBookForAi($book, $preparation);
    $job->handle();

    $preparation->refresh();
    expect($preparation->status)->toBe('completed')
        ->and($preparation->total_chapters)->toBe(3);

    // Only the dirty chapter should have been analyzed
    $chapters[0]->refresh();
    $chapters[1]->refresh();
    $chapters[2]->refresh();

    expect($chapters[0]->summary)->toBeNull()
        ->and($chapters[1]->summary)->toBeNull()
        ->and($chapters[2]->summary)->toBe('Summary.');
});

test('completed preparation stamps prepared_content_hash and ai_prepared_at', function () {
    fakeAiAgents();

    [$book, $chapters] = createBookWithChapters(2);

    $preparation = AiPreparation::create([
        'book_id' => $book->id,
        'status' => 'pending',
    ]);

    $job = new PrepareBookForAi($book, $preparation);
    $job->handle();

    $preparation->refresh();
    expect($preparation->status)->toBe('completed');

    foreach ($chapters as $chapter) {
        $chapter->refresh();
        expect($chapter->prepared_content_hash)->toBe($chapter->content_hash)
            ->and($chapter->ai_prepared_at)->not->toBeNull();
    }
});
