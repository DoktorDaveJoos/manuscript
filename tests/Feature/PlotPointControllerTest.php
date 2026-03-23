<?php

use App\Enums\PlotPointStatus;
use App\Models\Act;
use App\Models\Book;
use App\Models\Character;
use App\Models\License;
use App\Models\PlotPoint;

beforeEach(fn () => License::factory()->create());

it('creates a plot point', function () {
    $book = Book::factory()->create();
    $act = Act::factory()->create(['book_id' => $book->id]);

    $this->post(route('plotPoints.store', $book), [
        'title' => 'The reveal',
        'description' => 'Jonas discovers the truth',
        'type' => 'turning_point',
        'act_id' => $act->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('plot_points', [
        'book_id' => $book->id,
        'title' => 'The reveal',
        'type' => 'turning_point',
    ]);
});

it('updates a plot point', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id, 'title' => 'Old title']);

    $this->patch(route('plotPoints.update', [$book, $plotPoint]), [
        'title' => 'New title',
        'status' => 'fulfilled',
    ])->assertRedirect();

    expect($plotPoint->fresh()->title)->toBe('New title')
        ->and($plotPoint->fresh()->status)->toBe(PlotPointStatus::Fulfilled);
});

it('deletes a plot point', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);

    $this->delete(route('plotPoints.destroy', [$book, $plotPoint]))
        ->assertRedirect();

    $this->assertDatabaseMissing('plot_points', ['id' => $plotPoint->id]);
});

it('reorders plot points', function () {
    $book = Book::factory()->create();

    $a = PlotPoint::factory()->create(['book_id' => $book->id, 'sort_order' => 0]);
    $b = PlotPoint::factory()->create(['book_id' => $book->id, 'sort_order' => 1]);

    $this->post(route('plotPoints.reorder', $book), [
        'items' => [
            ['id' => $a->id, 'sort_order' => 1],
            ['id' => $b->id, 'sort_order' => 0],
        ],
    ])->assertRedirect();

    expect($a->fresh()->sort_order)->toBe(1)
        ->and($b->fresh()->sort_order)->toBe(0);
});

it('reorders plot points with act_id change', function () {
    $book = Book::factory()->create();
    $actA = Act::factory()->create(['book_id' => $book->id]);
    $actB = Act::factory()->create(['book_id' => $book->id]);

    $pp = PlotPoint::factory()->create(['book_id' => $book->id, 'act_id' => $actA->id, 'sort_order' => 0]);

    $this->post(route('plotPoints.reorder', $book), [
        'items' => [
            ['id' => $pp->id, 'sort_order' => 0, 'act_id' => $actB->id],
        ],
    ])->assertRedirect();

    $pp->refresh();
    expect($pp->act_id)->toBe($actB->id)
        ->and($pp->sort_order)->toBe(0);
});

it('reorder preserves act_id when not provided', function () {
    $book = Book::factory()->create();
    $act = Act::factory()->create(['book_id' => $book->id]);
    $pp = PlotPoint::factory()->create(['book_id' => $book->id, 'act_id' => $act->id, 'sort_order' => 0]);

    $this->post(route('plotPoints.reorder', $book), [
        'items' => [
            ['id' => $pp->id, 'sort_order' => 2],
        ],
    ])->assertRedirect();

    $pp->refresh();
    expect($pp->act_id)->toBe($act->id)
        ->and($pp->sort_order)->toBe(2);
});

it('reorder rejects act_id from another book', function () {
    $book = Book::factory()->create();
    $otherBook = Book::factory()->create();
    $otherAct = Act::factory()->create(['book_id' => $otherBook->id]);
    $pp = PlotPoint::factory()->create(['book_id' => $book->id, 'sort_order' => 0]);

    $this->postJson(route('plotPoints.reorder', $book), [
        'items' => [
            ['id' => $pp->id, 'sort_order' => 0, 'act_id' => $otherAct->id],
        ],
    ])->assertUnprocessable();
});

it('cycles plot point status', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id, 'status' => PlotPointStatus::Planned]);

    $this->patch(route('plotPoints.updateStatus', [$book, $plotPoint]), [
        'status' => 'fulfilled',
    ])->assertRedirect();

    expect($plotPoint->fresh()->status)->toBe(PlotPointStatus::Fulfilled);
});

it('can attach characters to a plot point', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $character = Character::factory()->create(['book_id' => $book->id]);

    $plotPoint->characters()->attach($character->id, ['role' => 'key']);

    expect($plotPoint->characters)->toHaveCount(1)
        ->and($plotPoint->characters->first()->id)->toBe($character->id)
        ->and($plotPoint->characters->first()->pivot->role)->toBe('key');
});

it('syncs characters when updating a plot point', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $char1 = Character::factory()->create(['book_id' => $book->id]);
    $char2 = Character::factory()->create(['book_id' => $book->id]);

    $this->patch(route('plotPoints.update', [$book, $plotPoint]), [
        'characters' => [
            ['id' => $char1->id, 'role' => 'key'],
            ['id' => $char2->id, 'role' => 'supporting'],
        ],
    ])->assertRedirect();

    $plotPoint->refresh();
    expect($plotPoint->characters)->toHaveCount(2);
    expect($plotPoint->characters->firstWhere('id', $char1->id)->pivot->role)->toBe('key');
    expect($plotPoint->characters->firstWhere('id', $char2->id)->pivot->role)->toBe('supporting');
});

it('replaces previous characters on sync', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $char1 = Character::factory()->create(['book_id' => $book->id]);
    $char2 = Character::factory()->create(['book_id' => $book->id]);

    $plotPoint->characters()->attach($char1->id, ['role' => 'key']);

    $this->patch(route('plotPoints.update', [$book, $plotPoint]), [
        'characters' => [
            ['id' => $char2->id, 'role' => 'mentioned'],
        ],
    ])->assertRedirect();

    $plotPoint->refresh();
    expect($plotPoint->characters)->toHaveCount(1)
        ->and($plotPoint->characters->first()->id)->toBe($char2->id);
});

it('detaches all characters with empty array', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $char = Character::factory()->create(['book_id' => $book->id]);

    $plotPoint->characters()->attach($char->id, ['role' => 'key']);

    $this->patch(route('plotPoints.update', [$book, $plotPoint]), [
        'characters' => [],
    ])->assertRedirect();

    expect($plotPoint->fresh()->characters)->toHaveCount(0);
});

it('rejects characters from another book', function () {
    $book = Book::factory()->create();
    $otherBook = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $otherChar = Character::factory()->create(['book_id' => $otherBook->id]);

    $this->patchJson(route('plotPoints.update', [$book, $plotPoint]), [
        'characters' => [
            ['id' => $otherChar->id, 'role' => 'key'],
        ],
    ])->assertUnprocessable();
});

it('rejects invalid character role', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $char = Character::factory()->create(['book_id' => $book->id]);

    $this->patchJson(route('plotPoints.update', [$book, $plotPoint]), [
        'characters' => [
            ['id' => $char->id, 'role' => 'villain'],
        ],
    ])->assertUnprocessable();
});

it('rejects non-existent character ids', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);

    $this->patchJson(route('plotPoints.update', [$book, $plotPoint]), [
        'characters' => [
            ['id' => 99999, 'role' => 'key'],
        ],
    ])->assertUnprocessable();
});
