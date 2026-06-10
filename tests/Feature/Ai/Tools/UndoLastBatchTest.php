<?php

use App\Ai\Tools\Plot\ApplyPlotCoachBatch;
use App\Ai\Tools\Plot\UndoLastBatch;
use App\Models\Book;
use App\Models\Character;
use App\Models\PlotCoachBatch;
use App\Models\PlotCoachSession;
use App\Services\PlotCoachBatchService;
use Laravel\Ai\Tools\Request;

it('delegates to the batch service and returns undo confirmation', function () {
    $book = Book::factory()->create();
    PlotCoachSession::factory()->for($book, 'book')->create();

    // Apply something first.
    (new ApplyPlotCoachBatch($book->id))->handle(new Request([
        'summary' => 'Add Rae',
        'writes' => [['type' => 'character', 'data' => ['name' => 'Rae']]],
    ]));

    expect(Character::query()->where('book_id', $book->id)->count())->toBe(1);

    $tool = new UndoLastBatch($book->id);
    $result = (string) $tool->handle(new Request([]));

    expect($result)
        ->toContain('Undone batch #')
        ->toContain('Add Rae');
    expect(Character::query()->where('book_id', $book->id)->count())->toBe(0);
});

it('returns a no-op message when nothing to undo', function () {
    $book = Book::factory()->create();
    PlotCoachSession::factory()->for($book, 'book')->create();

    $tool = new UndoLastBatch($book->id);
    $result = (string) $tool->handle(new Request([]));

    expect($result)->toBe('No batch to undo in this session.');
});

it('returns a failure message when no active session exists', function () {
    $book = Book::factory()->create();

    $tool = new UndoLastBatch($book->id);
    $result = (string) $tool->handle(new Request([]));

    expect($result)->toContain('no active plot coach session');
});

it('undoes the bound session\'s batch, not the active session\'s', function () {
    $book = Book::factory()->create();
    $boundSession = PlotCoachSession::factory()->for($book, 'book')->archived()->create();
    $activeSession = PlotCoachSession::factory()->for($book, 'book')->create();

    $service = new PlotCoachBatchService;
    $boundBatch = $service->apply($boundSession, [['type' => 'character', 'data' => ['name' => 'Bound-only']]], 'Bound write');
    $service->apply($activeSession, [['type' => 'character', 'data' => ['name' => 'Active-only']]], 'Active write');

    $tool = new UndoLastBatch($book->id, session: $boundSession);
    $result = (string) $tool->handle(new Request([]));

    expect($result)->toContain("Undone batch #{$boundBatch->id}");
    expect(Character::query()->where('book_id', $book->id)->where('name', 'Bound-only')->exists())->toBeFalse();
    expect(Character::query()->where('book_id', $book->id)->where('name', 'Active-only')->exists())->toBeTrue();
});

it('refuses to undo a batch past its 30-minute undo window', function () {
    $book = Book::factory()->create();
    PlotCoachSession::factory()->for($book, 'book')->create();

    (new ApplyPlotCoachBatch($book->id))->handle(new Request([
        'summary' => 'Add Rae',
        'writes' => [['type' => 'character', 'data' => ['name' => 'Rae']]],
    ]));

    PlotCoachBatch::query()->update(['undo_window_expires_at' => now()->subMinute()]);

    $result = (string) (new UndoLastBatch($book->id))->handle(new Request([]));

    expect($result)->toContain('expired');
    expect(Character::query()->where('book_id', $book->id)->count())->toBe(1);
});
