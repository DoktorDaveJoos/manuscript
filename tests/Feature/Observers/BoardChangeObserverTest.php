<?php

use App\Models\Act;
use App\Models\Beat;
use App\Models\Book;
use App\Models\PlotCoachSession;
use App\Models\PlotPoint;
use App\Models\Storyline;
use App\Observers\BoardChangeObserver;

it('queues a created entry when a plot point is created', function () {
    $book = Book::factory()->create();
    $act = Act::factory()->for($book, 'book')->create();

    // Create the session AFTER the act so the act's creation event does not
    // append to the queue (there was no active session when it was created).
    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'pending_board_changes' => [],
    ]);

    PlotPoint::factory()->for($book, 'book')->create([
        'act_id' => $act->id,
        'title' => 'Catalyst',
    ]);

    $session->refresh();

    expect($session->pending_board_changes)->toHaveCount(1);
    expect($session->pending_board_changes[0])->toMatchArray([
        'kind' => 'created',
        'type' => 'plot_point',
    ]);
    expect($session->pending_board_changes[0]['summary'])->toContain('Catalyst');
});

it('queues an updated entry when a beat is updated', function () {
    $book = Book::factory()->create();
    $act = Act::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create(['act_id' => $act->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $plotPoint->id, 'title' => 'Original']);

    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'pending_board_changes' => [],
    ]);

    $beat->update(['title' => 'Revised']);

    $session->refresh();

    expect($session->pending_board_changes)->toHaveCount(1);
    expect($session->pending_board_changes[0]['kind'])->toBe('updated');
    expect($session->pending_board_changes[0]['type'])->toBe('beat');
    expect($session->pending_board_changes[0]['summary'])->toContain('Revised');
});

it('queues a deleted entry when a storyline is deleted', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create(['name' => 'Resistance arc']);

    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'pending_board_changes' => [],
    ]);

    $storyline->delete();

    $session->refresh();

    expect($session->pending_board_changes)->toHaveCount(1);
    expect($session->pending_board_changes[0]['kind'])->toBe('deleted');
    expect($session->pending_board_changes[0]['type'])->toBe('storyline');
    expect($session->pending_board_changes[0]['summary'])->toContain('Resistance arc');
});

it('is a no-op when no active session exists for the book', function () {
    $book = Book::factory()->create();
    $act = Act::factory()->for($book, 'book')->create();

    // No session created for this book.
    PlotPoint::factory()->for($book, 'book')->create(['act_id' => $act->id]);

    expect(PlotCoachSession::query()->where('book_id', $book->id)->count())->toBe(0);
});

it('does not queue when an archived session is the only one', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->archived()->create([
        'pending_board_changes' => [],
    ]);
    $act = Act::factory()->for($book, 'book')->create();

    PlotPoint::factory()->for($book, 'book')->create(['act_id' => $act->id]);

    $session->refresh();

    expect($session->pending_board_changes)->toBe([]);
});

it('suppresses queuing during a batch service apply', function () {
    $book = Book::factory()->create();
    $act = Act::factory()->for($book, 'book')->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'pending_board_changes' => [],
    ]);

    BoardChangeObserver::suppress(function () use ($book, $act) {
        PlotPoint::factory()->for($book, 'book')->create(['act_id' => $act->id]);
    });

    $session->refresh();

    expect($session->pending_board_changes)->toBe([]);
});

it('queues only against the session that belongs to the mutated book', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    $actA = Act::factory()->for($bookA, 'book')->create();

    // Create sessions AFTER the act so its creation does not pre-populate the
    // queue.
    $sessionA = PlotCoachSession::factory()->for($bookA, 'book')->create(['pending_board_changes' => []]);
    $sessionB = PlotCoachSession::factory()->for($bookB, 'book')->create(['pending_board_changes' => []]);

    PlotPoint::factory()->for($bookA, 'book')->create(['act_id' => $actA->id]);

    $sessionA->refresh();
    $sessionB->refresh();

    expect($sessionA->pending_board_changes)->toHaveCount(1);
    expect($sessionB->pending_board_changes)->toBe([]);
});
