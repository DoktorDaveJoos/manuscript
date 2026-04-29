<?php

use App\Enums\PlotCoachStage;
use App\Models\Act;
use App\Models\Beat;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\PlotCoachBatch;
use App\Models\PlotCoachSession;
use App\Models\PlotPoint;
use App\Models\Storyline;
use App\Models\WikiEntry;
use App\Services\PlotCoachBatchService;
use InvalidArgumentException;

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

test('it updates an existing wiki entry when id is set instead of creating a duplicate', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $entry = WikiEntry::query()->create([
        'book_id' => $book->id,
        'kind' => 'location',
        'name' => 'Jakutsk',
        'ai_description' => 'Siberian city.',
        'is_ai_extracted' => true,
    ]);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'wiki_entry', 'data' => [
            'id' => $entry->id,
            'ai_description' => 'Siberian city. Maja is pulled here in Act II.',
        ]],
    ], 'Refine Jakutsk');

    $entry->refresh();

    expect(WikiEntry::query()->where('book_id', $book->id)->count())->toBe(1);
    expect($entry->ai_description)->toBe('Siberian city. Maja is pulled here in Act II.');
});

test('undo restores the previous values of an updated wiki entry', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $entry = WikiEntry::query()->create([
        'book_id' => $book->id,
        'kind' => 'location',
        'name' => 'Jakutsk',
        'ai_description' => 'Siberian city.',
        'is_ai_extracted' => true,
    ]);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'wiki_entry', 'data' => [
            'id' => $entry->id,
            'name' => 'Jakutsk (revised)',
            'ai_description' => 'A bolder rewrite.',
        ]],
    ], 'Refine Jakutsk');

    $service->undo($session);

    $entry->refresh();

    expect($entry->name)->toBe('Jakutsk');
    expect($entry->ai_description)->toBe('Siberian city.');
    expect(WikiEntry::query()->where('book_id', $book->id)->count())->toBe(1);
});

test('it updates an existing character when id is set', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $character = Character::factory()->for($book)->create([
        'name' => 'Maja',
        'ai_description' => 'Original sketch.',
    ]);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'character', 'data' => [
            'id' => $character->id,
            'ai_description' => 'Research chemist. Wants control. Wound: she let a colleague take the blame.',
        ]],
    ], 'Refine Maja');

    $character->refresh();

    expect(Character::query()->where('book_id', $book->id)->count())->toBe(1);
    expect($character->ai_description)->toContain('Wound');
});

test('it rejects updates that reference an entity from another book', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();

    $session = PlotCoachSession::factory()->for($bookA, 'book')->create();

    $entry = WikiEntry::query()->create([
        'book_id' => $bookB->id,
        'kind' => 'location',
        'name' => 'Other-book city',
        'is_ai_extracted' => true,
    ]);

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply($session, [
        ['type' => 'wiki_entry', 'data' => ['id' => $entry->id, 'ai_description' => 'nope']],
    ], 'Cross-book attempt'))->toThrow(InvalidArgumentException::class);
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

test('it creates a chapter stub with empty content by default', function () {
    $book = Book::factory()->create(['plot_coach_seed_stub_with_intent' => false]);
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'chapter', 'data' => [
            'title' => 'Chapter One',
            'storyline_id' => $storyline->id,
        ]],
    ], 'Add Ch. 1');

    $chapter = Chapter::query()->where('book_id', $book->id)->first();
    expect($chapter)->not->toBeNull();
    expect($chapter->title)->toBe('Chapter One');
    expect($chapter->storyline_id)->toBe($storyline->id);
    expect($chapter->word_count)->toBe(0);
    expect($chapter->currentVersion()->first()->content)->toBe('');
});

test('it reuses an existing chapter with matching storyline + title (idempotent)', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $existing = Chapter::factory()
        ->for($book, 'book')
        ->for($storyline, 'storyline')
        ->create(['title' => 'The Arrest', 'word_count' => 42]);

    $service = new PlotCoachBatchService;
    $batch = $service->apply($session, [
        ['type' => 'chapter', 'data' => [
            'title' => 'The Arrest',
            'storyline_id' => $storyline->id,
        ]],
    ], 'Re-propose Ch.');

    $chapters = Chapter::query()->where('book_id', $book->id)->get();
    expect($chapters)->toHaveCount(1);
    expect($chapters->first()->id)->toBe($existing->id);
    expect($chapters->first()->word_count)->toBe(42);

    $write = $batch->payload['writes'][0];
    expect($write['type'])->toBe('chapter');
    expect($write['id'])->toBe($existing->id);
    expect($write['reused'] ?? false)->toBeTrue();
});

