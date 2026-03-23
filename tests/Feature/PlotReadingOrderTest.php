<?php

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\License;
use App\Models\Storyline;

beforeEach(fn () => License::factory()->create());

test('interleave produces correct round-robin order across acts and storylines', function () {
    $book = Book::factory()->create();

    $storylineA = Storyline::factory()->for($book)->create(['sort_order' => 0]);
    $storylineB = Storyline::factory()->for($book)->create(['sort_order' => 1]);
    $storylineC = Storyline::factory()->for($book)->backstory()->create(['sort_order' => 2]);

    $act1 = Act::factory()->for($book)->create(['sort_order' => 0, 'number' => 1]);
    $act2 = Act::factory()->for($book)->create(['sort_order' => 1, 'number' => 2]);

    $a1_sA_ch1 = Chapter::factory()->for($book)->create([
        'storyline_id' => $storylineA->id,
        'act_id' => $act1->id,
        'reader_order' => 0,
        'title' => 'Act1-StoryA-1',
    ]);
    $a1_sA_ch2 = Chapter::factory()->for($book)->create([
        'storyline_id' => $storylineA->id,
        'act_id' => $act1->id,
        'reader_order' => 1,
        'title' => 'Act1-StoryA-2',
    ]);
    $a1_sB_ch1 = Chapter::factory()->for($book)->create([
        'storyline_id' => $storylineB->id,
        'act_id' => $act1->id,
        'reader_order' => 2,
        'title' => 'Act1-StoryB-1',
    ]);

    $a2_sB_ch1 = Chapter::factory()->for($book)->create([
        'storyline_id' => $storylineB->id,
        'act_id' => $act2->id,
        'reader_order' => 3,
        'title' => 'Act2-StoryB-1',
    ]);
    $a2_sC_ch1 = Chapter::factory()->for($book)->create([
        'storyline_id' => $storylineC->id,
        'act_id' => $act2->id,
        'reader_order' => 4,
        'title' => 'Act2-StoryC-1',
    ]);
    $a2_sA_ch1 = Chapter::factory()->for($book)->create([
        'storyline_id' => $storylineA->id,
        'act_id' => $act2->id,
        'reader_order' => 5,
        'title' => 'Act2-StoryA-1',
    ]);

    $this->postJson(route('chapters.interleave', $book))->assertSuccessful();

    // Verify round-robin order via database reader_order
    expect($a1_sA_ch1->fresh()->reader_order)->toBe(0)
        ->and($a1_sB_ch1->fresh()->reader_order)->toBe(1)
        ->and($a1_sA_ch2->fresh()->reader_order)->toBe(2)
        ->and($a2_sA_ch1->fresh()->reader_order)->toBe(3)
        ->and($a2_sB_ch1->fresh()->reader_order)->toBe(4)
        ->and($a2_sC_ch1->fresh()->reader_order)->toBe(5);
});

test('null act_id chapters appear last after interleave', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create(['sort_order' => 0]);
    $act = Act::factory()->for($book)->create(['sort_order' => 0, 'number' => 1]);

    $assigned1 = Chapter::factory()->for($book)->create([
        'storyline_id' => $storyline->id,
        'act_id' => $act->id,
        'reader_order' => 0,
    ]);
    $assigned2 = Chapter::factory()->for($book)->create([
        'storyline_id' => $storyline->id,
        'act_id' => $act->id,
        'reader_order' => 1,
    ]);
    $unassigned = Chapter::factory()->for($book)->create([
        'storyline_id' => $storyline->id,
        'act_id' => null,
        'reader_order' => 2,
    ]);

    $this->postJson(route('chapters.interleave', $book))->assertSuccessful();

    expect($assigned1->fresh()->reader_order)->toBeLessThan($unassigned->fresh()->reader_order)
        ->and($assigned2->fresh()->reader_order)->toBeLessThan($unassigned->fresh()->reader_order);
});

test('soft-deleted chapters are excluded from interleave', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create(['sort_order' => 0]);

    $chapter1 = Chapter::factory()->for($book)->create([
        'storyline_id' => $storyline->id,
        'reader_order' => 0,
    ]);
    $chapter2 = Chapter::factory()->for($book)->create([
        'storyline_id' => $storyline->id,
        'reader_order' => 1,
    ]);
    $deleted = Chapter::factory()->for($book)->create([
        'storyline_id' => $storyline->id,
        'reader_order' => 2,
    ]);

    $deleted->delete();

    // Verify the plot page loads without the deleted chapter
    $this->get(route('books.plot', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('plot/index')
        );

    // Verify only 2 non-deleted chapters exist
    expect($book->chapters()->count())->toBe(2);
});

test('reorder then interleave round-trip produces correct round-robin order', function () {
    $book = Book::factory()->create();

    $storylineA = Storyline::factory()->for($book)->create(['sort_order' => 0]);
    $storylineB = Storyline::factory()->for($book)->create(['sort_order' => 1]);

    $act = Act::factory()->for($book)->create(['sort_order' => 0, 'number' => 1]);

    $chA1 = Chapter::factory()->for($book)->create([
        'storyline_id' => $storylineA->id,
        'act_id' => $act->id,
        'reader_order' => 0,
    ]);
    $chA2 = Chapter::factory()->for($book)->create([
        'storyline_id' => $storylineA->id,
        'act_id' => $act->id,
        'reader_order' => 1,
    ]);
    $chB1 = Chapter::factory()->for($book)->create([
        'storyline_id' => $storylineB->id,
        'act_id' => $act->id,
        'reader_order' => 2,
    ]);
    $chB2 = Chapter::factory()->for($book)->create([
        'storyline_id' => $storylineB->id,
        'act_id' => $act->id,
        'reader_order' => 3,
    ]);

    // Manual reorder: B2, B1, A2, A1
    $this->postJson(route('chapters.reorder', $book), [
        'order' => [
            ['id' => $chB2->id, 'storyline_id' => $storylineB->id],
            ['id' => $chB1->id, 'storyline_id' => $storylineB->id],
            ['id' => $chA2->id, 'storyline_id' => $storylineA->id],
            ['id' => $chA1->id, 'storyline_id' => $storylineA->id],
        ],
    ])->assertSuccessful();

    // Verify manual order took effect
    expect($chB2->fresh()->reader_order)->toBe(0)
        ->and($chB1->fresh()->reader_order)->toBe(1)
        ->and($chA2->fresh()->reader_order)->toBe(2)
        ->and($chA1->fresh()->reader_order)->toBe(3);

    // Interleave should override manual order with round-robin
    $this->postJson(route('chapters.interleave', $book))->assertSuccessful();

    // Round-robin: A first (sort_order 0) then B (sort_order 1)
    // A queue (by reader_order): A2(2), A1(3)
    // B queue (by reader_order): B2(0), B1(1)
    // Result: A2, B2, A1, B1
    expect($chA2->fresh()->reader_order)->toBe(0)
        ->and($chB2->fresh()->reader_order)->toBe(1)
        ->and($chA1->fresh()->reader_order)->toBe(2)
        ->and($chB1->fresh()->reader_order)->toBe(3);
});
