<?php

use App\Ai\Tools\Plot\ProposeBatch;
use App\Models\Beat;
use App\Models\Book;
use App\Models\Character;
use App\Models\PlotPoint;
use App\Models\Storyline;
use App\Models\WikiEntry;
use Laravel\Ai\Tools\Request;

it('rejects a chapter write whose beats reference characters not in character_ids', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $beat = Beat::factory()->for($plotPoint, 'plotPoint')->create([
        'title' => 'Maja boards',
        'description' => 'Maja boards the Voyager probe.',
    ]);
    Character::factory()->for($book, 'book')->create(['name' => 'Maja']);

    $tool = new ProposeBatch($book->id);
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'summary' => 'Single chapter slice',
        'writes' => [
            ['type' => 'chapter', 'data' => [
                'title' => 'Opening',
                'storyline_id' => $storyline->id,
                'beat_ids' => [$beat->id],
            ]],
        ],
    ]));

    expect($result)
        ->toContain('Chapter entity links missing')
        ->toContain('Maja')
        ->not->toContain('PLOT_COACH_BATCH_PROPOSAL');
});

it('passes through a chapter write with populated entity ids', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $beat = Beat::factory()->for($plotPoint, 'plotPoint')->create([
        'title' => 'Maja boards',
        'description' => 'Maja boards the Voyager probe.',
    ]);
    $maja = Character::factory()->for($book, 'book')->create(['name' => 'Maja']);
    $voyager = WikiEntry::factory()->for($book, 'book')->create(['name' => 'Voyager']);

    $tool = new ProposeBatch($book->id);
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'summary' => 'Single chapter slice',
        'writes' => [
            ['type' => 'chapter', 'data' => [
                'title' => 'Opening',
                'storyline_id' => $storyline->id,
                'beat_ids' => [$beat->id],
                'character_ids' => [$maja->id],
                'wiki_entry_ids' => [$voyager->id],
            ]],
        ],
    ]));

    expect($result)->toContain('PLOT_COACH_BATCH_PROPOSAL');
});

it('does not validate non-chapter writes (a character write alongside a chapter)', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $beat = Beat::factory()->for($plotPoint, 'plotPoint')->create([
        'title' => 'Empty room',
        'description' => 'A nondescript empty room.',
    ]);

    $tool = new ProposeBatch($book->id);
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'summary' => 'Mixed batch',
        'writes' => [
            ['type' => 'character', 'data' => ['name' => 'Maja']],
            ['type' => 'chapter', 'data' => [
                'title' => 'Opening',
                'storyline_id' => $storyline->id,
                'beat_ids' => [$beat->id],
            ]],
        ],
    ]));

    // Empty room beat doesn't reference any book entity, so chapter passes;
    // character write goes through unaffected.
    expect($result)
        ->toContain('PLOT_COACH_BATCH_PROPOSAL')
        ->toContain('Maja');
});
