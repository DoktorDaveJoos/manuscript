<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Scene;
use App\Models\Storyline;

// --- Soft delete behavior ---

test('chapter destroy soft-deletes chapter', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 0]);

    $this->delete(route('chapters.destroy', [$book, $chapter]));

    expect(Chapter::find($chapter->id))->toBeNull();
    expect(Chapter::withTrashed()->find($chapter->id))->not->toBeNull();
});

test('chapter destroy cascades soft-delete to scenes', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 0]);
    $scene = Scene::factory()->for($chapter)->create();

    $this->delete(route('chapters.destroy', [$book, $chapter]));

    expect(Scene::find($scene->id))->toBeNull();
    expect(Scene::withTrashed()->find($scene->id))->not->toBeNull();
});

test('storyline destroy cascades soft-delete to chapters and scenes', function () {
    $book = Book::factory()->create();
    $s1 = Storyline::factory()->for($book)->create();
    $s2 = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($s1)->create(['reader_order' => 0]);
    $scene = Scene::factory()->for($chapter)->create();

    $this->delete(route('storylines.destroy', [$book, $s1]));

    expect(Storyline::find($s1->id))->toBeNull();
    expect(Storyline::withTrashed()->find($s1->id))->not->toBeNull();
    expect(Chapter::find($chapter->id))->toBeNull();
    expect(Chapter::withTrashed()->find($chapter->id))->not->toBeNull();
    expect(Scene::find($scene->id))->toBeNull();
    expect(Scene::withTrashed()->find($scene->id))->not->toBeNull();
});

test('scene destroy soft-deletes scene', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 0]);
    Scene::factory()->for($chapter)->create(['sort_order' => 0]);
    $scene2 = Scene::factory()->for($chapter)->create(['sort_order' => 1]);

    $this->deleteJson(route('scenes.destroy', [$book, $chapter, $scene2]));

    expect(Scene::find($scene2->id))->toBeNull();
    expect(Scene::withTrashed()->find($scene2->id))->not->toBeNull();
});

test('restoreVersion force-deletes scenes', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $scene = Scene::factory()->for($chapter)->create();
    $v1 = ChapterVersion::factory()->for($chapter)->create([
        'version_number' => 1,
        'content' => 'Old content',
        'is_current' => false,
    ]);
    ChapterVersion::factory()->for($chapter)->create([
        'version_number' => 2,
        'content' => 'Current',
        'is_current' => true,
    ]);

    $this->post(route('chapters.restoreVersion', [$book, $chapter, $v1]));

    // Scene should be permanently gone, not soft-deleted
    expect(Scene::withTrashed()->find($scene->id))->toBeNull();
});

// --- Trash index ---

test('trash index returns trashed storylines', function () {
    $book = Book::factory()->create();
    $s1 = Storyline::factory()->for($book)->create(['name' => 'Main']);
    $s2 = Storyline::factory()->for($book)->create(['name' => 'Deleted']);
    $s2->delete();

    $this->getJson(route('books.trash.index', $book))
        ->assertSuccessful()
        ->assertJsonCount(1)
        ->assertJsonFragment(['type' => 'storyline', 'name' => 'Deleted']);
});

test('trash index returns trashed chapters when storyline is not trashed', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Trashed Ch']);
    $chapter->delete();

    $this->getJson(route('books.trash.index', $book))
        ->assertSuccessful()
        ->assertJsonFragment(['type' => 'chapter', 'name' => 'Trashed Ch']);
});

test('trash index hides chapters whose storyline is also trashed', function () {
    $book = Book::factory()->create();
    $s1 = Storyline::factory()->for($book)->create();
    $s2 = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($s1)->create(['title' => 'Hidden Ch']);

    // Soft-delete both storyline and chapter (as cascading destroy does)
    $chapter->delete();
    $s1->delete();

    $response = $this->getJson(route('books.trash.index', $book));

    // Only the storyline should appear, not the chapter
    $response->assertSuccessful();
    $items = $response->json();
    $types = array_column($items, 'type');
    expect($types)->toContain('storyline');
    expect($types)->not->toContain('chapter');
});

test('trash index returns trashed scenes when chapter is not trashed', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $scene1 = Scene::factory()->for($chapter)->create(['title' => 'Keep', 'sort_order' => 0]);
    $scene2 = Scene::factory()->for($chapter)->create(['title' => 'Trashed Scene', 'sort_order' => 1]);
    $scene2->delete();

    $this->getJson(route('books.trash.index', $book))
        ->assertSuccessful()
        ->assertJsonFragment(['type' => 'scene', 'name' => 'Trashed Scene']);
});