test('it links beats when provided, and re-attaches beats to reused chapters without detaching existing ones', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $act = Act::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->for($act, 'act')->create();
    $beatA = Beat::factory()->for($plotPoint, 'plotPoint')->create();
    $beatB = Beat::factory()->for($plotPoint, 'plotPoint')->create();
    $beatC = Beat::factory()->for($plotPoint, 'plotPoint')->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $existing = Chapter::factory()
        ->for($book, 'book')
        ->for($storyline, 'storyline')
        ->create(['title' => 'Opening']);
    $existing->beats()->attach($beatA->id);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'chapter', 'data' => [
            'title' => 'Opening',
            'storyline_id' => $storyline->id,
            'beat_ids' => [$beatB->id, $beatC->id],
        ]],
    ], 'Re-propose with beats');

    $existing->refresh();
    expect($existing->beats()->pluck('beats.id')->sort()->values()->all())
        ->toBe(collect([$beatA->id, $beatB->id, $beatC->id])->sort()->values()->all());
});

test('it seeds the first scene with beat intent when the book opts in', function () {
    $book = Book::factory()->create(['plot_coach_seed_stub_with_intent' => true]);
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $act = Act::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->for($act, 'act')->create();
    $beat = Beat::factory()->for($plotPoint, 'plotPoint')->create([
        'title' => 'First spark',
        'description' => 'She sees the smoke.',
    ]);
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'chapter', 'data' => [
            'title' => 'The Spark',
            'storyline_id' => $storyline->id,
            'beat_ids' => [$beat->id],
        ]],
    ], 'Add Ch. 1');

    $chapter = Chapter::query()->where('book_id', $book->id)->first();
    expect($chapter)->not->toBeNull();
    $content = $chapter->currentVersion()->first()->content;
    expect($content)->toContain('First spark');
    expect($content)->toContain('She sees the smoke.');
    expect($chapter->word_count)->toBeGreaterThan(0);
});

test('it rejects chapter writes that reference beats from another book', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $otherBook = Book::factory()->create();
    $otherAct = Act::factory()->for($otherBook, 'book')->create();
    $otherPlotPoint = PlotPoint::factory()->for($otherBook, 'book')->for($otherAct, 'act')->create();
    $foreignBeat = Beat::factory()->for($otherPlotPoint, 'plotPoint')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply($session, [
        ['type' => 'chapter', 'data' => [
            'title' => 'Bad',
            'storyline_id' => $storyline->id,
            'beat_ids' => [$foreignBeat->id],
        ]],
    ], 'Bad'))->toThrow(InvalidArgumentException::class);

    expect(Chapter::query()->where('book_id', $book->id)->count())->toBe(0);
});

test('it does not delete reused chapters on undo', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $existing = Chapter::factory()
        ->for($book, 'book')
        ->for($storyline, 'storyline')
        ->create(['title' => 'Pre-existing']);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'chapter', 'data' => [
            'title' => 'Pre-existing',
            'storyline_id' => $storyline->id,
        ]],
    ], 'Re-link');

    $service->undo($session);

    // Undo must not delete a chapter the user created before the batch.
    expect(Chapter::query()->where('id', $existing->id)->exists())->toBeTrue();
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

test('it applies a book_update batch patching whitelisted intake fields', function () {
    $book = Book::factory()->create([
        'premise' => null,
        'target_word_count' => null,
        'genre' => null,
    ]);
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;
    $batch = $service->apply($session, [
        ['type' => 'book_update', 'data' => [
            'premise' => '  A retired cellist investigates the drowning of her student.  ',
            'target_word_count' => 85000,
            'genre' => 'literary_fiction',
        ]],
    ], 'Pin intake');

    $book->refresh();
    expect($book->premise)->toBe('A retired cellist investigates the drowning of her student.');
    expect($book->target_word_count)->toBe(85000);
    expect($book->genre->value)->toBe('literary_fiction');

    $write = $batch->payload['writes'][0];
    expect($write['type'])->toBe('book_update');
    expect($write['id'])->toBe($book->id);
    expect($write['previous'])->toMatchArray([
        'premise' => null,
        'target_word_count' => null,
        'genre' => null,
    ]);
});

test('it ignores non-whitelisted fields in a book_update', function () {
    $book = Book::factory()->create(['title' => 'Original Title']);
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'book_update', 'data' => [
            'premise' => 'A concrete hook.',
            'title' => 'Hijacked Title',
            'author' => 'Hijacked Author',
        ]],
    ], 'Premise only');

    $book->refresh();
    expect($book->premise)->toBe('A concrete hook.');
    expect($book->title)->toBe('Original Title');
});

