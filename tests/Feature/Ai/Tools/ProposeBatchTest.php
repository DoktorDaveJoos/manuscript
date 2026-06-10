<?php

use App\Ai\Tools\Plot\ProposeBatch;
use App\Enums\WikiEntryKind;
use App\Models\Book;
use App\Models\Character;
use App\Models\PlotCoachProposal;
use App\Models\PlotCoachSession;
use App\Models\PlotPoint;
use App\Models\Storyline;
use App\Models\WikiEntry;
use Laravel\Ai\Tools\Request;

it('returns a compact preview with summary, item count, and sentinel', function () {
    $book = Book::factory()->create();

    $tool = new ProposeBatch($book->id);
    $request = new Request([
        'summary' => 'Seed resistance arc',
        'writes' => [
            ['type' => 'character', 'data' => ['name' => 'Mara', 'ai_description' => 'A fighter.']],
            ['type' => 'character', 'data' => ['name' => 'Tomas']],
            ['type' => 'storyline', 'data' => ['name' => 'Resistance arc', 'type' => 'main']],
            ['type' => 'plot_point', 'data' => ['title' => 'First arrest', 'type' => 'setup']],
            ['type' => 'beat', 'data' => ['title' => 'Knock at dawn', 'description' => 'Before sunrise.']],
            ['type' => 'wiki_entry', 'data' => ['kind' => 'location', 'name' => 'The Archive']],
        ],
    ]);

    $result = (string) $tool->handle($request);

    // Compact on purpose: the writes appear once, in the sentinel JSON the
    // card is rendered from — no second per-item markdown copy.
    expect($result)
        ->toContain('## Proposed batch')
        ->toContain('Seed resistance arc')
        ->toContain('6 items — awaiting approval.')
        ->toContain('<!-- PLOT_COACH_BATCH_PROPOSAL')
        ->toContain('Knock at dawn')
        ->not->toContain('### Characters')
        ->not->toContain('### Storylines');
});

it('does not persist anything', function () {
    $book = Book::factory()->create();

    $characterCountBefore = Character::query()->count();
    $storylineCountBefore = Storyline::query()->count();
    $plotPointCountBefore = PlotPoint::query()->count();

    $tool = new ProposeBatch($book->id);
    $tool->handle(new Request([
        'summary' => 'Would-be writes',
        'writes' => [
            ['type' => 'character', 'data' => ['name' => 'Doesnt-save']],
            ['type' => 'storyline', 'data' => ['name' => 'Also doesnt save']],
        ],
    ]));

    expect(Character::query()->count())->toBe($characterCountBefore);
    expect(Storyline::query()->count())->toBe($storylineCountBefore);
    expect(PlotPoint::query()->count())->toBe($plotPointCountBefore);
});

it('handles an empty writes array gracefully', function () {
    $book = Book::factory()->create();

    $tool = new ProposeBatch($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'empty',
        'writes' => [],
    ]));

    expect($result)->toContain('Batch preview: (empty)');
});

it('returns an explicit parse error when writes is a malformed JSON string', function () {
    // Real failure mode: the LLM emits a JSON-encoded writes string with an
    // unescaped " in a description value, breaking the parse silently.
    $book = Book::factory()->create();
    $malformed = '[{"type":"beat","data":{"title":"Signal","description":"He says: **"Wenn ich es sage: Kopf."** then they move."}}]';

    $tool = new ProposeBatch($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'malformed',
        'writes' => $malformed,
    ]));

    expect($result)
        ->toContain('Batch failed')
        ->toContain('writes')
        ->toContain('JSON')
        ->not->toContain('Batch preview: (empty)')
        ->not->toContain('PLOT_COACH_BATCH_PROPOSAL');
});

