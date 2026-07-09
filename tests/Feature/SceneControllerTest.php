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

test('updateContent rejects a stale expected_current_version_id with 409 and keeps the scene untouched', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $scene = Scene::factory()->for($chapter)->create(['content' => '<p>Revised by AI.</p>']);
    $current = ChapterVersion::factory()->for($chapter)->create([
        'version_number' => 2,
        'is_current' => true,
    ]);

    // A save retry composed against the pre-revision chapter must not
    // overwrite content the server has since replaced.
    $this->putJson(route('scenes.updateContent', [$book, $chapter, $scene]), [
        'content' => '<p>Stale text from before the revision.</p>',
        'expected_current_version_id' => $current->id - 1,
    ])->assertStatus(409);

    expect($scene->fresh()->content)->toBe('<p>Revised by AI.</p>');
});

test('updateContent accepts a matching expected_current_version_id', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $scene = Scene::factory()->for($chapter)->create(['content' => '<p>Old.</p>']);
    $current = ChapterVersion::factory()->for($chapter)->create([
        'version_number' => 2,
        'is_current' => true,
    ]);

    $this->putJson(route('scenes.updateContent', [$book, $chapter, $scene]), [
        'content' => '<p>New words.</p>',
        'expected_current_version_id' => $current->id,
    ])->assertOk();

    expect($scene->fresh()->content)->toBe('<p>New words.</p>');
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

test('updateContent does not create session on word decrease when none exists', function () {
    $book = Book::factory()->create(['daily_word_count_goal' => 100]);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 10]);
    $scene = Scene::factory()->for($chapter)->create(['word_count' => 10, 'content' => '<p>Hello world test words here and more stuff foo bar</p>']);

    $this->putJson(route('scenes.updateContent', [$book, $chapter, $scene]), [
        'content' => '<p>Hello</p>',
    ])->assertOk();

    expect(WritingSession::where('book_id', $book->id)->first())->toBeNull();
});

test('updateContent subtracts deletions from existing session', function () {
    $book = Book::factory()->create(['daily_word_count_goal' => 100]);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 0]);
    $scene = Scene::factory()->for($chapter)->create(['word_count' => 0, 'content' => '']);

    // Write 12 words today.
    $this->putJson(route('scenes.updateContent', [$book, $chapter, $scene]), [
        'content' => '<p>one two three four five six seven eight nine ten eleven twelve</p>',
    ])->assertOk();

    // Delete down to 5 words — net should be 5, not 12.
    $this->putJson(route('scenes.updateContent', [$book, $chapter, $scene]), [
        'content' => '<p>one two three four five</p>',
    ])->assertOk();

    $session = WritingSession::where('book_id', $book->id)->first();
    expect($session->words_written)->toBe(5);
});

test('updateContent clamps session at zero when deletions exceed words written today', function () {
    $book = Book::factory()->create(['daily_word_count_goal' => 100]);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 10]);
    $scene = Scene::factory()->for($chapter)->create(['word_count' => 10, 'content' => '<p>one two three four five six seven eight nine ten</p>']);
    WritingSession::factory()->for($book)->create([
        'date' => now()->toDateString(),
        'words_written' => 3,
    ]);

    // Wipe the scene — delta is -10, but session only has 3 words today.
    $this->putJson(route('scenes.updateContent', [$book, $chapter, $scene]), [
        'content' => '<p></p>',
    ])->assertOk();

    $session = WritingSession::where('book_id', $book->id)->first();
    expect($session->words_written)->toBe(0);
});

test('updateContent preserves goal_met when deletions drop count below goal', function () {
    $book = Book::factory()->create(['daily_word_count_goal' => 5]);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 10]);
    $scene = Scene::factory()->for($chapter)->create(['word_count' => 10, 'content' => '<p>one two three four five six seven eight nine ten</p>']);
    WritingSession::factory()->for($book)->goalMet()->create([
        'date' => now()->toDateString(),
        'words_written' => 10,
    ]);

    // Delete down to 2 words.
    $this->putJson(route('scenes.updateContent', [$book, $chapter, $scene]), [
        'content' => '<p>one two</p>',
    ])->assertOk();

    $session = WritingSession::where('book_id', $book->id)->first();
    expect($session->words_written)->toBe(2);
    expect($session->goal_met)->toBeTrue();
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

    // Single-segment snapshot without a scene_map: the first scene is
    // updated in place (id and title survive), the excess scene is removed.
    $chapter->refresh();
    expect($chapter->scenes)->toHaveCount(1);
    expect($chapter->scenes->first()->title)->toBe('Scene A');
    expect($chapter->scenes->first()->content)->toBe('Original content');
});
