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
