<?php

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Storyline;

test('can assign a chapter to an act', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $act = Act::factory()->for($book)->create(['sort_order' => 0, 'number' => 1]);
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['act_id' => null]);

    $response = $this->patchJson(route('chapters.assignAct', [$book, $chapter]), [
        'act_id' => $act->id,
    ]);

    $response->assertOk()->assertJson(['success' => true]);
    expect($chapter->fresh()->act_id)->toBe($act->id);
});

test('can unassign a chapter from an act', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $act = Act::factory()->for($book)->create(['sort_order' => 0, 'number' => 1]);
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['act_id' => $act->id]);

    $response = $this->patchJson(route('chapters.assignAct', [$book, $chapter]), [
        'act_id' => null,
    ]);

    $response->assertOk()->assertJson(['success' => true]);
    expect($chapter->fresh()->act_id)->toBeNull();
});

test('rejects act from a different book', function () {
    $book = Book::factory()->create();
    $otherBook = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $foreignAct = Act::factory()->for($otherBook)->create(['sort_order' => 0, 'number' => 1]);
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['act_id' => null]);

    $response = $this->patchJson(route('chapters.assignAct', [$book, $chapter]), [
        'act_id' => $foreignAct->id,
    ]);

    $response->assertUnprocessable();
    expect($chapter->fresh()->act_id)->toBeNull();
});

test('rejects non-existent act_id', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['act_id' => null]);

    $response = $this->patchJson(route('chapters.assignAct', [$book, $chapter]), [
        'act_id' => 99999,
    ]);

    $response->assertUnprocessable();
    expect($chapter->fresh()->act_id)->toBeNull();
});
