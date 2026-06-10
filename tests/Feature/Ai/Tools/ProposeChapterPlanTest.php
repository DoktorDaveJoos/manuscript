<?php

use App\Ai\Tools\Plot\ProposeChapterPlan;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\PlotCoachProposal;
use App\Models\PlotCoachSession;
use App\Models\Storyline;
use Laravel\Ai\Tools\Request;

it('returns a compact preview with summary, chapter count, and sentinel', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create(['name' => 'Main arc']);

    $tool = new ProposeChapterPlan($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'Slice Act I',
        'chapters' => [
            ['title' => 'Opening', 'storyline_id' => $storyline->id, 'beat_ids' => [1, 2]],
            ['title' => 'Escalation', 'storyline_id' => $storyline->id],
        ],
    ]));

    // Compact on purpose: the chapters appear once, in the sentinel JSON the
    // card is rendered from — no second per-chapter markdown copy.
    expect($result)
        ->toContain('## Proposed chapter plan')
        ->toContain('Slice Act I')
        ->toContain('Opening')
        ->toContain('Escalation')
        ->toContain('2 chapters — awaiting approval.')
        ->toContain('<!-- PLOT_COACH_BATCH_PROPOSAL')
        ->not->toContain('### Chapters');
});

it('flags chapters that already exist on the same storyline as reuse', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();

    Chapter::factory()
        ->for($book, 'book')
        ->for($storyline, 'storyline')
        ->create(['title' => 'Opening']);

    $tool = new ProposeChapterPlan($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'Re-propose',
        'chapters' => [
            ['title' => 'Opening', 'storyline_id' => $storyline->id],
        ],
    ]));

    expect($result)->toContain('will be reused');
    expect($result)->toContain('1 already exist');
    expect($result)->toContain('Opening');
});

it('persists nothing', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();

    $before = Chapter::query()->count();

    (new ProposeChapterPlan($book->id))->handle(new Request([
        'summary' => 'Preview',
        'chapters' => [
            ['title' => 'Does not save', 'storyline_id' => $storyline->id],
        ],
    ]));

    expect(Chapter::query()->count())->toBe($before);
});

it('returns an explicit parse error when chapters is a malformed JSON string', function () {
    $book = Book::factory()->create();

    $malformed = '[{"title":"Opening","description":"Says: **"hello."**"}]';

    $result = (string) (new ProposeChapterPlan($book->id))->handle(new Request([
        'summary' => 'malformed',
        'chapters' => $malformed,
    ]));

    expect($result)
        ->toContain('Chapter plan failed')
        ->toContain('chapters')
        ->toContain('JSON')
        ->not->toContain('Chapter plan preview: (empty)')
        ->not->toContain('PLOT_COACH_BATCH_PROPOSAL');
});

it('rejects malformed chapter entries loudly instead of silently dropping them', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    PlotCoachSession::factory()->for($book, 'book')->create();

    $tool = new ProposeChapterPlan($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'Mixed',
        'chapters' => [
            ['title' => '', 'storyline_id' => $storyline->id],
            ['title' => 'Valid', 'storyline_id' => $storyline->id],
            ['title' => 'No storyline'],
        ],
    ]));

    expect($result)
        ->toContain('Chapter plan rejected')
        ->toContain('title')
        ->toContain('storyline')
        ->not->toContain('PLOT_COACH_BATCH_PROPOSAL');

    expect(PlotCoachProposal::query()->count())->toBe(0);
});

it('carries full chapter wiring through to the sentinel writes', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create(['name' => 'Main arc']);

    $tool = new ProposeChapterPlan($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'Wired stub',
        'chapters' => [
            [
                'title' => 'Opening',
                'storyline_id' => $storyline->id,
                'beat_ids' => [10, 11],
                'pov_character_id' => 7,
                'character_ids' => [8, 9],
                'wiki_entry_ids' => [42],
            ],
        ],
    ]));

    preg_match('/<!-- PLOT_COACH_BATCH_PROPOSAL\n(.+?)\n-->/s', $result, $matches);
    $payload = json_decode($matches[1], true);

    expect($payload['writes'][0]['data'])->toMatchArray([
        'title' => 'Opening',
        'storyline_id' => $storyline->id,
        'beat_ids' => [10, 11],
        'pov_character_id' => 7,
        'character_ids' => [8, 9],
        'wiki_entry_ids' => [42],
    ]);
});

it('passes character_ids and wiki_entry_ids through to the sentinel writes', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();

    $tool = new ProposeChapterPlan($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'Stash',
        'chapters' => [
            [
                'title' => 'Opening',
                'storyline_id' => $storyline->id,
                'character_ids' => [4, 5],
                'wiki_entry_ids' => [11],
            ],
        ],
    ]));

    preg_match('/<!-- PLOT_COACH_BATCH_PROPOSAL\n(.+?)\n-->/s', $result, $matches);
    $payload = json_decode($matches[1], true);

    expect($payload['writes'][0]['data']['character_ids'])->toBe([4, 5]);
    expect($payload['writes'][0]['data']['wiki_entry_ids'])->toBe([11]);
});

it('binds the book at construction so no payload book_id is needed', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $tool = new ProposeChapterPlan($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'One chapter',
        'chapters' => [
            ['title' => 'Opening', 'storyline_id' => $storyline->id],
        ],
    ]));

    expect($result)->toContain('## Proposed chapter plan');
    expect(PlotCoachProposal::query()->where('session_id', $session->id)->count())->toBe(1);
});

it('accepts storyline_name as a fallback when the storyline is created in the same batch', function () {
    $book = Book::factory()->create();
    PlotCoachSession::factory()->for($book, 'book')->create();

    $tool = new ProposeChapterPlan($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'New storyline + chapter',
        'chapters' => [
            ['title' => 'Opening', 'storyline_name' => 'Main arc'],
        ],
    ]));

    expect($result)
        ->toContain('## Proposed chapter plan')
        ->toContain('Opening')
        ->toContain('<!-- PLOT_COACH_BATCH_PROPOSAL');

    preg_match('/<!-- PLOT_COACH_BATCH_PROPOSAL\n(.+?)\n-->/s', $result, $matches);
    $payload = json_decode($matches[1], true);

    expect($payload['writes'][0]['data']['storyline_name'])->toBe('Main arc');
});