test('it rejects an empty book_update', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply(
        $session,
        [['type' => 'book_update', 'data' => []]],
        'Nothing',
    ))->toThrow(InvalidArgumentException::class);
});

test('it rejects an invalid genre value in book_update', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply(
        $session,
        [['type' => 'book_update', 'data' => ['genre' => 'not-a-genre']]],
        'Bad',
    ))->toThrow(InvalidArgumentException::class);
});

test('it rejects a plot_point whose act_id does not belong to this book', function () {
    $book = Book::factory()->create();
    $otherBook = Book::factory()->create();
    $foreignAct = Act::factory()->for($otherBook, 'book')->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply(
        $session,
        [['type' => 'plot_point', 'data' => [
            'title' => 'Orphan',
            'act_id' => $foreignAct->id,
        ]]],
        'Bad',
    ))->toThrow(InvalidArgumentException::class);

    expect(PlotPoint::query()->where('book_id', $book->id)->count())->toBe(0);
});

test('it resolves plot_point.act_number to the act_id of this book', function () {
    $book = Book::factory()->create();
    $act = Act::factory()->for($book, 'book')->create(['number' => 2]);
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'plot_point', 'data' => [
            'title' => 'Midpoint',
            'act_number' => 2,
        ]],
    ], 'Add midpoint');

    $pp = PlotPoint::query()->where('book_id', $book->id)->first();
    expect($pp)->not->toBeNull();
    expect($pp->act_id)->toBe($act->id);
});

test('it resolves beat.plot_point_title against a plot_point created earlier in the same batch', function () {
    $book = Book::factory()->create();
    $act = Act::factory()->for($book, 'book')->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'plot_point', 'data' => [
            'title' => 'Auslöser',
            'act_id' => $act->id,
        ]],
        ['type' => 'beat', 'data' => [
            'plot_point_title' => 'Auslöser',
            'title' => 'First cut',
        ]],
    ], 'Plot point + beat together');

    $pp = PlotPoint::query()->where('book_id', $book->id)->where('title', 'Auslöser')->first();
    $beat = Beat::query()->where('plot_point_id', $pp->id)->where('title', 'First cut')->first();
    expect($beat)->not->toBeNull();
});

test('it rejects beat.plot_point_title that does not match any plot point in this book', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply(
        $session,
        [['type' => 'beat', 'data' => [
            'plot_point_title' => 'Does Not Exist',
            'title' => 'Orphan beat',
        ]]],
        'Bad',
    ))->toThrow(InvalidArgumentException::class);

    expect(Beat::query()->count())->toBe(0);
});

test('it rejects plot_point.character_ids referencing characters from another book', function () {
    $book = Book::factory()->create();
    $otherBook = Book::factory()->create();
    $act = Act::factory()->for($book, 'book')->create();
    $foreignCharacter = Character::factory()->for($otherBook, 'book')->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply(
        $session,
        [['type' => 'plot_point', 'data' => [
            'title' => 'Bad',
            'act_id' => $act->id,
            'character_ids' => [$foreignCharacter->id],
        ]]],
        'Bad',
    ))->toThrow(InvalidArgumentException::class);

    expect(PlotPoint::query()->where('book_id', $book->id)->count())->toBe(0);
});

test('it rejects plot_point.act_number that does not match any act', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply(
        $session,
        [['type' => 'plot_point', 'data' => [
            'title' => 'Bad',
            'act_number' => 9,
        ]]],
        'Bad',
    ))->toThrow(InvalidArgumentException::class);
});

test('it rejects a negative target_word_count in book_update', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply(
        $session,
        [['type' => 'book_update', 'data' => ['target_word_count' => -10]]],
        'Bad',
    ))->toThrow(InvalidArgumentException::class);
});

test('it applies an act write and returns the id', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;
    $batch = $service->apply($session, [
        ['type' => 'act', 'data' => [
            'number' => 1,
            'title' => 'The Controlled Error',
            'description' => 'Lab accident opens the story.',
            'color' => '#4C8DFF',
            'sort_order' => 0,
        ]],
    ], 'Seed Act I');

    expect(Act::query()->where('book_id', $book->id)->count())->toBe(1);

    $act = Act::query()->where('book_id', $book->id)->first();
    expect($act->title)->toBe('The Controlled Error');
    expect($act->number)->toBe(1);
    expect($act->sort_order)->toBe(0);
    expect($batch->payload['writes'][0]['type'])->toBe('act');
    expect($batch->payload['writes'][0]['id'])->toBe($act->id);
});

test('it rejects an act without a title', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply(
        $session,
        [['type' => 'act', 'data' => ['number' => 1]]],
        'Bad',
    ))->toThrow(InvalidArgumentException::class);
});

