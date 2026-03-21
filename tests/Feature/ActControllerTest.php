<?php

use App\Models\Act;
use App\Models\Beat;
use App\Models\Book;
use App\Models\PlotPoint;

it('creates an act', function () {
    $book = Book::factory()->create();

    $response = $this->postJson(route('acts.store', $book), [
        'title' => 'The Setup',
        'number' => 1,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('acts', [
        'book_id' => $book->id,
        'title' => 'The Setup',
        'number' => 1,
    ]);
});

it('auto-increments sort_order on create', function () {
    $book = Book::factory()->create();
    Act::factory()->create(['book_id' => $book->id, 'sort_order' => 0]);

    $this->postJson(route('acts.store', $book), [
        'title' => 'Second act',
        'number' => 2,
    ])->assertRedirect();

    expect(Act::where('title', 'Second act')->first()->sort_order)->toBe(1);
});

it('requires title and number to create an act', function () {
    $book = Book::factory()->create();

    $this->postJson(route('acts.store', $book), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'number']);
});

it('updates an act', function () {
    $book = Book::factory()->create();
    $act = Act::factory()->create(['book_id' => $book->id, 'title' => 'Old title']);

    $this->patchJson(route('acts.update', [$book, $act]), [
        'title' => 'New title',
        'description' => 'Updated description',
    ])->assertRedirect();

    expect($act->fresh()->title)->toBe('New title')
        ->and($act->fresh()->description)->toBe('Updated description');
});

it('deletes an act', function () {
    $book = Book::factory()->create();
    $act = Act::factory()->create(['book_id' => $book->id]);

    $this->deleteJson(route('acts.destroy', [$book, $act]))
        ->assertRedirect();

    $this->assertDatabaseMissing('acts', ['id' => $act->id]);
});

it('cascade deletes plot points when act is deleted', function () {
    $book = Book::factory()->create();
    $act = Act::factory()->create(['book_id' => $book->id]);
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id, 'act_id' => $act->id]);

    $this->deleteJson(route('acts.destroy', [$book, $act]))->assertRedirect();

    $this->assertDatabaseMissing('plot_points', ['id' => $plotPoint->id]);
});

it('cascade deletes beats when act is deleted', function () {
    $book = Book::factory()->create();
    $act = Act::factory()->create(['book_id' => $book->id]);
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id, 'act_id' => $act->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $plotPoint->id]);

    $this->deleteJson(route('acts.destroy', [$book, $act]))->assertRedirect();

    $this->assertDatabaseMissing('beats', ['id' => $beat->id]);
    $this->assertDatabaseMissing('plot_points', ['id' => $plotPoint->id]);
});

it('does not delete plot points from other acts', function () {
    $book = Book::factory()->create();
    $act1 = Act::factory()->create(['book_id' => $book->id]);
    $act2 = Act::factory()->create(['book_id' => $book->id]);
    $plotPoint1 = PlotPoint::factory()->create(['book_id' => $book->id, 'act_id' => $act1->id]);
    $plotPoint2 = PlotPoint::factory()->create(['book_id' => $book->id, 'act_id' => $act2->id]);

    $this->deleteJson(route('acts.destroy', [$book, $act1]))->assertRedirect();

    $this->assertDatabaseMissing('plot_points', ['id' => $plotPoint1->id]);
    $this->assertDatabaseHas('plot_points', ['id' => $plotPoint2->id]);
});

it('rejects updating an act from another book', function () {
    $book = Book::factory()->create();
    $otherBook = Book::factory()->create();
    $act = Act::factory()->create(['book_id' => $otherBook->id]);

    $this->patchJson(route('acts.update', [$book, $act]), [
        'title' => 'Hacked',
    ])->assertNotFound();
});

it('rejects deleting an act from another book', function () {
    $book = Book::factory()->create();
    $otherBook = Book::factory()->create();
    $act = Act::factory()->create(['book_id' => $otherBook->id]);

    $this->deleteJson(route('acts.destroy', [$book, $act]))
        ->assertNotFound();
});
