<?php

use App\Models\Act;
use App\Models\Book;
use App\Models\License;
use App\Models\PlotPoint;
use App\Models\Storyline;

test('free user cannot access plot board', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->get(route('books.plot', $book))
        ->assertRedirect(route('settings.index'));
});

test('free user cannot create acts', function () {
    $book = Book::factory()->create();

    $this->postJson(route('acts.store', $book), [
        'title' => 'Act 1',
        'number' => 1,
    ])->assertForbidden();
});

test('free user cannot create plot points', function () {
    $book = Book::factory()->create();
    $act = Act::factory()->for($book)->create();

    $this->postJson(route('plotPoints.store', $book), [
        'act_id' => $act->id,
        'title' => 'Inciting Incident',
        'type' => 'turning_point',
    ])->assertForbidden();
});

test('free user cannot create beats', function () {
    $book = Book::factory()->create();
    $act = Act::factory()->for($book)->create();
    $plotPoint = PlotPoint::factory()->for($book)->for($act)->create();

    $this->postJson(route('beats.store', [$book, $plotPoint]), [
        'title' => 'A beat',
    ])->assertForbidden();
});

test('pro user can access plot board', function () {
    License::factory()->create();
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->get(route('books.plot', $book))
        ->assertSuccessful();
});

test('pro user can create acts', function () {
    License::factory()->create();
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->post(route('acts.store', $book), [
        'title' => 'Act 1',
        'number' => 1,
    ])->assertRedirect();

    expect($book->acts()->count())->toBe(1);
});