test('it undoes an act create', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'act', 'data' => ['number' => 1, 'title' => 'Act I']],
    ], 'Add Act I');

    expect(Act::query()->where('book_id', $book->id)->count())->toBe(1);

    $service->undo($session);

    expect(Act::query()->where('book_id', $book->id)->count())->toBe(0);
});

test('it transitions session stage via session_update and captures previous value for undo', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create(['stage' => PlotCoachStage::Intake]);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'session_update', 'data' => ['stage' => 'plotting']],
    ], 'Advance to plotting');

    $session->refresh();
    expect($session->stage)->toBe(PlotCoachStage::Plotting);

    $service->undo($session);

    $session->refresh();
    expect($session->stage)->toBe(PlotCoachStage::Intake);
});

test('it sets coaching_mode via session_update', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create(['coaching_mode' => null]);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'session_update', 'data' => ['coaching_mode' => 'guided']],
    ], 'Set mode');

    $session->refresh();
    expect($session->coaching_mode?->value)->toBe('guided');
});

test('it rejects an invalid stage value in session_update', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply(
        $session,
        [['type' => 'session_update', 'data' => ['stage' => 'not-a-stage']]],
        'Bad',
    ))->toThrow(InvalidArgumentException::class);
});

test('it rejects an empty session_update', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply(
        $session,
        [['type' => 'session_update', 'data' => []]],
        'Bad',
    ))->toThrow(InvalidArgumentException::class);
});

test('it undoes a book_update by restoring the previous values', function () {
    $book = Book::factory()->create([
        'premise' => 'Old premise.',
        'target_word_count' => 50000,
        'genre' => 'thriller',
    ]);
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'book_update', 'data' => [
            'premise' => 'New premise.',
            'target_word_count' => 90000,
            'genre' => 'fantasy',
        ]],
    ], 'Rewrite intake');

    $book->refresh();
    expect($book->premise)->toBe('New premise.');
    expect($book->target_word_count)->toBe(90000);
    expect($book->genre->value)->toBe('fantasy');

    $service->undo($session);

    $book->refresh();
    expect($book->premise)->toBe('Old premise.');
    expect($book->target_word_count)->toBe(50000);
    expect($book->genre->value)->toBe('thriller');
});

test('it soft-deletes a wiki entry via a delete write', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $entry = WikiEntry::factory()->for($book)->create(['name' => 'Old Jakutsk']);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'delete', 'data' => ['target' => 'wiki_entry', 'id' => $entry->id]],
    ], 'Drop dupe');

    expect(WikiEntry::query()->find($entry->id))->toBeNull();
    expect(WikiEntry::withTrashed()->find($entry->id))->not->toBeNull()
        ->and(WikiEntry::withTrashed()->find($entry->id)->deleted_at)->not->toBeNull();
});

test('it restores a soft-deleted entity on undo', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $character = Character::factory()->for($book)->create(['name' => 'Old Maja']);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'delete', 'data' => ['target' => 'character', 'id' => $character->id]],
    ], 'Drop old Maja');

    expect(Character::query()->find($character->id))->toBeNull();

    $service->undo($session);

    expect(Character::query()->find($character->id))->not->toBeNull()
        ->and(Character::query()->find($character->id)->deleted_at)->toBeNull();
});

test('it merges via update + delete in a single batch', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $keep = WikiEntry::factory()->for($book)->create(['name' => 'Jakutsk', 'ai_description' => 'short']);
    $loser = WikiEntry::factory()->for($book)->create(['name' => 'Old Jakutsk']);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'wiki_entry', 'data' => ['id' => $keep->id, 'ai_description' => 'merged & richer']],
        ['type' => 'delete', 'data' => ['target' => 'wiki_entry', 'id' => $loser->id]],
    ], 'Merge dupes');

    $keep->refresh();
    expect($keep->ai_description)->toBe('merged & richer');
    expect(WikiEntry::query()->find($loser->id))->toBeNull();
});

test('it rejects a delete write targeting another book', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($bookA, 'book')->create();
    $foreign = WikiEntry::factory()->for($bookB)->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply($session, [
        ['type' => 'delete', 'data' => ['target' => 'wiki_entry', 'id' => $foreign->id]],
    ], 'Bad'))->toThrow(InvalidArgumentException::class);
});

test('it rejects a delete write with an unknown target', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply($session, [
        ['type' => 'delete', 'data' => ['target' => 'rocket', 'id' => 1]],
    ], 'Bad'))->toThrow(InvalidArgumentException::class);
});

