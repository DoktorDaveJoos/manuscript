<?php

use App\Enums\ConnectionType;
use App\Models\Book;
use App\Models\PlotPoint;
use App\Models\PlotPointConnection;

it('creates a connection between plot points', function () {
    $book = Book::factory()->create();
    $source = PlotPoint::factory()->create(['book_id' => $book->id]);
    $target = PlotPoint::factory()->create(['book_id' => $book->id]);

    $response = $this->postJson(route('plotConnections.store', $book), [
        'source_plot_point_id' => $source->id,
        'target_plot_point_id' => $target->id,
        'type' => 'sets_up',
        'description' => 'Foreshadows the twist',
    ]);

    $response->assertCreated()
        ->assertJsonPath('type', 'sets_up');

    $this->assertDatabaseHas('plot_point_connections', [
        'book_id' => $book->id,
        'source_plot_point_id' => $source->id,
    ]);
});

it('rejects self-connection', function () {
    $book = Book::factory()->create();
    $point = PlotPoint::factory()->create(['book_id' => $book->id]);

    $this->postJson(route('plotConnections.store', $book), [
        'source_plot_point_id' => $point->id,
        'target_plot_point_id' => $point->id,
        'type' => 'causes',
    ])->assertUnprocessable();
});

it('deletes a connection', function () {
    $book = Book::factory()->create();
    $source = PlotPoint::factory()->create(['book_id' => $book->id]);
    $target = PlotPoint::factory()->create(['book_id' => $book->id]);
    $connection = PlotPointConnection::create([
        'book_id' => $book->id,
        'source_plot_point_id' => $source->id,
        'target_plot_point_id' => $target->id,
        'type' => ConnectionType::Causes,
    ]);

    $this->deleteJson(route('plotConnections.destroy', [$book, $connection]))
        ->assertNoContent();

    $this->assertDatabaseMissing('plot_point_connections', ['id' => $connection->id]);
});
