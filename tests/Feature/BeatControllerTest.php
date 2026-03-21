<?php

use App\Enums\BeatStatus;
use App\Models\Beat;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\PlotPoint;
use App\Models\Storyline;

it('creates a beat under a plot point', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);

    $this->post(route('beats.store', [$book, $plotPoint]), [
        'title' => 'The confrontation',
        'description' => 'Jonas faces his nemesis',
    ])->assertRedirect();

    $this->assertDatabaseHas('beats', [
        'plot_point_id' => $plotPoint->id,
        'title' => 'The confrontation',
        'status' => 'planned',
    ]);
});

it('auto-increments sort_order on create', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    Beat::factory()->create(['plot_point_id' => $plotPoint->id, 'sort_order' => 0]);

    $this->post(route('beats.store', [$book, $plotPoint]), [
        'title' => 'Second beat',
    ])->assertRedirect();

    expect(Beat::where('title', 'Second beat')->first()->sort_order)->toBe(1);
});

it('requires a title to create a beat', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);

    $this->postJson(route('beats.store', [$book, $plotPoint]), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

it('updates a beat', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $plotPoint->id, 'title' => 'Old title']);

    $this->patch(route('beats.update', [$book, $beat]), [
        'title' => 'New title',
        'description' => 'Updated description',
    ])->assertRedirect();

    expect($beat->fresh()->title)->toBe('New title');
});

it('deletes a beat', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $plotPoint->id]);

    $this->delete(route('beats.destroy', [$book, $beat]))
        ->assertRedirect();

    $this->assertDatabaseMissing('beats', ['id' => $beat->id]);
});

it('reorders beats within a plot point', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $a = Beat::factory()->create(['plot_point_id' => $plotPoint->id, 'sort_order' => 0]);
    $b = Beat::factory()->create(['plot_point_id' => $plotPoint->id, 'sort_order' => 1]);

    $this->post(route('beats.reorder', [$book, $plotPoint]), [
        'items' => [
            ['id' => $a->id, 'sort_order' => 1],
            ['id' => $b->id, 'sort_order' => 0],
        ],
    ])->assertRedirect();

    expect($a->fresh()->sort_order)->toBe(1)
        ->and($b->fresh()->sort_order)->toBe(0);
});

it('reorder rejects beats from another plot point', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $otherPlotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $otherPlotPoint->id, 'sort_order' => 0]);

    $this->postJson(route('beats.reorder', [$book, $plotPoint]), [
        'items' => [
            ['id' => $beat->id, 'sort_order' => 0],
        ],
    ])->assertUnprocessable();
});

it('cycles beat status', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $plotPoint->id, 'status' => BeatStatus::Planned]);

    $this->patch(route('beats.updateStatus', [$book, $beat]), [
        'status' => 'fulfilled',
    ])->assertRedirect();

    expect($beat->fresh()->status)->toBe(BeatStatus::Fulfilled);
});

it('rejects invalid status value', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $plotPoint->id]);

    $this->patchJson(route('beats.updateStatus', [$book, $beat]), [
        'status' => 'invalid_status',
    ])->assertUnprocessable();
});

it('links a chapter to a beat', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $plotPoint->id]);
    $chapter = Chapter::factory()->create(['book_id' => $book->id, 'storyline_id' => $storyline->id]);

    $this->post(route('beats.chapters.link', [$book, $beat]), [
        'chapter_id' => $chapter->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('beat_chapter', [
        'beat_id' => $beat->id,
        'chapter_id' => $chapter->id,
    ]);
});

it('does not duplicate a chapter link', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $plotPoint->id]);
    $chapter = Chapter::factory()->create(['book_id' => $book->id, 'storyline_id' => $storyline->id]);

    $beat->chapters()->attach($chapter->id);

    $this->post(route('beats.chapters.link', [$book, $beat]), [
        'chapter_id' => $chapter->id,
    ])->assertRedirect();

    expect($beat->chapters()->count())->toBe(1);
});

it('unlinks a chapter from a beat', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $plotPoint->id]);
    $chapter = Chapter::factory()->create(['book_id' => $book->id, 'storyline_id' => $storyline->id]);
    $beat->chapters()->attach($chapter->id);

    $this->delete(route('beats.chapters.unlink', [$book, $beat, $chapter]))
        ->assertRedirect();

    $this->assertDatabaseMissing('beat_chapter', [
        'beat_id' => $beat->id,
        'chapter_id' => $chapter->id,
    ]);
});

it('moves a beat to another plot point', function () {
    $book = Book::factory()->create();
    $ppA = PlotPoint::factory()->create(['book_id' => $book->id]);
    $ppB = PlotPoint::factory()->create(['book_id' => $book->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $ppA->id, 'sort_order' => 0]);

    $this->patch(route('beats.move', [$book, $beat]), [
        'plot_point_id' => $ppB->id,
        'sort_order' => 0,
    ])->assertRedirect();

    $beat->refresh();
    expect($beat->plot_point_id)->toBe($ppB->id)
        ->and($beat->sort_order)->toBe(0);
});

it('move rejects a plot point from another book', function () {
    $book = Book::factory()->create();
    $otherBook = Book::factory()->create();
    $pp = PlotPoint::factory()->create(['book_id' => $book->id]);
    $otherPP = PlotPoint::factory()->create(['book_id' => $otherBook->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $pp->id, 'sort_order' => 0]);

    $this->patchJson(route('beats.move', [$book, $beat]), [
        'plot_point_id' => $otherPP->id,
        'sort_order' => 0,
    ])->assertForbidden();
});

it('move updates sort_order within the same plot point', function () {
    $book = Book::factory()->create();
    $pp = PlotPoint::factory()->create(['book_id' => $book->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $pp->id, 'sort_order' => 0]);

    $this->patch(route('beats.move', [$book, $beat]), [
        'plot_point_id' => $pp->id,
        'sort_order' => 3,
    ])->assertRedirect();

    expect($beat->fresh()->sort_order)->toBe(3);
});

it('cascade deletes beats when plot point is deleted', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $plotPoint->id]);

    $plotPoint->delete();

    $this->assertDatabaseMissing('beats', ['id' => $beat->id]);
});

it('cascade detaches beat_chapter rows when beat is deleted', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $plotPoint->id]);
    $chapter = Chapter::factory()->create(['book_id' => $book->id, 'storyline_id' => $storyline->id]);
    $beat->chapters()->attach($chapter->id);

    $beat->delete();

    $this->assertDatabaseMissing('beat_chapter', ['beat_id' => $beat->id]);
});

it('beat factory fulfilled state sets correct status', function () {
    $beat = Beat::factory()->fulfilled()->make();

    expect($beat->status)->toBe(BeatStatus::Fulfilled);
});

it('beat factory abandoned state sets correct status', function () {
    $beat = Beat::factory()->abandoned()->make();

    expect($beat->status)->toBe(BeatStatus::Abandoned);
});
