<?php

use App\Ai\Tools\Plot\ProposeChapterPlan;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Storyline;
use Laravel\Ai\Tools\Request;

it('renders a markdown preview listing proposed chapters', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create(['name' => 'Main arc']);

    $tool = new ProposeChapterPlan;
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'summary' => 'Slice Act I',
        'chapters' => [
            ['title' => 'Opening', 'storyline_id' => $storyline->id, 'beat_ids' => [1, 2]],
            ['title' => 'Escalation', 'storyline_id' => $storyline->id],
        ],
    ]));

    expect($result)
        ->toContain('## Proposed chapter plan')
        ->toContain('Slice Act I')
        ->toContain('### Chapters')
        ->toContain('Opening')
        ->toContain('Escalation')
        ->toContain('2 chapters — awaiting approval.')
        ->toContain('<!-- PLOT_COACH_BATCH_PROPOSAL');
});

it('flags chapters that already exist on the same storyline as reuse', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();

    Chapter::factory()
        ->for($book, 'book')
        ->for($storyline, 'storyline')
        ->create(['title' => 'Opening']);

    $tool = new ProposeChapterPlan;
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'summary' => 'Re-propose',
        'chapters' => [
            ['title' => 'Opening', 'storyline_id' => $storyline->id],
        ],
    ]));

    expect($result)->toContain('existing — will reuse');
    expect($result)->toContain('1 already exist');
});

it('persists nothing', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();

    $before = Chapter::query()->count();

    (new ProposeChapterPlan)->handle(new Request([
        'book_id' => $book->id,
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

    $result = (string) (new ProposeChapterPlan)->handle(new Request([
        'book_id' => $book->id,
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

it('skips malformed chapter entries silently', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();

    $tool = new ProposeChapterPlan;
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'summary' => 'Mixed',
        'chapters' => [
            ['title' => '', 'storyline_id' => $storyline->id],
            ['title' => 'Valid', 'storyline_id' => $storyline->id],
            ['title' => 'No storyline'],
        ],
    ]));

    expect($result)->toContain('1 chapter — awaiting approval');
    expect($result)->toContain('Valid');
});

it('renders supporting character and wiki entry counts in the preview', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create(['name' => 'Main arc']);

    $tool = new ProposeChapterPlan;
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
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

    expect($result)
        ->toContain('Opening')
        ->toContain('2 beats')
        ->toContain('POV #7')
        ->toContain('2 supporting characters')
        ->toContain('1 wiki entry');
});

it('passes character_ids and wiki_entry_ids through to the sentinel writes', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();

    $tool = new ProposeChapterPlan;
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
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