test('it updates an existing beat when id is set instead of creating a duplicate', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $beat = Beat::factory()->for($plotPoint, 'plotPoint')->create([
        'title' => 'First cut',
        'description' => 'Initial sketch.',
    ]);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'beat', 'data' => [
            'id' => $beat->id,
            'description' => 'Tighter — the cut lands earlier.',
        ]],
    ], 'Refine first cut');

    $beat->refresh();

    expect(Beat::query()->where('plot_point_id', $plotPoint->id)->count())->toBe(1);
    expect($beat->title)->toBe('First cut');
    expect($beat->description)->toBe('Tighter — the cut lands earlier.');
});

test('it rehangs an existing beat onto a different plot_point via plot_point_id', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $oldPlotPoint = PlotPoint::factory()->for($book, 'book')->create(['title' => 'Swiss plot point']);
    $newPlotPoint = PlotPoint::factory()->for($book, 'book')->create(['title' => 'Blacksite finale']);
    $beat = Beat::factory()->for($oldPlotPoint, 'plotPoint')->create();

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'beat', 'data' => [
            'id' => $beat->id,
            'plot_point_id' => $newPlotPoint->id,
        ]],
    ], 'Rehang to finale');

    $beat->refresh();

    expect($beat->plot_point_id)->toBe($newPlotPoint->id);
});

test('it rehangs an existing beat via plot_point_title fallback', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $oldPlotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $newPlotPoint = PlotPoint::factory()->for($book, 'book')->create(['title' => 'Blacksite finale']);
    $beat = Beat::factory()->for($oldPlotPoint, 'plotPoint')->create();

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'beat', 'data' => [
            'id' => $beat->id,
            'plot_point_title' => 'Blacksite finale',
        ]],
    ], 'Rehang via title');

    $beat->refresh();

    expect($beat->plot_point_id)->toBe($newPlotPoint->id);
});

test('undo restores the previous plot_point_id of a rehung beat', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $oldPlotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $newPlotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $beat = Beat::factory()->for($oldPlotPoint, 'plotPoint')->create([
        'title' => 'Original',
        'description' => 'Original description.',
    ]);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'beat', 'data' => [
            'id' => $beat->id,
            'title' => 'Renamed',
            'description' => 'Rewritten.',
            'plot_point_id' => $newPlotPoint->id,
        ]],
    ], 'Rehang + refine');

    $service->undo($session);

    $beat->refresh();

    expect($beat->plot_point_id)->toBe($oldPlotPoint->id);
    expect($beat->title)->toBe('Original');
    expect($beat->description)->toBe('Original description.');
});

test('it rejects a beat update referencing a beat from another book', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($bookA, 'book')->create();
    $foreignPlotPoint = PlotPoint::factory()->for($bookB, 'book')->create();
    $foreignBeat = Beat::factory()->for($foreignPlotPoint, 'plotPoint')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply($session, [
        ['type' => 'beat', 'data' => ['id' => $foreignBeat->id, 'description' => 'no']],
    ], 'Cross-book attempt'))->toThrow(InvalidArgumentException::class);
});

test('it rejects rehanging a beat onto a plot_point from another book', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($bookA, 'book')->create();
    $localPlotPoint = PlotPoint::factory()->for($bookA, 'book')->create();
    $foreignPlotPoint = PlotPoint::factory()->for($bookB, 'book')->create();
    $beat = Beat::factory()->for($localPlotPoint, 'plotPoint')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply($session, [
        ['type' => 'beat', 'data' => [
            'id' => $beat->id,
            'plot_point_id' => $foreignPlotPoint->id,
        ]],
    ], 'Cross-book rehang'))->toThrow(InvalidArgumentException::class);
});

test('it rejects an empty beat update', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $beat = Beat::factory()->for($plotPoint, 'plotPoint')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply($session, [
        ['type' => 'beat', 'data' => ['id' => $beat->id]],
    ], 'Empty'))->toThrow(InvalidArgumentException::class);
});

test('it updates an existing plot_point when id is set', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create([
        'title' => 'Original title',
        'description' => 'Original prose.',
    ]);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'plot_point', 'data' => [
            'id' => $plotPoint->id,
            'description' => 'Tighter prose.',
        ]],
    ], 'Refine plot point');

    $plotPoint->refresh();

    expect(PlotPoint::query()->where('book_id', $book->id)->count())->toBe(1);
    expect($plotPoint->title)->toBe('Original title');
    expect($plotPoint->description)->toBe('Tighter prose.');
});

test('it rehangs an existing plot_point onto a different act via act_id', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $oldAct = Act::factory()->for($book, 'book')->create();
    $newAct = Act::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create(['act_id' => $oldAct->id]);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'plot_point', 'data' => [
            'id' => $plotPoint->id,
            'act_id' => $newAct->id,
        ]],
    ], 'Rehang plot_point');

    $plotPoint->refresh();

    expect($plotPoint->act_id)->toBe($newAct->id);
});

