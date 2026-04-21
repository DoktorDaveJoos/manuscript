<?php

use App\Ai\Tools\Plot\ProposeBatch;
use App\Models\Book;
use App\Models\Character;
use App\Models\PlotPoint;
use App\Models\Storyline;
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
