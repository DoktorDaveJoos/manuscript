<?php

use App\Ai\Tools\Plot\ApplyPlotCoachBatch;
use App\Models\Book;
use App\Models\Character;
use App\Models\PlotCoachBatch;
use App\Models\PlotCoachSession;
use Laravel\Ai\Tools\Request;

it('invokes the batch service and returns a confirmation string', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $tool = new ApplyPlotCoachBatch;
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'summary' => 'Add Mara',
        'writes' => [
            ['type' => 'character', 'data' => ['name' => 'Mara']],
        ],
    ]));

    expect($result)
        ->toContain('Applied batch #')
        ->toContain('Add Mara')
        ->toContain('1 item written.');

    expect(Character::query()->where('book_id', $book->id)->count())->toBe(1);
    expect(PlotCoachBatch::query()->where('session_id', $session->id)->count())->toBe(1);
});

it('returns a failure message when the batch throws', function () {
    $book = Book::factory()->create();
    PlotCoachSession::factory()->for($book, 'book')->create();

    $tool = new ApplyPlotCoachBatch;
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'summary' => 'Bad batch',
        'writes' => [
            // Missing act_id — must roll back.
            ['type' => 'plot_point', 'data' => ['title' => 'Orphan']],
        ],
    ]));

    expect($result)->toContain('Batch failed:');
    expect($result)->toContain('Nothing persisted');
    expect(PlotCoachBatch::query()->count())->toBe(0);
});

it('returns a failure message when no active session exists for the book', function () {
    $book = Book::factory()->create();

    $tool = new ApplyPlotCoachBatch;
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'summary' => 'No session',
        'writes' => [['type' => 'character', 'data' => ['name' => 'Mara']]],
    ]));

    expect($result)->toContain('no active plot coach session');
});