test('it rehangs a plot_point via act_number fallback', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $oldAct = Act::factory()->for($book, 'book')->create(['number' => 1]);
    $newAct = Act::factory()->for($book, 'book')->create(['number' => 3]);
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create(['act_id' => $oldAct->id]);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'plot_point', 'data' => [
            'id' => $plotPoint->id,
            'act_number' => 3,
        ]],
    ], 'Rehang via act_number');

    $plotPoint->refresh();

    expect($plotPoint->act_id)->toBe($newAct->id);
});

test('undo restores the previous act_id of a rehung plot_point', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $oldAct = Act::factory()->for($book, 'book')->create();
    $newAct = Act::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create([
        'act_id' => $oldAct->id,
        'description' => 'Original.',
    ]);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'plot_point', 'data' => [
            'id' => $plotPoint->id,
            'description' => 'New.',
            'act_id' => $newAct->id,
        ]],
    ], 'Rehang + refine plot_point');

    $service->undo($session);

    $plotPoint->refresh();

    expect($plotPoint->act_id)->toBe($oldAct->id);
    expect($plotPoint->description)->toBe('Original.');
});

test('it rejects a plot_point update referencing another book', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($bookA, 'book')->create();
    $foreign = PlotPoint::factory()->for($bookB, 'book')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply($session, [
        ['type' => 'plot_point', 'data' => ['id' => $foreign->id, 'description' => 'no']],
    ], 'Cross-book'))->toThrow(InvalidArgumentException::class);
});

test('it rejects rehanging a plot_point onto an act from another book', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($bookA, 'book')->create();
    $localAct = Act::factory()->for($bookA, 'book')->create();
    $foreignAct = Act::factory()->for($bookB, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($bookA, 'book')->create(['act_id' => $localAct->id]);

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply($session, [
        ['type' => 'plot_point', 'data' => ['id' => $plotPoint->id, 'act_id' => $foreignAct->id]],
    ], 'Cross-book rehang'))->toThrow(InvalidArgumentException::class);
});

test('it rejects an empty plot_point update', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply($session, [
        ['type' => 'plot_point', 'data' => ['id' => $plotPoint->id]],
    ], 'Empty'))->toThrow(InvalidArgumentException::class);
});

test('it patches plot_point.character_ids and undo restores the previous attachments', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $alice = Character::factory()->for($book, 'book')->create();
    $bob = Character::factory()->for($book, 'book')->create();
    $carol = Character::factory()->for($book, 'book')->create();
    $plotPoint->characters()->sync([$alice->id, $bob->id]);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'plot_point', 'data' => [
            'id' => $plotPoint->id,
            'character_ids' => [$bob->id, $carol->id],
        ]],
    ], 'Recast plot point');

    expect($plotPoint->characters()->pluck('characters.id')->sort()->values()->all())
        ->toBe(collect([$bob->id, $carol->id])->sort()->values()->all());

    $service->undo($session);

    expect($plotPoint->characters()->pluck('characters.id')->sort()->values()->all())
        ->toBe(collect([$alice->id, $bob->id])->sort()->values()->all());
});

test('it clears plot_point.character_ids when an empty list is passed', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $alice = Character::factory()->for($book, 'book')->create();
    $plotPoint->characters()->sync([$alice->id]);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'plot_point', 'data' => [
            'id' => $plotPoint->id,
            'character_ids' => [],
        ]],
    ], 'Clear cast');

    expect($plotPoint->characters()->count())->toBe(0);
});

test('it rejects plot_point.character_ids update referencing characters from another book', function () {
    $book = Book::factory()->create();
    $otherBook = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $foreign = Character::factory()->for($otherBook, 'book')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply($session, [
        ['type' => 'plot_point', 'data' => [
            'id' => $plotPoint->id,
            'character_ids' => [$foreign->id],
        ]],
    ], 'Cross-book'))->toThrow(InvalidArgumentException::class);
});

test('it updates an existing act when id is set', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $act = Act::factory()->for($book, 'book')->create([
        'title' => 'Original',
        'description' => 'Original description.',
        'number' => 1,
        'sort_order' => 0,
    ]);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'act', 'data' => [
            'id' => $act->id,
            'title' => 'Renamed',
            'description' => 'Tighter description.',
        ]],
    ], 'Rename Act I');

    $act->refresh();

    expect(Act::query()->where('book_id', $book->id)->count())->toBe(1);
    expect($act->title)->toBe('Renamed');
    expect($act->description)->toBe('Tighter description.');
});

