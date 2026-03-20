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

    $response = $this->postJson(route('beats.store', [$book, $plotPoint]), [
        'title' => 'The confrontation',
        'description' => 'Jonas faces his nemesis',
    ]);

    $response->assertCreated()
        ->assertJsonPath('title', 'The confrontation')
        ->assertJsonPath('status', 'planned');

    $this->assertDatabaseHas('beats', [
        'plot_point_id' => $plotPoint->id,
        'title' => 'The confrontation',
    ]);
});

it('auto-increments sort_order on create', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    Beat::factory()->create(['plot_point_id' => $plotPoint->id, 'sort_order' => 0]);

    $response = $this->postJson(route('beats.store', [$book, $plotPoint]), [
        'title' => 'Second beat',
    ]);

    $response->assertCreated()->assertJsonPath('sort_order', 1);
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

    $response = $this->patchJson(route('beats.update', [$book, $beat]), [
        'title' => 'New title',
        'description' => 'Updated description',
    ]);

    $response->assertOk()->assertJsonPath('title', 'New title');
    expect($beat->fresh()->title)->toBe('New title');
});

it('deletes a beat', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $plotPoint->id]);

    $this->deleteJson(route('beats.destroy', [$book, $beat]))
        ->assertNoContent();

    $this->assertDatabaseMissing('beats', ['id' => $beat->id]);
});

it('reorders beats within a plot point', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $a = Beat::factory()->create(['plot_point_id' => $plotPoint->id, 'sort_order' => 0]);
    $b = Beat::factory()->create(['plot_point_id' => $plotPoint->id, 'sort_order' => 1]);

    $response = $this->postJson(route('beats.reorder', [$book, $plotPoint]), [
        'items' => [
            ['id' => $a->id, 'sort_order' => 1],
            ['id' => $b->id, 'sort_order' => 0],
        ],
    ]);

    $response->assertOk()->assertJsonPath('success', true);
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

    $this->patchJson(route('beats.updateStatus', [$book, $beat]), [
        'status' => 'fulfilled',
    ])->assertOk();

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

    $this->postJson(route('beats.chapters.link', [$book, $beat]), [
        'chapter_id' => $chapter->id,
    ])->assertOk()->assertJsonPath('success', true);

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

    $this->postJson(route('beats.chapters.link', [$book, $beat]), [
        'chapter_id' => $chapter->id,
    ])->assertOk();

    expect($beat->chapters()->count())->toBe(1);
});

it('unlinks a chapter from a beat', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $plotPoint->id]);
    $chapter = Chapter::factory()->create(['book_id' => $book->id, 'storyline_id' => $storyline->id]);
    $beat->chapters()->attach($chapter->id);

    $this->deleteJson(route('beats.chapters.unlink', [$book, $beat, $chapter]))
        ->assertNoContent();

    $this->assertDatabaseMissing('beat_chapter', [
        'beat_id' => $beat->id,
        'chapter_id' => $chapter->id,
    ]);
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
