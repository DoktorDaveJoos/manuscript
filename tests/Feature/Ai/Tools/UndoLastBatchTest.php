<?php

use App\Ai\Tools\Plot\ApplyPlotCoachBatch;
use App\Ai\Tools\Plot\UndoLastBatch;
use App\Models\Book;
use App\Models\Character;
use App\Models\PlotCoachSession;
use Laravel\Ai\Tools\Request;

it('delegates to the batch service and returns undo confirmation', function () {
    $book = Book::factory()->create();
    PlotCoachSession::factory()->for($book, 'book')->create();

    // Apply something first.
    (new ApplyPlotCoachBatch)->handle(new Request([
        'book_id' => $book->id,
        'summary' => 'Add Rae',
        'writes' => [['type' => 'character', 'data' => ['name' => 'Rae']]],
    ]));

    expect(Character::query()->where('book_id', $book->id)->count())->toBe(1);

    $tool = new UndoLastBatch;
    $result = (string) $tool->handle(new Request(['book_id' => $book->id]));

    expect($result)
        ->toContain('Undone batch #')
        ->toContain('Add Rae');
    expect(Character::query()->where('book_id', $book->id)->count())->toBe(0);
});

it('returns a no-op message when nothing to undo', function () {
    $book = Book::factory()->create();
    PlotCoachSession::factory()->for($book, 'book')->create();

    $tool = new UndoLastBatch;
    $result = (string) $tool->handle(new Request(['book_id' => $book->id]));

    expect($result)->toBe('No batch to undo in this session.');
});

it('returns a failure message when no active session exists', function () {
    $book = Book::factory()->create();

    $tool = new UndoLastBatch;
    $result = (string) $tool->handle(new Request(['book_id' => $book->id]));

    expect($result)->toContain('no active plot coach session');
});