test('it renumbers and reorders an act via update', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $act = Act::factory()->for($book, 'book')->create(['number' => 1, 'sort_order' => 0]);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'act', 'data' => [
            'id' => $act->id,
            'number' => 3,
            'sort_order' => 2,
        ]],
    ], 'Reposition act');

    $act->refresh();

    expect($act->number)->toBe(3);
    expect($act->sort_order)->toBe(2);
});

test('undo restores the previous values of an updated act', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $act = Act::factory()->for($book, 'book')->create([
        'title' => 'Original',
        'description' => 'Original.',
        'number' => 1,
        'color' => '#111111',
        'sort_order' => 0,
    ]);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'act', 'data' => [
            'id' => $act->id,
            'title' => 'Renamed',
            'description' => 'New.',
            'number' => 2,
            'color' => '#FFFFFF',
            'sort_order' => 1,
        ]],
    ], 'Rewrite act');

    $service->undo($session);

    $act->refresh();

    expect($act->title)->toBe('Original');
    expect($act->description)->toBe('Original.');
    expect($act->number)->toBe(1);
    expect($act->color)->toBe('#111111');
    expect($act->sort_order)->toBe(0);
});

test('it rejects an act update referencing another book', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($bookA, 'book')->create();
    $foreign = Act::factory()->for($bookB, 'book')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply($session, [
        ['type' => 'act', 'data' => ['id' => $foreign->id, 'title' => 'no']],
    ], 'Cross-book'))->toThrow(InvalidArgumentException::class);
});

test('it rejects an empty act update', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $act = Act::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply($session, [
        ['type' => 'act', 'data' => ['id' => $act->id]],
    ], 'Empty'))->toThrow(InvalidArgumentException::class);
});

test('it updates an existing storyline when id is set', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $storyline = Storyline::factory()->for($book, 'book')->create([
        'name' => 'Original',
        'type' => 'main',
        'color' => '#111111',
        'sort_order' => 0,
    ]);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'storyline', 'data' => [
            'id' => $storyline->id,
            'name' => 'Resistance arc',
            'type' => 'parallel',
            'color' => '#B87333',
            'sort_order' => 2,
        ]],
    ], 'Rewrite storyline');

    $storyline->refresh();

    expect(Storyline::query()->where('book_id', $book->id)->count())->toBe(1);
    expect($storyline->name)->toBe('Resistance arc');
    expect($storyline->type->value)->toBe('parallel');
    expect($storyline->color)->toBe('#B87333');
    expect($storyline->sort_order)->toBe(2);
});

test('undo restores the previous values of an updated storyline', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $storyline = Storyline::factory()->for($book, 'book')->create([
        'name' => 'Original',
        'type' => 'main',
        'color' => '#111111',
        'sort_order' => 0,
    ]);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'storyline', 'data' => [
            'id' => $storyline->id,
            'name' => 'Renamed',
            'type' => 'parallel',
        ]],
    ], 'Rewrite');

    $service->undo($session);

    $storyline->refresh();

    expect($storyline->name)->toBe('Original');
    expect($storyline->type->value)->toBe('main');
});

test('it rejects an invalid storyline type on update', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply($session, [
        ['type' => 'storyline', 'data' => ['id' => $storyline->id, 'type' => 'not-a-type']],
    ], 'Bad'))->toThrow(InvalidArgumentException::class);
});

test('it rejects a storyline update referencing another book', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($bookA, 'book')->create();
    $foreign = Storyline::factory()->for($bookB, 'book')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply($session, [
        ['type' => 'storyline', 'data' => ['id' => $foreign->id, 'name' => 'no']],
    ], 'Cross-book'))->toThrow(InvalidArgumentException::class);
});

test('it rejects an empty storyline update', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply($session, [
        ['type' => 'storyline', 'data' => ['id' => $storyline->id]],
    ], 'Empty'))->toThrow(InvalidArgumentException::class);
});

test('it updates an existing chapter when id is set', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $chapter = Chapter::factory()
        ->for($book, 'book')
        ->for($storyline, 'storyline')
        ->create(['title' => 'Original']);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'chapter', 'data' => [
            'id' => $chapter->id,
            'title' => 'Renamed',
        ]],
    ], 'Rename chapter');

    $chapter->refresh();

    expect(Chapter::query()->where('book_id', $book->id)->count())->toBe(1);
    expect($chapter->title)->toBe('Renamed');
});

