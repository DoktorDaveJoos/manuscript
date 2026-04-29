<?php

use App\Ai\Tools\Plot\ProposeBatch;
use App\Enums\WikiEntryKind;
use App\Models\Book;
use App\Models\Character;
use App\Models\PlotPoint;
use App\Models\Storyline;
use App\Models\WikiEntry;
use Laravel\Ai\Tools\Request;

it('returns a markdown preview with sections grouped by type', function () {
    $tool = new ProposeBatch;
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

    expect($result)
        ->toContain('## Proposed batch')
        ->toContain('Seed resistance arc')
        ->toContain('### Characters')
        ->toContain('Mara — A fighter.')
        ->toContain('Tomas')
        ->toContain('### Storylines')
        ->toContain('[main] Resistance arc')
        ->toContain('### Plot points')
        ->toContain('[setup] First arrest')
        ->toContain('### Beats')
        ->toContain('Knock at dawn — Before sunrise.')
        ->toContain('### Wiki entries')
        ->toContain('[location] The Archive')
        ->toContain('6 items — awaiting approval.');
});

it('does not persist anything', function () {
    $book = Book::factory()->create();

    $characterCountBefore = Character::query()->count();
    $storylineCountBefore = Storyline::query()->count();
    $plotPointCountBefore = PlotPoint::query()->count();

    $tool = new ProposeBatch;
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
    $tool = new ProposeBatch;
    $result = (string) $tool->handle(new Request([
        'summary' => 'empty',
        'writes' => [],
    ]));

    expect($result)->toContain('Batch preview: (empty)');
});

it('returns an explicit parse error when writes is a malformed JSON string', function () {
    // Real failure mode: the LLM emits a JSON-encoded writes string with an
    // unescaped " in a description value, breaking the parse silently.
    $malformed = '[{"type":"beat","data":{"title":"Signal","description":"He says: **"Wenn ich es sage: Kopf."** then they move."}}]';

    $tool = new ProposeBatch;
    $result = (string) $tool->handle(new Request([
        'book_id' => 1,
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
    $tool = new ProposeBatch;
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

it('flags duplicate character names against the book when book_id is provided', function () {
    $book = Book::factory()->create();
    Character::factory()->for($book)->create(['name' => 'Mara']);

    $tool = new ProposeBatch;
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
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

    $tool = new ProposeBatch;
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
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

    $tool = new ProposeBatch;
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'summary' => 'Test',
        'writes' => [
            ['type' => 'character', 'data' => ['name' => '  MARA  ']],
        ],
    ]));

    expect($result)->toContain('name already exists');
});

it('does not flag duplicates when book_id is omitted', function () {
    $book = Book::factory()->create();
    Character::factory()->for($book)->create(['name' => 'Mara']);

    $tool = new ProposeBatch;
    $result = (string) $tool->handle(new Request([
        'summary' => 'Test',
        'writes' => [
            ['type' => 'character', 'data' => ['name' => 'Mara']],
        ],
    ]));

    expect($result)->not->toContain('name already exists');
});

it('scopes duplicate detection to the given book', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    Character::factory()->for($bookA)->create(['name' => 'Mara']);

    $tool = new ProposeBatch;

    // Same name proposed against a different book — not a duplicate.
    $result = (string) $tool->handle(new Request([
        'book_id' => $bookB->id,
        'summary' => 'Test',
        'writes' => [
            ['type' => 'character', 'data' => ['name' => 'Mara']],
        ],
    ]));

    expect($result)->not->toContain('name already exists');
});

it('renders a Book details section for a book_update write', function () {
    $tool = new ProposeBatch;
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

    expect($result)
        ->toContain('### Book details')
        ->toContain('premise: A retired cellist investigates a drowning.')
        ->toContain('target length: 85,000 words')
        ->toContain('genre: literary_fiction');
});

it('enriches update writes with the existing entity name + kind for the preview', function () {
    $book = Book::factory()->create();
    $character = Character::factory()->for($book)->create(['name' => 'Maja']);
    $entry = WikiEntry::factory()->for($book)->create([
        'name' => 'Jakutsk',
        'kind' => WikiEntryKind::Location,
    ]);

    $tool = new ProposeBatch;
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'summary' => 'Refine entries',
        'writes' => [
            ['type' => 'character', 'data' => ['id' => $character->id, 'ai_description' => 'tighter']],
            ['type' => 'wiki_entry', 'data' => ['id' => $entry->id, 'ai_description' => 'merged']],
        ],
    ]));

    // Markdown preview falls back to the existing name instead of "(unnamed)".
    expect($result)
        ->toContain('_Update_ Maja — tighter')
        ->toContain('_Update_ [location] Jakutsk — merged');

    // Sentinel payload carries the same hint fields so the React card can use them.
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

it('does not enrich writes when book_id is omitted', function () {
    $book = Book::factory()->create();
    $character = Character::factory()->for($book)->create(['name' => 'Maja']);

    $tool = new ProposeBatch;
    $result = (string) $tool->handle(new Request([
        'summary' => 'No book_id',
        'writes' => [
            ['type' => 'character', 'data' => ['id' => $character->id, 'ai_description' => 'tighter']],
        ],
    ]));

    expect($result)->not->toContain('Maja');
});

it('produces a unique proposal_id per invocation', function () {
    $tool = new ProposeBatch;
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
