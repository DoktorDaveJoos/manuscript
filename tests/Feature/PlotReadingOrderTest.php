<?php

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Storyline;

test('interleave produces correct round-robin order across acts and storylines on plot page', function () {
    $book = Book::factory()->create();

    $storylineA = Storyline::factory()->for($book)->create(['sort_order' => 0]);
    $storylineB = Storyline::factory()->for($book)->create(['sort_order' => 1]);
    $storylineC = Storyline::factory()->for($book)->backstory()->create(['sort_order' => 2]);

    $act1 = Act::factory()->for($book)->create(['sort_order' => 0, 'number' => 1]);
    $act2 = Act::factory()->for($book)->create(['sort_order' => 1, 'number' => 2]);

    // Act 1: two chapters on storyline A, one on storyline B
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

    // Act 2: one chapter on storyline B, one on storyline C, one on storyline A
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

    // Expected round-robin order:
    // Act 1 (sort_order 0): storylines A(0), B(1) → A1, B1, A2
    // Act 2 (sort_order 1): storylines A(0), B(1), C(2) → A1, B1, C1
    $this->get(route('books.plot', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('plot/index')
            ->has('chapters', 6)
            ->where('chapters.0.id', $a1_sA_ch1->id)
            ->where('chapters.1.id', $a1_sB_ch1->id)
            ->where('chapters.2.id', $a1_sA_ch2->id)
            ->where('chapters.3.id', $a2_sA_ch1->id)
            ->where('chapters.4.id', $a2_sB_ch1->id)
            ->where('chapters.5.id', $a2_sC_ch1->id)
        );
});

test('null act_id chapters appear last after interleave', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create(['sort_order' => 0]);
    $act = Act::factory()->for($book)->create(['sort_order' => 0, 'number' => 1]);

    $assigned1 = Chapter::factory()->for($book)->create([
        'storyline_id' => $storyline->id,
        'act_id' => $act->id,
        'reader_order' => 0,
        'title' => 'Assigned 1',
    ]);
    $assigned2 = Chapter::factory()->for($book)->create([
        'storyline_id' => $storyline->id,
        'act_id' => $act->id,
        'reader_order' => 1,
        'title' => 'Assigned 2',
    ]);
    $unassigned = Chapter::factory()->for($book)->create([
        'storyline_id' => $storyline->id,
        'act_id' => null,
        'reader_order' => 2,
        'title' => 'Unassigned',
    ]);

    $this->postJson(route('chapters.interleave', $book))->assertSuccessful();

    $this->get(route('books.plot', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('plot/index')
            ->has('chapters', 3)
            ->where('chapters.0.id', $assigned1->id)
            ->where('chapters.1.id', $assigned2->id)
            ->where('chapters.2.id', $unassigned->id)
        );
});

test('soft-deleted chapters are excluded from plot page', function () {
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

    $this->get(route('books.plot', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('plot/index')
            ->has('chapters', 2)
            ->where('chapters.0.id', $chapter1->id)
            ->where('chapters.1.id', $chapter2->id)
        );
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
        'title' => 'A1',
    ]);
    $chA2 = Chapter::factory()->for($book)->create([
        'storyline_id' => $storylineA->id,
        'act_id' => $act->id,
        'reader_order' => 1,
        'title' => 'A2',
    ]);
    $chB1 = Chapter::factory()->for($book)->create([
        'storyline_id' => $storylineB->id,
        'act_id' => $act->id,
        'reader_order' => 2,
        'title' => 'B1',
    ]);
    $chB2 = Chapter::factory()->for($book)->create([
        'storyline_id' => $storylineB->id,
        'act_id' => $act->id,
        'reader_order' => 3,
        'title' => 'B2',
    ]);

    // Manual reorder: B2, B1, A2, A1 (reverse everything)
    $this->postJson(route('chapters.reorder', $book), [
        'order' => [
            ['id' => $chB2->id, 'storyline_id' => $storylineB->id],
            ['id' => $chB1->id, 'storyline_id' => $storylineB->id],
            ['id' => $chA2->id, 'storyline_id' => $storylineA->id],
            ['id' => $chA1->id, 'storyline_id' => $storylineA->id],
        ],
    ])->assertSuccessful();

    // Verify manual order took effect
    $this->get(route('books.plot', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('chapters.0.id', $chB2->id)
            ->where('chapters.1.id', $chB1->id)
            ->where('chapters.2.id', $chA2->id)
            ->where('chapters.3.id', $chA1->id)
        );

    // Interleave should override manual order with round-robin
    $this->postJson(route('chapters.interleave', $book))->assertSuccessful();

    // Round-robin within single act: storyline A(sort 0) then B(sort 1)
    // Within each storyline, sorted by reader_order at time of interleave.
    // After reorder: B2=0, B1=1, A2=2, A1=3
    // Round-robin picks: A first (sort_order 0) then B (sort_order 1)
    // A queue (by reader_order): A2(2), A1(3)
    // B queue (by reader_order): B2(0), B1(1)
    // Result: A2, B2, A1, B1
    $this->get(route('books.plot', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('plot/index')
            ->has('chapters', 4)
            ->where('chapters.0.id', $chA2->id)
            ->where('chapters.1.id', $chB2->id)
            ->where('chapters.2.id', $chA1->id)
            ->where('chapters.3.id', $chB1->id)
        );
});