test('it moves a chapter to a different storyline', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $oldStoryline = Storyline::factory()->for($book, 'book')->create();
    $newStoryline = Storyline::factory()->for($book, 'book')->create();
    $chapter = Chapter::factory()
        ->for($book, 'book')
        ->for($oldStoryline, 'storyline')
        ->create();

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'chapter', 'data' => [
            'id' => $chapter->id,
            'storyline_id' => $newStoryline->id,
        ]],
    ], 'Move chapter');

    $chapter->refresh();

    expect($chapter->storyline_id)->toBe($newStoryline->id);
});

test('it changes POV character and act on a chapter', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $act = Act::factory()->for($book, 'book')->create();
    $character = Character::factory()->for($book, 'book')->create();
    $chapter = Chapter::factory()
        ->for($book, 'book')
        ->for($storyline, 'storyline')
        ->create();

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'chapter', 'data' => [
            'id' => $chapter->id,
            'pov_character_id' => $character->id,
            'act_id' => $act->id,
        ]],
    ], 'Set POV + act');

    $chapter->refresh();

    expect($chapter->pov_character_id)->toBe($character->id);
    expect($chapter->act_id)->toBe($act->id);
});

test('it resyncs a chapter beat_ids fully — replaces, never just appends', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $beatA = Beat::factory()->for($plotPoint, 'plotPoint')->create();
    $beatB = Beat::factory()->for($plotPoint, 'plotPoint')->create();
    $beatC = Beat::factory()->for($plotPoint, 'plotPoint')->create();
    $chapter = Chapter::factory()
        ->for($book, 'book')
        ->for($storyline, 'storyline')
        ->create();
    $chapter->beats()->attach([$beatA->id, $beatB->id]);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'chapter', 'data' => [
            'id' => $chapter->id,
            'beat_ids' => [$beatB->id, $beatC->id],
        ]],
    ], 'Resync beats');

    expect($chapter->beats()->pluck('beats.id')->sort()->values()->all())
        ->toBe(collect([$beatB->id, $beatC->id])->sort()->values()->all());
});

test('undo restores the previous chapter values and beat pivot', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $oldStoryline = Storyline::factory()->for($book, 'book')->create();
    $newStoryline = Storyline::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $beatA = Beat::factory()->for($plotPoint, 'plotPoint')->create();
    $beatB = Beat::factory()->for($plotPoint, 'plotPoint')->create();
    $beatC = Beat::factory()->for($plotPoint, 'plotPoint')->create();
    $chapter = Chapter::factory()
        ->for($book, 'book')
        ->for($oldStoryline, 'storyline')
        ->create(['title' => 'Original']);
    $chapter->beats()->attach([$beatA->id, $beatB->id]);

    $service = new PlotCoachBatchService;
    $service->apply($session, [
        ['type' => 'chapter', 'data' => [
            'id' => $chapter->id,
            'title' => 'Renamed',
            'storyline_id' => $newStoryline->id,
            'beat_ids' => [$beatC->id],
        ]],
    ], 'Rewrite chapter');

    $service->undo($session);

    $chapter->refresh();

    expect($chapter->title)->toBe('Original');
    expect($chapter->storyline_id)->toBe($oldStoryline->id);
    expect($chapter->beats()->pluck('beats.id')->sort()->values()->all())
        ->toBe(collect([$beatA->id, $beatB->id])->sort()->values()->all());
});

test('it rejects moving a chapter to a storyline from another book', function () {
    $book = Book::factory()->create();
    $otherBook = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $foreignStoryline = Storyline::factory()->for($otherBook, 'book')->create();
    $chapter = Chapter::factory()
        ->for($book, 'book')
        ->for($storyline, 'storyline')
        ->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply($session, [
        ['type' => 'chapter', 'data' => [
            'id' => $chapter->id,
            'storyline_id' => $foreignStoryline->id,
        ]],
    ], 'Cross-book'))->toThrow(InvalidArgumentException::class);
});

test('it rejects a chapter update referencing another book', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($bookA, 'book')->create();
    $foreignStoryline = Storyline::factory()->for($bookB, 'book')->create();
    $foreign = Chapter::factory()
        ->for($bookB, 'book')
        ->for($foreignStoryline, 'storyline')
        ->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply($session, [
        ['type' => 'chapter', 'data' => ['id' => $foreign->id, 'title' => 'no']],
    ], 'Cross-book'))->toThrow(InvalidArgumentException::class);
});

test('it rejects an empty chapter update', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $chapter = Chapter::factory()
        ->for($book, 'book')
        ->for($storyline, 'storyline')
        ->create();

    $service = new PlotCoachBatchService;

    expect(fn () => $service->apply($session, [
        ['type' => 'chapter', 'data' => ['id' => $chapter->id]],
    ], 'Empty'))->toThrow(InvalidArgumentException::class);
});
