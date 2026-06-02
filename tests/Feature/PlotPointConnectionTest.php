<?php

use App\Enums\ConnectionType;
use App\Models\Book;
use App\Models\PlotPoint;
use App\Models\PlotPointConnection;
use Illuminate\Database\UniqueConstraintViolationException;

it('creates a connection between two plot points', function () {
    $book = Book::factory()->create();
    $source = PlotPoint::factory()->create(['book_id' => $book->id]);
    $target = PlotPoint::factory()->create(['book_id' => $book->id]);

    $connection = PlotPointConnection::create([
        'book_id' => $book->id,
        'source_plot_point_id' => $source->id,
        'target_plot_point_id' => $target->id,
        'type' => ConnectionType::SetsUp,
        'description' => 'Foreshadowing',
    ]);

    expect($connection->source->id)->toBe($source->id)
        ->and($connection->target->id)->toBe($target->id)
        ->and($connection->type)->toBe(ConnectionType::SetsUp);
});

it('loads outgoing and incoming connections on a plot point', function () {
    $book = Book::factory()->create();
    $a = PlotPoint::factory()->create(['book_id' => $book->id]);
    $b = PlotPoint::factory()->create(['book_id' => $book->id]);
    $c = PlotPoint::factory()->create(['book_id' => $book->id]);

    PlotPointConnection::create([
        'book_id' => $book->id,
        'source_plot_point_id' => $a->id,
        'target_plot_point_id' => $b->id,
        'type' => ConnectionType::Causes,
    ]);
    PlotPointConnection::create([
        'book_id' => $book->id,
        'source_plot_point_id' => $c->id,
        'target_plot_point_id' => $a->id,
        'type' => ConnectionType::SetsUp,
    ]);

    $a->load(['outgoingConnections', 'incomingConnections']);

    expect($a->outgoingConnections)->toHaveCount(1)
        ->and($a->incomingConnections)->toHaveCount(1);
});

it('prevents duplicate connections', function () {
    $book = Book::factory()->create();
    $source = PlotPoint::factory()->create(['book_id' => $book->id]);
    $target = PlotPoint::factory()->create(['book_id' => $book->id]);

    PlotPointConnection::create([
        'book_id' => $book->id,
        'source_plot_point_id' => $source->id,
        'target_plot_point_id' => $target->id,
        'type' => ConnectionType::Causes,
    ]);

    PlotPointConnection::create([
        'book_id' => $book->id,
        'source_plot_point_id' => $source->id,
        'target_plot_point_id' => $target->id,
        'type' => ConnectionType::Resolves,
    ]);
})->throws(UniqueConstraintViolationException::class);

it('cascades delete when plot point is removed', function () {
    $book = Book::factory()->create();
    $source = PlotPoint::factory()->create(['book_id' => $book->id]);
    $target = PlotPoint::factory()->create(['book_id' => $book->id]);

    PlotPointConnection::create([
        'book_id' => $book->id,
        'source_plot_point_id' => $source->id,
        'target_plot_point_id' => $target->id,
        'type' => ConnectionType::Causes,
    ]);

    $source->forceDelete();

    expect(PlotPointConnection::count())->toBe(0);
});