it('appends a machine-readable sentinel block with proposal_id, writes, summary', function () {
    $book = Book::factory()->create();

    $tool = new ProposeBatch($book->id);
    $writes = [
        ['type' => 'character', 'data' => ['name' => 'Mara']],
        ['type' => 'storyline', 'data' => ['name' => 'Resistance arc', 'type' => 'main']],
    ];

    $result = (string) $tool->handle(new Request([
        'summary' => 'Seed resistance arc',
        'writes' => $writes,
    ]));

    expect($result)
        ->toContain('<!-- PLOT_COACH_BATCH_PROPOSAL')
        ->toContain('-->');

    // Extract the JSON payload between the delimiters and make sure it parses
    // into the expected shape.
    $matched = preg_match(
        '/<!-- PLOT_COACH_BATCH_PROPOSAL\n(.*?)\n-->/s',
        $result,
        $matches,
    );

    expect($matched)->toBe(1);

    $payload = json_decode($matches[1], true);

    expect($payload)
        ->toBeArray()
        ->toHaveKeys(['proposal_id', 'writes', 'summary']);

    expect($payload['summary'])->toBe('Seed resistance arc');
    expect($payload['writes'])->toBe($writes);
    expect($payload['proposal_id'])->toBeString()->not->toBeEmpty();
});

it('flags duplicate character names against the book', function () {
    $book = Book::factory()->create();
    Character::factory()->for($book)->create(['name' => 'Mara']);

    $tool = new ProposeBatch($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'Add cast',
        'writes' => [
            ['type' => 'character', 'data' => ['name' => 'Mara', 'ai_description' => 'new write']],
            ['type' => 'character', 'data' => ['name' => 'Kael']],
        ],
    ]));

    expect($result)
        ->toContain('name already exists')
        ->toContain('1 proposed name already exist');
});

it('flags duplicate wiki entry names too', function () {
    $book = Book::factory()->create();
    WikiEntry::factory()->for($book)->create([
        'name' => 'The Archive',
        'kind' => WikiEntryKind::Location,
    ]);

    $tool = new ProposeBatch($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'Add location',
        'writes' => [
            ['type' => 'wiki_entry', 'data' => ['kind' => 'location', 'name' => 'The Archive']],
        ],
    ]));

    expect($result)->toContain('name already exists');
});

it('is case and whitespace insensitive when detecting duplicates', function () {
    $book = Book::factory()->create();
    Character::factory()->for($book)->create(['name' => 'Mara']);

    $tool = new ProposeBatch($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'Test',
        'writes' => [
            ['type' => 'character', 'data' => ['name' => '  MARA  ']],
        ],
    ]));

    expect($result)->toContain('name already exists');
});

it('scopes duplicate detection to the given book', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    Character::factory()->for($bookA)->create(['name' => 'Mara']);

    // Same name proposed against a different book — not a duplicate.
    $tool = new ProposeBatch($bookB->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'Test',
        'writes' => [
            ['type' => 'character', 'data' => ['name' => 'Mara']],
        ],
    ]));

    expect($result)->not->toContain('name already exists');
});

it('carries book_update writes through to the sentinel payload', function () {
    $book = Book::factory()->create();
    $tool = new ProposeBatch($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'Pin intake',
        'writes' => [
            ['type' => 'book_update', 'data' => [
                'premise' => 'A retired cellist investigates a drowning.',
                'target_word_count' => 85000,
                'genre' => 'literary_fiction',
            ]],
        ],
    ]));

    preg_match('/<!-- PLOT_COACH_BATCH_PROPOSAL\n(.*?)\n-->/s', $result, $matches);
    $payload = json_decode($matches[1], true);

    expect($payload['writes'][0]['type'])->toBe('book_update');
    expect($payload['writes'][0]['data'])->toMatchArray([
        'premise' => 'A retired cellist investigates a drowning.',
        'target_word_count' => 85000,
        'genre' => 'literary_fiction',
    ]);
});

it('enriches update writes with the existing entity name + kind for the card', function () {
    $book = Book::factory()->create();
    $character = Character::factory()->for($book)->create(['name' => 'Maja']);
    $entry = WikiEntry::factory()->for($book)->create([
        'name' => 'Jakutsk',
        'kind' => WikiEntryKind::Location,
    ]);

    $tool = new ProposeBatch($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'Refine entries',
        'writes' => [
            ['type' => 'character', 'data' => ['id' => $character->id, 'ai_description' => 'tighter']],
            ['type' => 'wiki_entry', 'data' => ['id' => $entry->id, 'ai_description' => 'merged']],
        ],
    ]));

    // Sentinel payload carries the hint fields so the React card can render
    // the entity's current name for an id-only patch.
    preg_match('/<!-- PLOT_COACH_BATCH_PROPOSAL\n(.*?)\n-->/s', $result, $matches);
    $payload = json_decode($matches[1], true);

    expect($payload['writes'][0]['data'])->toMatchArray([
        'id' => $character->id,
        '_existing_name' => 'Maja',
    ]);
    expect($payload['writes'][1]['data'])->toMatchArray([
        'id' => $entry->id,
        '_existing_name' => 'Jakutsk',
        '_existing_kind' => 'location',
    ]);
});

