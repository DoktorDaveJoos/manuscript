<?php

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Storyline;

test('interleave with 2 storylines and 2 acts round-robins correctly', function () {
    $book = Book::factory()->create();
    $s1 = Storyline::factory()->for($book)->create(['sort_order' => 0]);
    $s2 = Storyline::factory()->for($book)->create(['sort_order' => 1]);
    $act1 = Act::factory()->for($book)->create(['sort_order' => 0, 'number' => 1]);
    $act2 = Act::factory()->for($book)->create(['sort_order' => 1, 'number' => 2]);

    $chA = Chapter::factory()->for($book)->for($s1)->create(['act_id' => $act1->id, 'reader_order' => 0, 'title' => 'A']);
    $chB = Chapter::factory()->for($book)->for($s1)->create(['act_id' => $act1->id, 'reader_order' => 2, 'title' => 'B']);
    $chC = Chapter::factory()->for($book)->for($s2)->create(['act_id' => $act1->id, 'reader_order' => 1, 'title' => 'C']);
    $chD = Chapter::factory()->for($book)->for($s2)->create(['act_id' => $act1->id, 'reader_order' => 3, 'title' => 'D']);

    $chE = Chapter::factory()->for($book)->for($s1)->create(['act_id' => $act2->id, 'reader_order' => 4, 'title' => 'E']);
    $chF = Chapter::factory()->for($book)->for($s2)->create(['act_id' => $act2->id, 'reader_order' => 5, 'title' => 'F']);

    $response = $this->postJson(route('chapters.interleave', $book));

    $response->assertOk();

    $result = $response->json();
    $expectedOrder = [$chA->id, $chC->id, $chB->id, $chD->id, $chE->id, $chF->id];
    $resultIds = collect($result)->pluck('id')->all();
    expect($resultIds)->toBe($expectedOrder);

    foreach ($result as $index => $item) {
        expect($item['reader_order'])->toBe($index);
    }

    expect($chA->fresh()->reader_order)->toBe(0);
    expect($chC->fresh()->reader_order)->toBe(1);
    expect($chB->fresh()->reader_order)->toBe(2);
    expect($chD->fresh()->reader_order)->toBe(3);
    expect($chE->fresh()->reader_order)->toBe(4);
    expect($chF->fresh()->reader_order)->toBe(5);
});

test('interleave with single storyline preserves order', function () {
    $book = Book::factory()->create();
    $s1 = Storyline::factory()->for($book)->create(['sort_order' => 0]);
    $act1 = Act::factory()->for($book)->create(['sort_order' => 0, 'number' => 1]);

    $ch1 = Chapter::factory()->for($book)->for($s1)->create(['act_id' => $act1->id, 'reader_order' => 0]);
    $ch2 = Chapter::factory()->for($book)->for($s1)->create(['act_id' => $act1->id, 'reader_order' => 1]);
    $ch3 = Chapter::factory()->for($book)->for($s1)->create(['act_id' => $act1->id, 'reader_order' => 2]);

    $response = $this->postJson(route('chapters.interleave', $book));

    $response->assertOk();

    $resultIds = collect($response->json())->pluck('id')->all();
    expect($resultIds)->toBe([$ch1->id, $ch2->id, $ch3->id]);
});

test('interleave places null act_id chapters at the end', function () {
    $book = Book::factory()->create();
    $s1 = Storyline::factory()->for($book)->create(['sort_order' => 0]);
    $act1 = Act::factory()->for($book)->create(['sort_order' => 0, 'number' => 1]);

    $chAssigned1 = Chapter::factory()->for($book)->for($s1)->create(['act_id' => $act1->id, 'reader_order' => 0]);
    $chAssigned2 = Chapter::factory()->for($book)->for($s1)->create(['act_id' => $act1->id, 'reader_order' => 1]);

    $chUnassigned1 = Chapter::factory()->for($book)->for($s1)->create(['act_id' => null, 'reader_order' => 2]);
    $chUnassigned2 = Chapter::factory()->for($book)->for($s1)->create(['act_id' => null, 'reader_order' => 3]);

    $response = $this->postJson(route('chapters.interleave', $book));

    $response->assertOk();

    $resultIds = collect($response->json())->pluck('id')->all();
    expect($resultIds)->toBe([$chAssigned1->id, $chAssigned2->id, $chUnassigned1->id, $chUnassigned2->id]);
});

test('interleave handles unequal chapter counts across storylines', function () {
    $book = Book::factory()->create();
    $s1 = Storyline::factory()->for($book)->create(['sort_order' => 0]);
    $s2 = Storyline::factory()->for($book)->create(['sort_order' => 1]);
    $act1 = Act::factory()->for($book)->create(['sort_order' => 0, 'number' => 1]);

    $chA = Chapter::factory()->for($book)->for($s1)->create(['act_id' => $act1->id, 'reader_order' => 0]);
    $chB = Chapter::factory()->for($book)->for($s1)->create(['act_id' => $act1->id, 'reader_order' => 1]);
    $chC = Chapter::factory()->for($book)->for($s1)->create(['act_id' => $act1->id, 'reader_order' => 2]);
    $chD = Chapter::factory()->for($book)->for($s2)->create(['act_id' => $act1->id, 'reader_order' => 3]);

    $response = $this->postJson(route('chapters.interleave', $book));

    $response->assertOk();

    $resultIds = collect($response->json())->pluck('id')->all();
    expect($resultIds)->toBe([$chA->id, $chD->id, $chB->id, $chC->id]);
});

test('interleave returns empty array for book with no chapters', function () {
    $book = Book::factory()->create();

    $response = $this->postJson(route('chapters.interleave', $book));

    $response->assertOk();
    expect($response->json())->toBe([]);
});

test('interleave excludes soft-deleted chapters', function () {
    $book = Book::factory()->create();
    $s1 = Storyline::factory()->for($book)->create(['sort_order' => 0]);
    $act1 = Act::factory()->for($book)->create(['sort_order' => 0, 'number' => 1]);

    $ch1 = Chapter::factory()->for($book)->for($s1)->create(['act_id' => $act1->id, 'reader_order' => 0]);
    $ch2 = Chapter::factory()->for($book)->for($s1)->create(['act_id' => $act1->id, 'reader_order' => 1]);
    $trashed = Chapter::factory()->for($book)->for($s1)->create(['act_id' => $act1->id, 'reader_order' => 2]);
    $trashed->delete();

    $response = $this->postJson(route('chapters.interleave', $book));

    $response->assertOk();

    $resultIds = collect($response->json())->pluck('id')->all();
    expect($resultIds)->toBe([$ch1->id, $ch2->id]);
    expect($resultIds)->not->toContain($trashed->id);
});
