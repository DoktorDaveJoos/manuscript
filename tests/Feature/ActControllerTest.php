<?php

use App\Models\Act;
use App\Models\Book;

it('creates an act under a book', function () {
    $book = Book::factory()->create();

    $response = $this->post(route('acts.store', $book), [
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

    $this->post(route('acts.store', $book), [
        'title' => 'Second Act',
        'number' => 2,
    ])->assertRedirect();

    $act = Act::query()->where('book_id', $book->id)->where('title', 'Second Act')->first();
    expect($act->sort_order)->toBe(1);
});

it('requires a title and number to create an act', function () {
    $book = Book::factory()->create();

    $this->postJson(route('acts.store', $book), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'number']);
});

it('updates an act', function () {
    $book = Book::factory()->create();
    $act = Act::factory()->create(['book_id' => $book->id, 'title' => 'Old title']);

    $this->patch(route('acts.update', [$book, $act]), [
        'title' => 'New title',
        'description' => 'Updated description',
    ])->assertRedirect();

    expect($act->fresh()->title)->toBe('New title')
        ->and($act->fresh()->description)->toBe('Updated description');
});

it('deletes an act', function () {
    $book = Book::factory()->create();
    $act = Act::factory()->create(['book_id' => $book->id]);

    $this->delete(route('acts.destroy', [$book, $act]))
        ->assertRedirect();

    $this->assertDatabaseMissing('acts', ['id' => $act->id]);
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