it('produces a unique proposal_id per invocation', function () {
    $book = Book::factory()->create();

    $tool = new ProposeBatch($book->id);
    $req = new Request([
        'summary' => 'x',
        'writes' => [['type' => 'character', 'data' => ['name' => 'A']]],
    ]);

    $a = (string) $tool->handle($req);
    $b = (string) $tool->handle($req);

    preg_match('/"proposal_id":"([^"]+)"/', $a, $mA);
    preg_match('/"proposal_id":"([^"]+)"/', $b, $mB);

    expect($mA[1])->not->toBe($mB[1]);
});

it('carries full chapter wiring (POV, supporting cast, wiki entries) through to the sentinel', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create(['name' => 'Main arc']);
    $pov = Character::factory()->for($book, 'book')->create(['name' => 'Maja']);

    $tool = new ProposeBatch($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'Wired stub',
        'writes' => [
            ['type' => 'chapter', 'data' => [
                'title' => 'Opening',
                'storyline_id' => $storyline->id,
                'pov_character_id' => $pov->id,
                'beat_ids' => [1, 2],
                'character_ids' => [3, 4, 5],
                'wiki_entry_ids' => [9],
            ]],
        ],
    ]));

    preg_match('/<!-- PLOT_COACH_BATCH_PROPOSAL\n(.*?)\n-->/s', $result, $matches);
    $payload = json_decode($matches[1], true);

    expect($payload['writes'][0]['data'])->toMatchArray([
        'title' => 'Opening',
        'storyline_id' => $storyline->id,
        'pov_character_id' => $pov->id,
        'beat_ids' => [1, 2],
        'character_ids' => [3, 4, 5],
        'wiki_entry_ids' => [9],
    ]);
});

it('binds to the constructor book and ignores a payload book_id', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();

    $sessionA = PlotCoachSession::factory()->for($bookA, 'book')->create();
    $sessionB = PlotCoachSession::factory()->for($bookB, 'book')->create();

    $tool = new ProposeBatch($bookA->id);
    $tool->handle(new Request([
        'book_id' => $bookB->id, // hallucinated by the model — must be ignored
        'summary' => 'Save Maja',
        'writes' => [
            ['type' => 'character', 'data' => ['name' => 'Maja']],
        ],
    ]));

    expect(PlotCoachProposal::query()->where('session_id', $sessionA->id)->count())->toBe(1);
    expect(PlotCoachProposal::query()->where('session_id', $sessionB->id)->count())->toBe(0);
});

it('rejects writes with unknown types instead of persisting them for a doomed apply', function () {
    $book = Book::factory()->create();
    PlotCoachSession::factory()->for($book, 'book')->create();

    $tool = new ProposeBatch($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'Mixed validity',
        'writes' => [
            ['type' => 'character', 'data' => ['name' => 'Maja']],
            ['type' => 'Charakter', 'data' => ['name' => 'Tomas']], // unknown type
        ],
    ]));

    expect($result)
        ->toContain('unknown write type')
        ->not->toContain('PLOT_COACH_BATCH_PROPOSAL');

    expect(PlotCoachProposal::query()->count())->toBe(0);
});

it('persists the proposal on the bound session even when another session is active', function () {
    $book = Book::factory()->create();
    $archived = PlotCoachSession::factory()->for($book, 'book')->archived()->create();
    $active = PlotCoachSession::factory()->for($book, 'book')->create();

    $tool = new ProposeBatch($book->id, session: $archived);
    $tool->handle(new Request([
        'summary' => 'Save Maja',
        'writes' => [['type' => 'character', 'data' => ['name' => 'Maja']]],
    ]));

    expect(PlotCoachProposal::query()->where('session_id', $archived->id)->count())->toBe(1);
    expect(PlotCoachProposal::query()->where('session_id', $active->id)->count())->toBe(0);
});
