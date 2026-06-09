<?php

use App\Ai\Tools\Plot\ProposeChapterPlan;
use App\Models\Beat;
use App\Models\Book;
use App\Models\Character;
use App\Models\PlotPoint;
use App\Models\Storyline;
use App\Models\WikiEntry;
use Laravel\Ai\Tools\Request;

it('rejects when a beat references a book character but character_ids is empty', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $beat = Beat::factory()->for($plotPoint, 'plotPoint')->create([
        'title' => 'Maja boards',
        'description' => 'Maja boards the Voyager probe.',
    ]);
    Character::factory()->for($book, 'book')->create(['name' => 'Maja']);

    $tool = new ProposeChapterPlan($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'Slice 1',
        'chapters' => [
            ['title' => 'Opening', 'storyline_id' => $storyline->id, 'beat_ids' => [$beat->id]],
        ],
    ]));

    expect($result)
        ->toContain('Chapter entity links missing')
        ->toContain('character_ids is empty')
        ->toContain('Maja')
        ->not->toContain('PLOT_COACH_BATCH_PROPOSAL'); // sentinel must NOT be emitted
});

it('rejects when a beat references a wiki entry but wiki_entry_ids is empty', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $beat = Beat::factory()->for($plotPoint, 'plotPoint')->create([
        'title' => 'Voyager appears',
        'description' => 'The Voyager probe enters the gravitational lens.',
    ]);
    WikiEntry::factory()->for($book, 'book')->create(['name' => 'Voyager']);

    $tool = new ProposeChapterPlan($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'Slice 1',
        'chapters' => [
            ['title' => 'Opening', 'storyline_id' => $storyline->id, 'beat_ids' => [$beat->id]],
        ],
    ]));

    expect($result)
        ->toContain('wiki_entry_ids is empty')
        ->toContain('Voyager')
        ->not->toContain('PLOT_COACH_BATCH_PROPOSAL');
});

it('accepts when no entities are referenced (empty lists are valid)', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $beat = Beat::factory()->for($plotPoint, 'plotPoint')->create([
        'title' => 'Empty room',
        'description' => 'A nondescript empty room. No one is there.',
    ]);
    Character::factory()->for($book, 'book')->create(['name' => 'Maja']);
    WikiEntry::factory()->for($book, 'book')->create(['name' => 'Voyager']);

    $tool = new ProposeChapterPlan($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'Slice 1',
        'chapters' => [
            ['title' => 'Opening', 'storyline_id' => $storyline->id, 'beat_ids' => [$beat->id]],
        ],
    ]));

    expect($result)
        ->toContain('PLOT_COACH_BATCH_PROPOSAL')
        ->not->toContain('Chapter entity links missing');
});

it('accepts when entities are referenced and lists are populated (specific picks not enforced)', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $beat = Beat::factory()->for($plotPoint, 'plotPoint')->create([
        'title' => 'Maja boards',
        'description' => 'Maja boards the Voyager probe.',
    ]);
    $maja = Character::factory()->for($book, 'book')->create(['name' => 'Maja']);
    $john = Character::factory()->for($book, 'book')->create(['name' => 'John']); // not in beat
    $voyager = WikiEntry::factory()->for($book, 'book')->create(['name' => 'Voyager']);

    $tool = new ProposeChapterPlan($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'Slice 1',
        'chapters' => [[
            'title' => 'Opening',
            'storyline_id' => $storyline->id,
            'beat_ids' => [$beat->id],
            'character_ids' => [$john->id], // agent picked the "wrong" character — that's OK; existence check passes
            'wiki_entry_ids' => [$voyager->id],
        ]],
    ]));

    expect($result)
        ->toContain('PLOT_COACH_BATCH_PROPOSAL')
        ->not->toContain('Chapter entity links missing');
});

it('reports rejections for multiple chapters in one proposal', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $beat1 = Beat::factory()->for($plotPoint, 'plotPoint')->create([
        'title' => 'Maja boards',
        'description' => 'Maja boards the Voyager probe.',
    ]);
    $beat2 = Beat::factory()->for($plotPoint, 'plotPoint')->create([
        'title' => 'John waits',
        'description' => 'John waits in the lab.',
    ]);
    Character::factory()->for($book, 'book')->create(['name' => 'Maja']);
    Character::factory()->for($book, 'book')->create(['name' => 'John']);
    WikiEntry::factory()->for($book, 'book')->create(['name' => 'Voyager']);

    $tool = new ProposeChapterPlan($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'Slice 1',
        'chapters' => [
            ['title' => 'A', 'storyline_id' => $storyline->id, 'beat_ids' => [$beat1->id]],
            ['title' => 'B', 'storyline_id' => $storyline->id, 'beat_ids' => [$beat2->id]],
        ],
    ]));

    expect($result)
        ->toContain('Chapter "A" (index 0)')
        ->toContain('Chapter "B" (index 1)')
        ->toContain('Maja')
        ->toContain('John')
        ->toContain('Voyager');
});
