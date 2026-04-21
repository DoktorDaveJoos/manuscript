<?php

use App\Models\Act;
use App\Models\Beat;
use App\Models\Book;
use App\Models\Character;
use App\Models\PlotCoachBatch;
use App\Models\PlotCoachSession;
use App\Models\PlotPoint;
use App\Models\Storyline;
use App\Models\WikiEntry;
use App\Services\PlotCoachBatchService;

test('it applies a character batch transactionally', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;
    $batch = $service->apply($session, [
        ['type' => 'character', 'data' => [
            'name' => 'Mara',
            'ai_description' => 'Resistance fighter with a fragile grip on her conscience.',
        ]],
    ], 'Add Mara');

    expect($batch)->toBeInstanceOf(PlotCoachBatch::class);
    expect($batch->session_id)->toBe($session->id);
    expect($batch->summary)->toBe('Add Mara');
    expect($batch->reverted_at)->toBeNull();
    expect($batch->payload['version'] ?? null)->toBe(1);
    expect($batch->payload['writes'] ?? [])->toHaveCount(1);

    $character = Character::query()->where('book_id', $book->id)->where('name', 'Mara')->first();
    expect($character)->not->toBeNull();
    expect($character->is_ai_extracted)->toBeTrue();
    expect($character->ai_description)->toContain('Resistance fighter');
});

test('it rolls back the entire batch on any failure', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;

    try {
        $service->apply($session, [
            ['type' => 'character', 'data' => ['name' => 'Kade']],
            // Missing act_id — must fail.
            ['type' => 'plot_point', 'data' => ['title' => 'Inciting event']],
        ], 'Bad batch');

        $this->fail('Expected batch to throw.');
    } catch (InvalidArgumentException $e) {
        // Expected.
    }

    expect(Character::query()->where('book_id', $book->id)->count())->toBe(0);
    expect(PlotPoint::query()->where('book_id', $book->id)->count())->toBe(0);
    expect(PlotCoachBatch::query()->where('session_id', $session->id)->count())->toBe(0);
});

test('it writes multiple types in a single batch', function () {
    $book = Book::factory()->create();
    $act = Act::factory()->for($book, 'book')->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;
    $batch = $service->apply($session, [
        ['type' => 'character', 'data' => ['name' => 'Tomas']],
        ['type' => 'storyline', 'data' => ['name' => 'Resistance arc', 'type' => 'main', 'color' => '#B87333']],
        ['type' => 'plot_point', 'data' => [
            'title' => 'First arrest',
            'type' => 'setup',
            'status' => 'planned',
            'act_id' => $act->id,
        ]],
    ], 'Seed structure');

    // Now add a beat linked to the plot_point we just created.
    $plotPointId = PlotPoint::query()->where('book_id', $book->id)->value('id');
    $batch2 = $service->apply($session, [
        ['type' => 'beat', 'data' => [
            'title' => 'Knock at dawn',
            'description' => 'They come before sunrise.',
            'plot_point_id' => $plotPointId,
        ]],
    ], 'Add beat');

    expect(Character::query()->where('book_id', $book->id)->count())->toBe(1);
    expect(Storyline::query()->where('book_id', $book->id)->count())->toBe(1);
    expect(PlotPoint::query()->where('book_id', $book->id)->count())->toBe(1);
    expect(Beat::query()->where('plot_point_id', $plotPointId)->count())->toBe(1);

    expect($batch->payload['writes'])->toHaveCount(3);
    expect($batch2->payload['writes'])->toHaveCount(1);
});

test('it undoes the most recent batch', function () {
    $book = Book::factory()->create();
    $act = Act::factory()->for($book, 'book')->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;
    $batch = $service->apply($session, [
        ['type' => 'character', 'data' => ['name' => 'Uri']],
        ['type' => 'plot_point', 'data' => [
            'title' => 'Escape',
            'type' => 'conflict',
            'act_id' => $act->id,
        ]],
    ], 'Uri + escape');

    expect(Character::query()->where('book_id', $book->id)->count())->toBe(1);
    expect(PlotPoint::query()->where('book_id', $book->id)->count())->toBe(1);

    $reverted = $service->undo($session);

    expect($reverted)->not->toBeNull();
    expect($reverted->id)->toBe($batch->id);
    expect($reverted->reverted_at)->not->toBeNull();
    expect(Character::query()->where('book_id', $book->id)->count())->toBe(0);
    expect(PlotPoint::query()->where('book_id', $book->id)->count())->toBe(0);
});

test('it undo is a no-op when no unreverted batch exists', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;

    expect($service->undo($session))->toBeNull();
});

test('it undo is idempotent — undoing twice does not re-delete or raise', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'character', 'data' => ['name' => 'Ivo']],
    ], 'Add Ivo');

    $first = $service->undo($session);
    expect($first)->not->toBeNull();

    $second = $service->undo($session);
    expect($second)->toBeNull();
});

test('it undo silently skips rows deleted by the user', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'character', 'data' => ['name' => 'Nils']],
    ], 'Add Nils');

    // User manually deletes the character outside the batch flow.
    Character::query()->where('book_id', $book->id)->delete();

    $reverted = $service->undo($session);

    expect($reverted)->not->toBeNull();
    expect($reverted->reverted_at)->not->toBeNull();
});

test('it never modifies existing entities with same name — duplicate rows allowed', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $existing = Character::factory()->for($book, 'book')->create([
        'name' => 'Mara',
        'description' => 'User-authored summary.',
    ]);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'character', 'data' => ['name' => 'Mara', 'ai_description' => 'AI-authored riff.']],
    ], 'Add Mara');

    // Original row untouched.
    $existing->refresh();
    expect($existing->description)->toBe('User-authored summary.');
    expect($existing->ai_description)->toBeNull();

    // Duplicate row created — name uniqueness is not schema-enforced.
    $maras = Character::query()->where('book_id', $book->id)->where('name', 'Mara')->get();
    expect($maras)->toHaveCount(2);
});

test('it persists a wiki entry with ai_description and ai-extracted flag', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'wiki_entry', 'data' => [
            'kind' => 'location',
            'name' => 'The Archive',
            'ai_description' => 'A warren of paper stacks beneath the city.',
        ]],
    ], 'Add location');

    $entry = WikiEntry::query()->where('book_id', $book->id)->first();
    expect($entry)->not->toBeNull();
    expect($entry->name)->toBe('The Archive');
    expect($entry->kind->value)->toBe('location');
    expect($entry->ai_description)->toContain('warren of paper');
    expect($entry->is_ai_extracted)->toBeTrue();
});

test('it throws on unknown write type', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply(
        $session,
        [['type' => 'banana', 'data' => ['name' => 'Mara']]],
        'Bad',
    ))->toThrow(InvalidArgumentException::class);

    expect(PlotCoachBatch::query()->count())->toBe(0);
});

test('it throws on invalid enum values', function () {
    $book = Book::factory()->create();
    $act = Act::factory()->for($book, 'book')->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply(
        $session,
        [['type' => 'plot_point', 'data' => [
            'title' => 'Bad',
            'type' => 'not-a-real-type',
            'act_id' => $act->id,
        ]]],
        'Bad',
    ))->toThrow(InvalidArgumentException::class);
});