test('trash index hides scenes whose chapter is also trashed', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $scene = Scene::factory()->for($chapter)->create();

    $scene->delete();
    $chapter->delete();

    $response = $this->getJson(route('books.trash.index', $book));
    $items = $response->json();
    $types = array_column($items, 'type');
    expect($types)->not->toContain('scene');
});

test('trash index is empty when nothing trashed', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->getJson(route('books.trash.index', $book))
        ->assertSuccessful()
        ->assertJsonCount(0);
});

// --- Restore ---

test('restore storyline cascades to chapters and scenes', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 0]);
    $scene = Scene::factory()->for($chapter)->create();

    // Soft-delete all
    $scene->delete();
    $chapter->delete();
    $storyline->delete();

    $this->postJson(route('books.trash.restore', $book), [
        'type' => 'storyline',
        'id' => $storyline->id,
    ])->assertSuccessful();

    expect(Storyline::find($storyline->id))->not->toBeNull();
    expect(Chapter::find($chapter->id))->not->toBeNull();
    expect(Scene::find($scene->id))->not->toBeNull();
});

test('restore chapter restores with scenes and appends reader_order', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $existing = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 0]);
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 1, 'word_count' => 100]);
    $scene = Scene::factory()->for($chapter)->create(['word_count' => 100]);

    $scene->delete();
    $chapter->delete();

    // Recompact existing
    $existing->update(['reader_order' => 0]);

    $this->postJson(route('books.trash.restore', $book), [
        'type' => 'chapter',
        'id' => $chapter->id,
    ])->assertSuccessful();

    $chapter->refresh();
    expect(Chapter::find($chapter->id))->not->toBeNull();
    expect(Scene::find($scene->id))->not->toBeNull();
    expect($chapter->reader_order)->toBe(1); // appended after existing
});

test('restore chapter auto-restores trashed storyline', function () {
    $book = Book::factory()->create();
    $s1 = Storyline::factory()->for($book)->create();
    $s2 = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($s1)->create(['reader_order' => 0]);

    $chapter->delete();
    $s1->delete();

    $this->postJson(route('books.trash.restore', $book), [
        'type' => 'chapter',
        'id' => $chapter->id,
    ])->assertSuccessful();

    expect(Storyline::find($s1->id))->not->toBeNull();
    expect(Chapter::find($chapter->id))->not->toBeNull();
});

test('restore scene recalculates word count', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 0]);
    Scene::factory()->for($chapter)->create(['sort_order' => 0, 'word_count' => 50]);
    $scene2 = Scene::factory()->for($chapter)->create(['sort_order' => 1, 'word_count' => 75]);

    $chapter->update(['word_count' => 125]);
    $scene2->delete();
    $chapter->recalculateWordCount();

    expect($chapter->fresh()->word_count)->toBe(50);

    $this->postJson(route('books.trash.restore', $book), [
        'type' => 'scene',
        'id' => $scene2->id,
    ])->assertSuccessful();

    expect($chapter->fresh()->word_count)->toBe(125);
});

test('restore validates type and id', function () {
    $book = Book::factory()->create();

    $this->postJson(route('books.trash.restore', $book), [
        'type' => 'invalid',
        'id' => 1,
    ])->assertUnprocessable();

    $this->postJson(route('books.trash.restore', $book), [
        'type' => 'chapter',
    ])->assertUnprocessable();
});

// --- Empty trash ---

test('empty trash permanently deletes all trashed items', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 0]);
    $scene = Scene::factory()->for($chapter)->create();

    $scene->delete();
    $chapter->delete();
    $storyline->delete();

    $this->deleteJson(route('books.trash.empty', $book))
        ->assertSuccessful();

    expect(Storyline::withTrashed()->find($storyline->id))->toBeNull();
    expect(Chapter::withTrashed()->find($chapter->id))->toBeNull();
    expect(Scene::withTrashed()->find($scene->id))->toBeNull();
});

test('empty trash does not affect other books', function () {
    $book1 = Book::factory()->create();
    $book2 = Book::factory()->create();
    $s1 = Storyline::factory()->for($book1)->create();
    $s2 = Storyline::factory()->for($book2)->create();

    $s1->delete();
    $s2->delete();

    $this->deleteJson(route('books.trash.empty', $book1))
        ->assertSuccessful();

    expect(Storyline::withTrashed()->find($s1->id))->toBeNull();
    expect(Storyline::withTrashed()->find($s2->id))->not->toBeNull();
});
