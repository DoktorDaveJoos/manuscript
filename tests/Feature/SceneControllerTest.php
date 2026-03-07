<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Scene;
use App\Models\Storyline;
use App\Models\WritingSession;

test('store creates scene at default position', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['sort_order' => 0]);

    $this->postJson(route('scenes.store', [$book, $chapter]), [
        'title' => 'New Scene',
    ])
        ->assertCreated()
        ->assertJsonFragment(['title' => 'New Scene']);

    expect($chapter->scenes()->count())->toBe(2);
    expect($chapter->scenes()->where('title', 'New Scene')->first()->sort_order)->toBe(1);
});

test('store creates scene at specific position', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['sort_order' => 0, 'title' => 'First']);
    Scene::factory()->for($chapter)->create(['sort_order' => 1, 'title' => 'Second']);

    $this->postJson(route('scenes.store', [$book, $chapter]), [
        'title' => 'Inserted',
        'position' => 1,
    ])->assertCreated();

    $scenes = $chapter->scenes()->orderBy('sort_order')->get();
    expect($scenes)->toHaveCount(3);
    expect($scenes[0]->title)->toBe('First');
    expect($scenes[1]->title)->toBe('Inserted');
    expect($scenes[2]->title)->toBe('Second');
});

test('updateContent saves content and recalculates word count', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 0]);
    $scene = Scene::factory()->for($chapter)->create(['word_count' => 0, 'content' => '']);

    $this->putJson(route('scenes.updateContent', [$book, $chapter, $scene]), [
        'content' => '<p>The quick brown fox jumps</p>',
    ])
        ->assertOk()
        ->assertJsonStructure(['word_count', 'chapter_word_count', 'saved_at']);

    $scene->refresh();
    expect($scene->word_count)->toBe(5);
    expect($scene->content)->toContain('quick brown fox');

    $chapter->refresh();
    expect($chapter->word_count)->toBe(5);
});

test('updateContent tracks writing session for word count increase', function () {
    $book = Book::factory()->create(['daily_word_count_goal' => 10]);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 0]);
    $scene = Scene::factory()->for($chapter)->create(['word_count' => 0, 'content' => '']);

    $this->putJson(route('scenes.updateContent', [$book, $chapter, $scene]), [
        'content' => '<p>The quick brown fox jumps</p>',
    ])->assertOk();

    $session = WritingSession::where('book_id', $book->id)->first();
    expect($session)->not->toBeNull();
    expect($session->words_written)->toBe(5);
    expect($session->goal_met)->toBeFalse();
});

test('updateContent does not track session on word decrease', function () {
    $book = Book::factory()->create(['daily_word_count_goal' => 100]);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 10]);
    $scene = Scene::factory()->for($chapter)->create(['word_count' => 10, 'content' => '<p>Hello world test words here and more stuff foo bar</p>']);

    $this->putJson(route('scenes.updateContent', [$book, $chapter, $scene]), [
        'content' => '<p>Hello</p>',
    ])->assertOk();

    expect(WritingSession::where('book_id', $book->id)->first())->toBeNull();
});

test('updateTitle saves scene title', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $scene = Scene::factory()->for($chapter)->create(['title' => 'Old Title']);

    $this->patchJson(route('scenes.updateTitle', [$book, $chapter, $scene]), [
        'title' => 'New Title',
    ])
        ->assertOk()
        ->assertJsonFragment(['title' => 'New Title']);

    expect($scene->fresh()->title)->toBe('New Title');
});

test('reorder updates sort_order', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $s1 = Scene::factory()->for($chapter)->create(['sort_order' => 0, 'title' => 'A']);
    $s2 = Scene::factory()->for($chapter)->create(['sort_order' => 1, 'title' => 'B']);
    $s3 = Scene::factory()->for($chapter)->create(['sort_order' => 2, 'title' => 'C']);

    $this->postJson(route('scenes.reorder', [$book, $chapter]), [
        'order' => [$s3->id, $s1->id, $s2->id],
    ])->assertOk();

    expect($s3->fresh()->sort_order)->toBe(0);
    expect($s1->fresh()->sort_order)->toBe(1);
    expect($s2->fresh()->sort_order)->toBe(2);
});

test('destroy deletes scene and recalculates', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 300]);
    Scene::factory()->for($chapter)->create(['sort_order' => 0, 'word_count' => 200]);
    $toDelete = Scene::factory()->for($chapter)->create(['sort_order' => 1, 'word_count' => 100]);

    $this->deleteJson(route('scenes.destroy', [$book, $chapter, $toDelete]))
        ->assertOk();

    expect(Scene::find($toDelete->id))->toBeNull();
    expect($chapter->scenes()->count())->toBe(1);
    expect($chapter->fresh()->word_count)->toBe(200);
});

test('destroy prevents deleting last scene', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $scene = Scene::factory()->for($chapter)->create(['sort_order' => 0]);

    $this->deleteJson(route('scenes.destroy', [$book, $chapter, $scene]))
        ->assertUnprocessable();

    expect(Scene::find($scene->id))->not->toBeNull();
});

test('store creates default scene when creating chapter', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $this->post(route('chapters.store', $book), [
        'title' => 'New Chapter',
        'storyline_id' => $storyline->id,
    ])->assertRedirect();

    $chapter = $book->chapters()->where('title', 'New Chapter')->first();
    expect($chapter->scenes)->toHaveCount(1);
    expect($chapter->scenes->first()->title)->toBe('Scene 1');
});

test('store creates scene with initial content', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 0]);
    Scene::factory()->for($chapter)->create(['sort_order' => 0, 'word_count' => 0]);

    $this->postJson(route('scenes.store', [$book, $chapter]), [
        'title' => 'Split Scene',
        'position' => 1,
        'content' => '<p>The quick brown fox jumps over the lazy dog</p>',
    ])
        ->assertCreated()
        ->assertJsonFragment(['title' => 'Split Scene']);

    $scene = $chapter->scenes()->where('title', 'Split Scene')->first();
    expect($scene->content)->toBe('<p>The quick brown fox jumps over the lazy dog</p>');
    expect($scene->word_count)->toBe(9);
    expect($chapter->fresh()->word_count)->toBe(9);
});

test('split creates scene in new chapter', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 0]);
    ChapterVersion::factory()->for($chapter)->create(['is_current' => true]);

    $this->postJson(route('chapters.split', [$book, $chapter]), [
        'title' => 'Split Chapter',
        'initial_content' => '<p>Split content</p>',
    ])->assertSuccessful();

    $newChapter = $book->chapters()->where('title', 'Split Chapter')->first();
    expect($newChapter->scenes)->toHaveCount(1);
    expect($newChapter->scenes->first()->content)->toBe('<p>Split content</p>');
});

test('restore version replaces scenes', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $v1 = ChapterVersion::factory()->for($chapter)->create([
        'version_number' => 1,
        'content' => 'Original content',
        'is_current' => false,
    ]);
    ChapterVersion::factory()->for($chapter)->create([
        'version_number' => 2,
        'content' => 'Current content',
        'is_current' => true,
    ]);

    // Create multiple scenes
    Scene::factory()->for($chapter)->create(['sort_order' => 0, 'title' => 'Scene A']);
    Scene::factory()->for($chapter)->create(['sort_order' => 1, 'title' => 'Scene B']);

    $this->post(route('chapters.restoreVersion', [$book, $chapter, $v1]))
        ->assertRedirect();

    $chapter->refresh();
    expect($chapter->scenes)->toHaveCount(1);
    expect($chapter->scenes->first()->title)->toBe('Scene 1');
    expect($chapter->scenes->first()->content)->toBe('Original content');
});
