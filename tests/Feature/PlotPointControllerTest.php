<?php

use App\Enums\PlotPointStatus;
use App\Models\Act;
use App\Models\Book;
use App\Models\PlotPoint;

it('creates a plot point', function () {
    $book = Book::factory()->create();
    $act = Act::factory()->create(['book_id' => $book->id]);

    $response = $this->postJson(route('plotPoints.store', $book), [
        'title' => 'The reveal',
        'description' => 'Jonas discovers the truth',
        'type' => 'turning_point',
        'act_id' => $act->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('title', 'The reveal')
        ->assertJsonPath('type', 'turning_point');

    $this->assertDatabaseHas('plot_points', [
        'book_id' => $book->id,
        'title' => 'The reveal',
    ]);
});

it('updates a plot point', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id, 'title' => 'Old title']);

    $response = $this->patchJson(route('plotPoints.update', [$book, $plotPoint]), [
        'title' => 'New title',
        'status' => 'fulfilled',
    ]);

    $response->assertOk()->assertJsonPath('title', 'New title');
    expect($plotPoint->fresh()->status)->toBe(PlotPointStatus::Fulfilled);
});

it('deletes a plot point', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);

    $this->deleteJson(route('plotPoints.destroy', [$book, $plotPoint]))
        ->assertNoContent();

    $this->assertDatabaseMissing('plot_points', ['id' => $plotPoint->id]);
});

it('reorders plot points', function () {
    $book = Book::factory()->create();

    $a = PlotPoint::factory()->create(['book_id' => $book->id, 'sort_order' => 0]);
    $b = PlotPoint::factory()->create(['book_id' => $book->id, 'sort_order' => 1]);

    $response = $this->postJson(route('plotPoints.reorder', $book), [
        'items' => [
            ['id' => $a->id, 'sort_order' => 1],
            ['id' => $b->id, 'sort_order' => 0],
        ],
    ]);

    $response->assertOk();
    expect($a->fresh()->sort_order)->toBe(1)
        ->and($b->fresh()->sort_order)->toBe(0);
});

it('cycles plot point status', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id, 'status' => PlotPointStatus::Planned]);

    $this->patchJson(route('plotPoints.updateStatus', [$book, $plotPoint]), [
        'status' => 'fulfilled',
    ])->assertOk();

    expect($plotPoint->fresh()->status)->toBe(PlotPointStatus::Fulfilled);
});
