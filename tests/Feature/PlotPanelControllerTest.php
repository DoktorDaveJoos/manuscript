<?php

use App\Models\Beat;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\License;
use App\Models\PlotPoint;
use App\Models\Storyline;

beforeEach(fn () => License::factory()->create());

it('index returns connected beats grouped by plot point', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);
    $chapter = Chapter::factory()->create(['book_id' => $book->id, 'storyline_id' => $storyline->id]);
    $plotPointA = PlotPoint::factory()->create(['book_id' => $book->id, 'sort_order' => 0, 'title' => 'Inciting']);
    $plotPointB = PlotPoint::factory()->create(['book_id' => $book->id, 'sort_order' => 1, 'title' => 'Twist']);
    $beatA1 = Beat::factory()->create(['plot_point_id' => $plotPointA->id, 'title' => 'Murder']);
    $beatA2 = Beat::factory()->create(['plot_point_id' => $plotPointA->id, 'title' => 'Body found']);
    $beatB = Beat::factory()->create(['plot_point_id' => $plotPointB->id, 'title' => 'Letter found']);
    Beat::factory()->create(['plot_point_id' => $plotPointA->id, 'title' => 'Unrelated']);
    $beatA1->chapters()->attach($chapter->id);
    $beatA2->chapters()->attach($chapter->id);
    $beatB->chapters()->attach($chapter->id);

    $response = $this->getJson(route('plot.panel.index', [
        'book' => $book,
        'chapter_id' => $chapter->id,
    ]));

    $response->assertOk()->assertJson([
        'connected' => [
            ['plot_point' => ['id' => $plotPointA->id, 'title' => 'Inciting']],
            ['plot_point' => ['id' => $plotPointB->id, 'title' => 'Twist']],
        ],
        'session' => [],
    ]);
    expect($response->json('connected.0.beats'))->toHaveCount(2)
        ->and($response->json('connected.1.beats'))->toHaveCount(1)
        ->and(collect($response->json('connected.0.beats'))->pluck('title'))
        ->toContain('Murder', 'Body found');
});

it('index with q returns matching session beats excluding already-connected', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);
    $chapter = Chapter::factory()->create(['book_id' => $book->id, 'storyline_id' => $storyline->id]);
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id, 'title' => 'Climax']);
    $connectedBeat = Beat::factory()->create(['plot_point_id' => $plotPoint->id, 'title' => 'Murder fight']);
    $connectedBeat->chapters()->attach($chapter->id);
    Beat::factory()->create(['plot_point_id' => $plotPoint->id, 'title' => 'Murder confession']);
    Beat::factory()->create(['plot_point_id' => $plotPoint->id, 'title' => 'Murder cleanup']);
    Beat::factory()->create(['plot_point_id' => $plotPoint->id, 'title' => 'Unrelated stuff']);

    $response = $this->getJson(route('plot.panel.index', [
        'book' => $book,
        'chapter_id' => $chapter->id,
        'q' => 'Murder',
    ]));

    $response->assertOk();
    $sessionBeats = collect($response->json('session.0.beats'))->pluck('title');
    expect($sessionBeats)->toHaveCount(2)
        ->and($sessionBeats)->toContain('Murder confession', 'Murder cleanup')
        ->and($sessionBeats)->not->toContain('Murder fight', 'Unrelated stuff');
});

it('index ignores beats from other books', function () {
    $book = Book::factory()->create();
    $otherBook = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);
    $chapter = Chapter::factory()->create(['book_id' => $book->id, 'storyline_id' => $storyline->id]);
    $otherPlotPoint = PlotPoint::factory()->create(['book_id' => $otherBook->id]);
    $otherBeat = Beat::factory()->create(['plot_point_id' => $otherPlotPoint->id, 'title' => 'Foreign beat']);

    $response = $this->getJson(route('plot.panel.index', [
        'book' => $book,
        'chapter_id' => $chapter->id,
        'q' => 'Foreign',
    ]));

    $response->assertOk()
        ->assertJson(['connected' => [], 'session' => []]);
});

it('connect adds the pivot row', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);
    $chapter = Chapter::factory()->create(['book_id' => $book->id, 'storyline_id' => $storyline->id]);
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $plotPoint->id]);

    $this->postJson(route('plot.panel.connect', $book), [
        'chapter_id' => $chapter->id,
        'beat_id' => $beat->id,
    ])->assertOk();

    $this->assertDatabaseHas('beat_chapter', [
        'beat_id' => $beat->id,
        'chapter_id' => $chapter->id,
    ]);
});

it('connect is idempotent', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);
    $chapter = Chapter::factory()->create(['book_id' => $book->id, 'storyline_id' => $storyline->id]);
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $plotPoint->id]);
    $beat->chapters()->attach($chapter->id);

    $this->postJson(route('plot.panel.connect', $book), [
        'chapter_id' => $chapter->id,
        'beat_id' => $beat->id,
    ])->assertOk();

    expect($beat->chapters()->count())->toBe(1);
});

it('connect 404s when beat belongs to a different book', function () {
    $book = Book::factory()->create();
    $otherBook = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);
    $chapter = Chapter::factory()->create(['book_id' => $book->id, 'storyline_id' => $storyline->id]);
    $foreignPlotPoint = PlotPoint::factory()->create(['book_id' => $otherBook->id]);
    $foreignBeat = Beat::factory()->create(['plot_point_id' => $foreignPlotPoint->id]);

    $this->postJson(route('plot.panel.connect', $book), [
        'chapter_id' => $chapter->id,
        'beat_id' => $foreignBeat->id,
    ])->assertNotFound();
});

it('disconnect removes the pivot row', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);
    $chapter = Chapter::factory()->create(['book_id' => $book->id, 'storyline_id' => $storyline->id]);
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $plotPoint->id]);
    $beat->chapters()->attach($chapter->id);

    $this->postJson(route('plot.panel.disconnect', $book), [
        'chapter_id' => $chapter->id,
        'beat_id' => $beat->id,
    ])->assertOk();

    $this->assertDatabaseMissing('beat_chapter', [
        'beat_id' => $beat->id,
        'chapter_id' => $chapter->id,
    ]);
});

it('disconnect is safe when row does not exist', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);
    $chapter = Chapter::factory()->create(['book_id' => $book->id, 'storyline_id' => $storyline->id]);
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $plotPoint->id]);

    $this->postJson(route('plot.panel.disconnect', $book), [
        'chapter_id' => $chapter->id,
        'beat_id' => $beat->id,
    ])->assertOk();
});

it('all panel endpoints redirect when license is inactive', function () {
    License::query()->delete();
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);
    $chapter = Chapter::factory()->create(['book_id' => $book->id, 'storyline_id' => $storyline->id]);
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $beat = Beat::factory()->create(['plot_point_id' => $plotPoint->id]);

    $this->get(route('plot.panel.index', ['book' => $book, 'chapter_id' => $chapter->id]))
        ->assertRedirect();
    $this->post(route('plot.panel.connect', $book), [
        'chapter_id' => $chapter->id,
        'beat_id' => $beat->id,
    ])->assertRedirect();
    $this->post(route('plot.panel.disconnect', $book), [
        'chapter_id' => $chapter->id,
        'beat_id' => $beat->id,
    ])->assertRedirect();
});
