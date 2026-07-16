<?php

use App\Enums\ChapterStatus;
use App\Enums\StorylineType;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\License;
use App\Models\Storyline;

beforeEach(fn () => License::factory()->create());

test('store creates storyline with chapter and scene', function () {
    $book = Book::factory()->create();
    $existingStoryline = Storyline::factory()->for($book)->create(['sort_order' => 0]);
    $existingChapter = Chapter::factory()->for($book)->for($existingStoryline)->create(['reader_order' => 0]);

    $this->post(route('storylines.store', $book), ['name' => 'B-Plot'])
        ->assertRedirect();

    $storyline = $book->storylines()->where('name', 'B-Plot')->first();
    expect($storyline)->not->toBeNull();
    expect($storyline->type)->toBe(StorylineType::Parallel);
    expect($storyline->sort_order)->toBe(1);

    $chapter = $storyline->chapters()->first();
    expect($chapter)->not->toBeNull();
    expect($chapter->title)->toBe('Chapter 1');
    expect($chapter->status)->toBe(ChapterStatus::Draft);
    expect($chapter->reader_order)->toBe(1);

    expect($chapter->versions()->count())->toBe(1);
    expect($chapter->scenes()->count())->toBe(1);
    expect($chapter->scenes()->first()->title)->toBe('Scene 1');
});

test('store validates name is required', function () {
    $book = Book::factory()->create();

    $this->post(route('storylines.store', $book), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('update changes storyline name and color', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create(['name' => 'Old', 'color' => '#111111']);

    $this->patchJson(route('storylines.update', [$book, $storyline]), [
        'name' => 'New Name',
        'color' => '#FF0000',
    ])
        ->assertOk()
        ->assertJsonFragment(['name' => 'New Name', 'color' => '#FF0000']);

    $storyline->refresh();
    expect($storyline->name)->toBe('New Name');
    expect($storyline->color)->toBe('#FF0000');
});

test('update validates name is required', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $this->patchJson(route('storylines.update', [$book, $storyline]), [
        'name' => '',
    ])->assertUnprocessable();
});

test('update validates color format', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $this->patchJson(route('storylines.update', [$book, $storyline]), [
        'name' => 'Test',
        'color' => 'not-a-color',
    ])->assertUnprocessable();
});

test('nested storyline routes reject storylines from another book', function () {
    $book = Book::factory()->create();
    $otherBook = Book::factory()->create();
    $foreignStoryline = Storyline::factory()->for($otherBook)->create();

    $this->patchJson(route('storylines.update', [$book, $foreignStoryline]), [
        'name' => 'Cross-book edit',
    ])->assertNotFound();
});

test('destroy cascades chapters and redirects', function () {
    $book = Book::factory()->create();
    $storyline1 = Storyline::factory()->for($book)->create(['sort_order' => 0]);
    $storyline2 = Storyline::factory()->for($book)->create(['sort_order' => 1]);

    $ch1 = Chapter::factory()->for($book)->for($storyline1)->create(['reader_order' => 0]);
    ChapterVersion::factory()->for($ch1)->create(['is_current' => true]);

    $ch2 = Chapter::factory()->for($book)->for($storyline2)->create(['reader_order' => 1]);
    ChapterVersion::factory()->for($ch2)->create(['is_current' => true]);

    $this->delete(route('storylines.destroy', [$book, $storyline1]))
        ->assertRedirect(route('chapters.show', [$book, $ch2]));

    expect(Storyline::find($storyline1->id))->toBeNull();
    expect(Chapter::find($ch1->id))->toBeNull();
    expect(Chapter::find($ch2->id))->not->toBeNull();
});

test('destroy blocks last storyline', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $this->delete(route('storylines.destroy', [$book, $storyline]))
        ->assertStatus(422);
});

test('successive deletes cannot remove the final storyline', function () {
    $book = Book::factory()->create();
    $first = Storyline::factory()->for($book)->create(['sort_order' => 0]);
    $second = Storyline::factory()->for($book)->create(['sort_order' => 1]);

    $this->delete(route('storylines.destroy', [$book, $first]))->assertRedirect();
    $this->delete(route('storylines.destroy', [$book, $second]))->assertStatus(422);

    expect($book->storylines()->count())->toBe(1)
        ->and($second->fresh())->not->toBeNull();
});

test('destroy redirects to editor when no chapters remain', function () {
    $book = Book::factory()->create();
    $storyline1 = Storyline::factory()->for($book)->create(['sort_order' => 0]);
    $storyline2 = Storyline::factory()->for($book)->create(['sort_order' => 1]);

    Chapter::factory()->for($book)->for($storyline1)->create();

    $this->delete(route('storylines.destroy', [$book, $storyline1]))
        ->assertRedirect(route('books.editor', $book));
});

test('reorder updates sort_order', function () {
    $book = Book::factory()->create();
    $s1 = Storyline::factory()->for($book)->create(['sort_order' => 0]);
    $s2 = Storyline::factory()->for($book)->create(['sort_order' => 1]);
    $s3 = Storyline::factory()->for($book)->create(['sort_order' => 2]);

    $this->postJson(route('storylines.reorder', $book), [
        'order' => [$s3->id, $s1->id, $s2->id],
    ])->assertOk();

    expect($s3->fresh()->sort_order)->toBe(0);
    expect($s1->fresh()->sort_order)->toBe(1);
    expect($s2->fresh()->sort_order)->toBe(2);
});

test('reorder validates order is required', function () {
    $book = Book::factory()->create();

    $this->postJson(route('storylines.reorder', $book), [])
        ->assertUnprocessable();
});

test('reorder rejects partial duplicate and foreign storyline payloads', function () {
    $book = Book::factory()->create();
    $first = Storyline::factory()->for($book)->create(['sort_order' => 0]);
    $second = Storyline::factory()->for($book)->create(['sort_order' => 1]);
    $otherBook = Book::factory()->create();
    $foreign = Storyline::factory()->for($otherBook)->create();

    $this->postJson(route('storylines.reorder', $book), [
        'order' => [$first->id],
    ])->assertJsonValidationErrors('order');

    $this->postJson(route('storylines.reorder', $book), [
        'order' => [$first->id, $first->id],
    ])->assertJsonValidationErrors('order.1');

    $this->postJson(route('storylines.reorder', $book), [
        'order' => [$first->id, $foreign->id],
    ])->assertJsonValidationErrors('order.1');

    expect($first->fresh()->sort_order)->toBe(0)
        ->and($second->fresh()->sort_order)->toBe(1);
});
