<?php

use App\Enums\PlotCoachSessionStatus;
use App\Enums\PlotCoachStage;
use App\Models\Book;
use App\Models\PlotCoachSession;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('can be created with factory', function () {
    $session = PlotCoachSession::factory()->create();

    expect($session->exists)->toBeTrue();
    expect(PlotCoachSession::query()->find($session->id))->not->toBeNull();
});

it('casts status to enum', function () {
    $session = PlotCoachSession::factory()->create([
        'status' => PlotCoachSessionStatus::Active,
    ]);

    expect($session->fresh()->status)->toBe(PlotCoachSessionStatus::Active);
});

it('casts stage to enum', function () {
    $session = PlotCoachSession::factory()->create([
        'stage' => PlotCoachStage::Structure,
    ]);

    expect($session->fresh()->stage)->toBe(PlotCoachStage::Structure);
});

it('casts decisions to array', function () {
    $decisions = ['genre' => 'fantasy', 'premise' => 'test'];

    $session = PlotCoachSession::factory()->create([
        'decisions' => $decisions,
    ]);

    expect($session->fresh()->decisions)->toBe($decisions);
});

it('casts pending board changes to array', function () {
    $changes = [['op' => 'rename', 'id' => 1]];

    $session = PlotCoachSession::factory()->create([
        'pending_board_changes' => $changes,
    ]);

    expect($session->fresh()->pending_board_changes)->toBe($changes);
});

it('scopes to active', function () {
    $book1 = Book::factory()->create();
    $book2 = Book::factory()->create();

    PlotCoachSession::factory()->for($book1)->create([
        'status' => PlotCoachSessionStatus::Active,
    ]);

    PlotCoachSession::factory()->for($book2)->archived()->create();

    $active = PlotCoachSession::query()->active()->get();

    expect($active)->toHaveCount(1);
    expect($active->first()->status)->toBe(PlotCoachSessionStatus::Active);
});

it('belongs to a book', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book)->create();

    expect($session->book)->not->toBeNull();
    expect($session->book->id)->toBe($book->id);
});

it('enforces only one active session per book via partial unique index', function () {
    $book = Book::factory()->create();

    PlotCoachSession::factory()->for($book)->create([
        'status' => PlotCoachSessionStatus::Active,
    ]);

    expect(fn () => PlotCoachSession::factory()->for($book)->create([
        'status' => PlotCoachSessionStatus::Active,
    ]))->toThrow(QueryException::class);
});

it('allows a new active session after archiving the previous one', function () {
    $book = Book::factory()->create();

    $first = PlotCoachSession::factory()->for($book)->create([
        'status' => PlotCoachSessionStatus::Active,
    ]);

    $first->update([
        'status' => PlotCoachSessionStatus::Archived,
        'archived_at' => now(),
    ]);

    $second = PlotCoachSession::factory()->for($book)->create([
        'status' => PlotCoachSessionStatus::Active,
    ]);

    expect($second->exists)->toBeTrue();
    expect(PlotCoachSession::query()->active()->where('book_id', $book->id)->count())->toBe(1);
});
