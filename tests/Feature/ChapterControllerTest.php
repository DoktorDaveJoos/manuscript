<?php

use App\Enums\VersionStatus;
use App\Models\Beat;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\PlotPoint;
use App\Models\Scene;
use App\Models\Storyline;

test('editor renders editor page with first chapter as fallback', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $second = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 1, 'title' => 'Second']);
    $first = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 0, 'title' => 'First']);

    $this->get(route('books.editor', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('chapters/editor')
            ->where('book.id', $book->id)
            ->where('fallbackChapterId', $first->id)
            ->where('initialPanes', null)
        );
});

test('editor renders empty state when no chapters exist', function () {
    $book = Book::factory()->create();

    $this->get(route('books.editor', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('chapters/empty')
            ->where('book.id', $book->id)
        );
});

test('editor includes storylines in empty state response', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create(['name' => 'Main']);

    $this->get(route('books.editor', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('chapters/empty')
            ->has('book.storylines', 1)
            ->where('book.storylines.0.name', 'Main')
        );
});

test('show redirects to editor page with chapter as pane', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create(['is_current' => true, 'version_number' => 1]);
    ChapterVersion::factory()->for($chapter)->create(['is_current' => false, 'version_number' => 2]);

    $this->get(route('chapters.show', [$book, $chapter]))
        ->assertRedirect(route('books.editor', ['book' => $book, 'panes' => $chapter->id]));
});

test('updateContent saves content and returns word count', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 0]);
    ChapterVersion::factory()->for($chapter)->create(['is_current' => true, 'content' => '']);

    $this->putJson(route('chapters.updateContent', [$book, $chapter]), [
        'content' => '<p>The quick brown fox jumps over the lazy dog</p>',
    ])
        ->assertOk()
        ->assertJsonStructure(['word_count', 'saved_at']);

    $chapter->refresh();
    expect($chapter->word_count)->toBeGreaterThan(0);
    expect($chapter->currentVersion->content)->toContain('quick brown fox');
});

test('updateContent validates content is required', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create(['is_current' => true]);

    $this->putJson(route('chapters.updateContent', [$book, $chapter]), [
        'content' => '',
    ])->assertUnprocessable();
});

test('versions returns version list as json', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create(['version_number' => 1, 'is_current' => false]);
    ChapterVersion::factory()->for($chapter)->create(['version_number' => 2, 'is_current' => true]);

    $this->getJson(route('chapters.versions', [$book, $chapter]))
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonFragment(['version_number' => 2, 'is_current' => true]);
});

test('restoreVersion creates new current version from old version content', function () {
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

    $this->post(route('chapters.restoreVersion', [$book, $chapter, $v1]))
        ->assertRedirect();

    $chapter->refresh();
    $versions = $chapter->versions()->orderByDesc('version_number')->get();

    expect($versions)->toHaveCount(3);
    expect($versions->first()->version_number)->toBe(3);
    expect($versions->first()->content)->toBe('Original content');
    expect($versions->first()->is_current)->toBeTrue();
    expect($versions->where('version_number', 2)->first()->is_current)->toBeFalse();
});

test('updateTitle saves title and returns json', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['title' => 'Old Title']);

    $this->patchJson(route('chapters.updateTitle', [$book, $chapter]), [
        'title' => 'New Title',
    ])
        ->assertOk()
        ->assertJsonStructure(['title', 'saved_at'])
        ->assertJsonFragment(['title' => 'New Title']);

    $chapter->refresh();
    expect($chapter->title)->toBe('New Title');
});

test('updateTitle validates title is required', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $this->patchJson(route('chapters.updateTitle', [$book, $chapter]), [
        'title' => '',
    ])->assertUnprocessable();
});

test('updateTitle validates title max length', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $this->patchJson(route('chapters.updateTitle', [$book, $chapter]), [
        'title' => str_repeat('a', 1001),
    ])->assertUnprocessable();
});

test('store creates chapter with initial version and redirects', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $this->post(route('chapters.store', $book), [
        'title' => 'My First Chapter',
        'storyline_id' => $storyline->id,
    ])->assertRedirect();

    $chapter = $book->chapters()->first();

    expect($chapter)->not->toBeNull();
    expect($chapter->title)->toBe('My First Chapter');
    expect($chapter->storyline_id)->toBe($storyline->id);
    expect($chapter->word_count)->toBe(0);
    expect($chapter->status->value)->toBe('draft');

    $version = $chapter->currentVersion;
    expect($version)->not->toBeNull();
    expect($version->version_number)->toBe(1);
    expect($version->content)->toBe('');
    expect($version->source->value)->toBe('original');
    expect($version->is_current)->toBeTrue();
});

test('store calculates correct reader_order sequence', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();

    Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 0]);
    Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 1]);

    $this->post(route('chapters.store', $book), [
        'title' => 'Third Chapter',
        'storyline_id' => $storyline->id,
    ])->assertRedirect();

    $newChapter = $book->chapters()->where('title', 'Third Chapter')->first();
    expect($newChapter->reader_order)->toBe(2);
});

test('store links chapter to beat when beat_id is provided', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $plotPoint = PlotPoint::factory()->for($book)->create();
    $beat = Beat::factory()->for($plotPoint)->create();

    $this->post(route('chapters.store', $book), [
        'title' => 'Beat Chapter',
        'storyline_id' => $storyline->id,
        'beat_id' => $beat->id,
    ])->assertRedirect();

    $chapter = $book->chapters()->where('title', 'Beat Chapter')->first();

    expect($chapter)->not->toBeNull();
    expect($beat->chapters()->where('chapter_id', $chapter->id)->exists())->toBeTrue();
});

test('store validates title is required', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $this->post(route('chapters.store', $book), [
        'title' => '',
        'storyline_id' => $storyline->id,
    ])->assertSessionHasErrors('title');
});

test('store rejects storyline from another book', function () {
    $book = Book::factory()->create();
    $otherBook = Book::factory()->create();
    $otherStoryline = Storyline::factory()->for($otherBook)->create();

    $this->post(route('chapters.store', $book), [
        'title' => 'Chapter 1',
        'storyline_id' => $otherStoryline->id,
    ])->assertNotFound();
});

test('split creates new chapter after current with initial content', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 0]);
    ChapterVersion::factory()->for($chapter)->create(['is_current' => true]);

    $this->postJson(route('chapters.split', [$book, $chapter]), [
        'title' => 'Split Chapter',
        'initial_content' => '<p>Content from split</p>',
    ])->assertSuccessful();

    $newChapter = $book->chapters()->where('title', 'Split Chapter')->first();

    expect($newChapter)->not->toBeNull();
    expect($newChapter->reader_order)->toBe(1);
    expect($newChapter->storyline_id)->toBe($storyline->id);
    expect($newChapter->status->value)->toBe('draft');
    expect($newChapter->word_count)->toBe(3);
});

test('split shifts reader_order of subsequent chapters', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $ch0 = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 0, 'title' => 'Ch 0']);
    $ch1 = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 1, 'title' => 'Ch 1']);
    $ch2 = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 2, 'title' => 'Ch 2']);
    ChapterVersion::factory()->for($ch1)->create(['is_current' => true]);

    $this->postJson(route('chapters.split', [$book, $ch1]), [
        'title' => 'Inserted',
    ])->assertSuccessful();

    expect($ch0->fresh()->reader_order)->toBe(0);
    expect($ch1->fresh()->reader_order)->toBe(1);
    expect($ch2->fresh()->reader_order)->toBe(3);

    $inserted = $book->chapters()->where('title', 'Inserted')->first();
    expect($inserted->reader_order)->toBe(2);
});

test('split stores initial content in version', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 0]);
    ChapterVersion::factory()->for($chapter)->create(['is_current' => true]);

    $this->postJson(route('chapters.split', [$book, $chapter]), [
        'title' => 'New',
        'initial_content' => '<p>Hello world</p>',
    ])->assertSuccessful();

    $newChapter = $book->chapters()->where('title', 'New')->first();
    $version = $newChapter->currentVersion;

    expect($version)->not->toBeNull();
    expect($version->version_number)->toBe(1);
    expect($version->content)->toBe('<p>Hello world</p>');
    expect($version->source->value)->toBe('original');
    expect($version->is_current)->toBeTrue();
});

test('split validates title is required', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create(['is_current' => true]);

    $this->postJson(route('chapters.split', [$book, $chapter]), [
        'title' => '',
    ])->assertUnprocessable();
});

test('split accepts empty initial_content', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 0]);
    ChapterVersion::factory()->for($chapter)->create(['is_current' => true]);

    $this->postJson(route('chapters.split', [$book, $chapter]), [
        'title' => 'Empty Split',
    ])->assertSuccessful();

    $newChapter = $book->chapters()->where('title', 'Empty Split')->first();
    expect($newChapter)->not->toBeNull();
    expect($newChapter->word_count)->toBe(0);
    expect($newChapter->currentVersion->content)->toBe('');
});

test('split returns json with chapter_id and url', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 0]);
    ChapterVersion::factory()->for($chapter)->create(['is_current' => true]);

    $this->postJson(route('chapters.split', [$book, $chapter]), [
        'title' => 'JSON Test',
    ])
        ->assertSuccessful()
        ->assertJsonStructure(['chapter_id', 'url']);
});

test('destroy soft-deletes chapter and recompacts reader_order', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $ch0 = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 0]);
    $ch1 = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 1]);
    $ch2 = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 2]);

    $this->delete(route('chapters.destroy', [$book, $ch1]))
        ->assertRedirect(route('chapters.show', [$book, $ch0]));

    expect(Chapter::find($ch1->id))->toBeNull();
    expect(Chapter::withTrashed()->find($ch1->id))->not->toBeNull();
    expect($ch0->fresh()->reader_order)->toBe(0);
    expect($ch2->fresh()->reader_order)->toBe(1);
});

test('destroy redirects to empty state when last chapter deleted', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 0]);

    $this->delete(route('chapters.destroy', [$book, $chapter]))
        ->assertRedirect(route('books.editor', $book));

    expect(Chapter::find($chapter->id))->toBeNull();
});

test('destroy soft-deletes chapter but versions remain', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 0]);
    $v1 = ChapterVersion::factory()->for($chapter)->create(['is_current' => true]);
    $v2 = ChapterVersion::factory()->for($chapter)->create(['is_current' => false]);

    $this->delete(route('chapters.destroy', [$book, $chapter]));

    expect(Chapter::find($chapter->id))->toBeNull();
    expect(Chapter::withTrashed()->find($chapter->id))->not->toBeNull();
    // Versions still exist because soft delete doesn't trigger DB CASCADE
    expect(ChapterVersion::find($v1->id))->not->toBeNull();
    expect(ChapterVersion::find($v2->id))->not->toBeNull();
});

test('updateStatus changes status', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['status' => 'draft']);

    $this->patchJson(route('chapters.updateStatus', [$book, $chapter]), [
        'status' => 'revised',
    ])
        ->assertOk()
        ->assertJsonFragment(['status' => 'revised']);

    expect($chapter->fresh()->status->value)->toBe('revised');
});

test('updateStatus validates status value', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $this->patchJson(route('chapters.updateStatus', [$book, $chapter]), [
        'status' => 'invalid',
    ])->assertUnprocessable();
});

test('reorder updates reader_order and storyline_id', function () {
    $book = Book::factory()->create();
    $s1 = Storyline::factory()->for($book)->create(['sort_order' => 0]);
    $s2 = Storyline::factory()->for($book)->create(['sort_order' => 1]);

    $ch0 = Chapter::factory()->for($book)->for($s1)->create(['reader_order' => 0]);
    $ch1 = Chapter::factory()->for($book)->for($s1)->create(['reader_order' => 1]);
    $ch2 = Chapter::factory()->for($book)->for($s2)->create(['reader_order' => 2]);

    $this->postJson(route('chapters.reorder', $book), [
        'order' => [
            ['id' => $ch2->id, 'storyline_id' => $s1->id],
            ['id' => $ch0->id, 'storyline_id' => $s1->id],
            ['id' => $ch1->id, 'storyline_id' => $s2->id],
        ],
    ])->assertOk();

    expect($ch2->fresh()->reader_order)->toBe(0);
    expect($ch2->fresh()->storyline_id)->toBe($s1->id);
    expect($ch0->fresh()->reader_order)->toBe(1);
    expect($ch1->fresh()->reader_order)->toBe(2);
    expect($ch1->fresh()->storyline_id)->toBe($s2->id);
});

test('reorder validates order is required', function () {
    $book = Book::factory()->create();

    $this->postJson(route('chapters.reorder', $book), [])
        ->assertUnprocessable();
});

test('updateNotes saves notes and returns json', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['notes' => null]);

    $this->patchJson(route('chapters.updateNotes', [$book, $chapter]), [
        'notes' => 'Remember to foreshadow the villain here.',
    ])
        ->assertOk()
        ->assertJsonStructure(['saved_at']);

    $chapter->refresh();
    expect($chapter->notes)->toBe('Remember to foreshadow the villain here.');
});

test('updateNotes accepts null to clear notes', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['notes' => 'Some notes']);

    $this->patchJson(route('chapters.updateNotes', [$book, $chapter]), [
        'notes' => null,
    ])
        ->assertOk();

    $chapter->refresh();
    expect($chapter->notes)->toBeNull();
});

test('updateNotes validates max length', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $this->patchJson(route('chapters.updateNotes', [$book, $chapter]), [
        'notes' => str_repeat('a', 10001),
    ])->assertUnprocessable();
});

test('notes survive round-trip through showJson endpoint', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['notes' => null]);

    $this->patchJson(route('chapters.updateNotes', [$book, $chapter]), [
        'notes' => 'Round-trip test note',
    ])->assertOk();

    $this->getJson(route('chapters.show.json', [$book, $chapter]))
        ->assertOk()
        ->assertJsonPath('chapter.notes', 'Round-trip test note');
});

test('createSnapshot creates new version with snapshot source', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => 'Old content',
    ]);
    Scene::factory()->for($chapter)->create([
        'content' => '<p>Scene content here</p>',
        'sort_order' => 0,
    ]);

    $this->postJson(route('chapters.createSnapshot', [$book, $chapter]), [
        'change_summary' => 'Before restructuring',
    ])->assertOk();

    $chapter->refresh();
    $versions = $chapter->versions()->orderByDesc('version_number')->get();

    expect($versions)->toHaveCount(2);
    expect($versions->first()->version_number)->toBe(2);
    expect($versions->first()->source->value)->toBe('snapshot');
    expect($versions->first()->change_summary)->toBe('Before restructuring');
    expect($versions->first()->is_current)->toBeTrue();
    expect($versions->first()->content)->toContain('Scene content here');
    expect($versions->where('version_number', 1)->first()->is_current)->toBeFalse();
});

test('createSnapshot syncs outgoing version content from scenes', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $v1 = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => '',
    ]);
    Scene::factory()->for($chapter)->create([
        'content' => '<p>User has written content</p>',
        'sort_order' => 0,
    ]);

    $this->postJson(route('chapters.createSnapshot', [$book, $chapter]))
        ->assertOk();

    $v1->refresh();
    expect($v1->content)->toBe('<p>User has written content</p>');
});

test('restoreVersion syncs current version content before restoring', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $v1 = ChapterVersion::factory()->for($chapter)->create([
        'version_number' => 1,
        'content' => '<p>Original</p>',
        'is_current' => false,
    ]);
    $v2 = ChapterVersion::factory()->for($chapter)->create([
        'version_number' => 2,
        'content' => '',
        'is_current' => true,
    ]);
    Scene::factory()->for($chapter)->create([
        'content' => '<p>Edited since v2 was created</p>',
        'sort_order' => 0,
    ]);

    $this->post(route('chapters.restoreVersion', [$book, $chapter, $v1]))
        ->assertRedirect();

    $v2->refresh();
    expect($v2->content)->toBe('<p>Edited since v2 was created</p>');
});

test('createSnapshot validates change_summary max length', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create(['is_current' => true]);

    $this->postJson(route('chapters.createSnapshot', [$book, $chapter]), [
        'change_summary' => str_repeat('a', 256),
    ])->assertUnprocessable();
});

test('createSnapshot uses scene content for version content', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create(['is_current' => true, 'version_number' => 1]);
    Scene::factory()->for($chapter)->create(['content' => '<p>First scene</p>', 'sort_order' => 0]);
    Scene::factory()->for($chapter)->create(['content' => '<p>Second scene</p>', 'sort_order' => 1]);

    $this->postJson(route('chapters.createSnapshot', [$book, $chapter]))
        ->assertOk();

    $latest = $chapter->versions()->orderByDesc('version_number')->first();
    expect($latest->content)->toBe("<p>First scene</p>\n<p>Second scene</p>");
});

test('destroyVersion deletes non-current version', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $v1 = ChapterVersion::factory()->for($chapter)->create(['is_current' => false, 'version_number' => 1]);
    ChapterVersion::factory()->for($chapter)->create(['is_current' => true, 'version_number' => 2]);

    $this->deleteJson(route('chapters.destroyVersion', [$book, $chapter, $v1]))
        ->assertOk();

    expect(ChapterVersion::find($v1->id))->toBeNull();
    expect($chapter->versions()->count())->toBe(1);
});

test('destroyVersion rejects deleting current version', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create(['is_current' => false, 'version_number' => 1]);
    $current = ChapterVersion::factory()->for($chapter)->create(['is_current' => true, 'version_number' => 2]);

    $this->deleteJson(route('chapters.destroyVersion', [$book, $chapter, $current]))
        ->assertForbidden();
});

test('destroyVersion rejects deleting last version', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $only = ChapterVersion::factory()->for($chapter)->create(['is_current' => false, 'version_number' => 1]);

    $this->deleteJson(route('chapters.destroyVersion', [$book, $chapter, $only]))
        ->assertForbidden();
});

test('chapter version has status column defaulting to accepted', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $version = ChapterVersion::factory()->for($chapter)->create(['is_current' => true]);

    expect($version->fresh()->status->value)->toBe('accepted');
});

test('acceptVersion promotes pending version to current', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 10]);

    $current = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => '<p>Original text</p>',
        'status' => VersionStatus::Accepted,
    ]);

    Scene::factory()->for($chapter)->create(['content' => '<p>Original text</p>', 'sort_order' => 0]);

    $pending = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => false,
        'version_number' => 2,
        'content' => '<p>Beautified text here</p>',
        'source' => 'beautify',
        'status' => VersionStatus::Pending,
    ]);

    $this->postJson(route('chapters.acceptVersion', [$book, $chapter, $pending]))
        ->assertOk();

    expect($current->fresh()->is_current)->toBeFalse();
    expect($pending->fresh()->is_current)->toBeTrue();
    expect($pending->fresh()->status->value)->toBe('accepted');

    $chapter->refresh();
    $chapter->load('scenes');
    expect($chapter->scenes)->toHaveCount(1);
    expect($chapter->scenes->first()->content)->toBe('<p>Beautified text here</p>');
    expect($chapter->word_count)->toBe(3);
});

test('acceptVersion rejects non-pending version', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $version = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'status' => VersionStatus::Accepted,
    ]);

    $this->postJson(route('chapters.acceptVersion', [$book, $chapter, $version]))
        ->assertForbidden();
});

test('rejectVersion deletes pending version', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $current = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'status' => VersionStatus::Accepted,
    ]);

    $pending = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => false,
        'version_number' => 2,
        'status' => VersionStatus::Pending,
    ]);

    $this->postJson(route('chapters.rejectVersion', [$book, $chapter, $pending]))
        ->assertOk();

    expect(ChapterVersion::find($pending->id))->toBeNull();
    expect($current->fresh()->is_current)->toBeTrue();
});

test('rejectVersion rejects non-pending version', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $version = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'status' => VersionStatus::Accepted,
    ]);

    $this->postJson(route('chapters.rejectVersion', [$book, $chapter, $version]))
        ->assertForbidden();
});

test('show redirects to editor with chapter as pane (pending version)', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'status' => VersionStatus::Accepted,
    ]);
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => false,
        'version_number' => 2,
        'status' => VersionStatus::Pending,
        'content' => 'Pending content',
        'source' => 'beautify',
        'change_summary' => 'AI text beautification',
    ]);

    $this->get(route('chapters.show', [$book, $chapter]))
        ->assertRedirect(route('books.editor', ['book' => $book, 'panes' => $chapter->id]));
});

test('show redirects to editor with chapter as pane (no pending version)', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'status' => VersionStatus::Accepted,
    ]);

    $this->get(route('chapters.show', [$book, $chapter]))
        ->assertRedirect(route('books.editor', ['book' => $book, 'panes' => $chapter->id]));
});

test('show redirects to editor (notes in chapter)', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['notes' => 'Test note']);
    ChapterVersion::factory()->for($chapter)->create(['is_current' => true]);

    $this->get(route('chapters.show', [$book, $chapter]))
        ->assertRedirect(route('books.editor', ['book' => $book, 'panes' => $chapter->id]));
});

test('show redirects to editor (prose pass rules via json endpoint)', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create(['is_current' => true]);

    $this->get(route('chapters.show', [$book, $chapter]))
        ->assertRedirect(route('books.editor', ['book' => $book, 'panes' => $chapter->id]));
});

test('acceptVersion preserves scenes with hr boundaries', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 10]);

    $current = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => '<p>Scene one text</p>',
        'status' => VersionStatus::Accepted,
    ]);

    Scene::factory()->for($chapter)->create(['title' => 'Opening', 'content' => '<p>Scene one text</p>', 'sort_order' => 0]);
    Scene::factory()->for($chapter)->create(['title' => 'Climax', 'content' => '<p>Scene two text</p>', 'sort_order' => 1]);
    Scene::factory()->for($chapter)->create(['title' => 'Denouement', 'content' => '<p>Scene three text</p>', 'sort_order' => 2]);

    $pending = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => false,
        'version_number' => 2,
        'content' => '<p>Revised scene one</p><hr><p>Revised scene two</p><hr><p>Revised scene three</p>',
        'source' => 'ai_revision',
        'status' => VersionStatus::Pending,
        'scene_map' => [
            ['title' => 'Opening', 'sort_order' => 0],
            ['title' => 'Climax', 'sort_order' => 1],
            ['title' => 'Denouement', 'sort_order' => 2],
        ],
    ]);

    $this->postJson(route('chapters.acceptVersion', [$book, $chapter, $pending]))
        ->assertOk();

    $chapter->refresh();
    $chapter->load('scenes');

    expect($chapter->scenes)->toHaveCount(3);
    expect($chapter->scenes[0]->title)->toBe('Opening');
    expect($chapter->scenes[0]->content)->toBe('<p>Revised scene one</p>');
    expect($chapter->scenes[1]->title)->toBe('Climax');
    expect($chapter->scenes[1]->content)->toBe('<p>Revised scene two</p>');
    expect($chapter->scenes[2]->title)->toBe('Denouement');
    expect($chapter->scenes[2]->content)->toBe('<p>Revised scene three</p>');
});

test('acceptVersion falls back to single scene when no hr tags', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 5]);

    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => '<p>Original</p>',
        'status' => VersionStatus::Accepted,
    ]);

    Scene::factory()->for($chapter)->create(['content' => '<p>Original</p>', 'sort_order' => 0]);

    $pending = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => false,
        'version_number' => 2,
        'content' => '<p>Revised text without scene breaks</p>',
        'source' => 'ai_revision',
        'status' => VersionStatus::Pending,
        'scene_map' => [['title' => 'Scene 1', 'sort_order' => 0]],
    ]);

    $this->postJson(route('chapters.acceptVersion', [$book, $chapter, $pending]))
        ->assertOk();

    $chapter->refresh();
    $chapter->load('scenes');

    expect($chapter->scenes)->toHaveCount(1);
    expect($chapter->scenes->first()->content)->toBe('<p>Revised text without scene breaks</p>');
});

test('acceptPartialVersion saves provided content', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 5]);

    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => '<p>Original</p>',
        'status' => VersionStatus::Accepted,
    ]);

    Scene::factory()->for($chapter)->create(['content' => '<p>Original</p>', 'sort_order' => 0]);

    $pending = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => false,
        'version_number' => 2,
        'content' => '<p>Full revision</p>',
        'source' => 'ai_revision',
        'status' => VersionStatus::Pending,
    ]);

    $mergedContent = '<p>Partially merged content</p>';

    $this->postJson(route('chapters.acceptPartialVersion', [$book, $chapter, $pending]), [
        'content' => $mergedContent,
    ])->assertOk();

    expect($pending->fresh()->is_current)->toBeTrue();
    expect($pending->fresh()->status->value)->toBe('accepted');
    expect($pending->fresh()->content)->toBe($mergedContent);

    $chapter->refresh();
    $chapter->load('scenes');
    expect($chapter->scenes->first()->content)->toBe($mergedContent);
});

test('acceptPartialVersion rejects non-pending version', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $version = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'status' => VersionStatus::Accepted,
    ]);

    $this->postJson(route('chapters.acceptPartialVersion', [$book, $chapter, $version]), [
        'content' => '<p>Anything</p>',
    ])->assertForbidden();
});

test('acceptPartialVersion validates content is required', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $pending = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => false,
        'version_number' => 2,
        'status' => VersionStatus::Pending,
    ]);

    $this->postJson(route('chapters.acceptPartialVersion', [$book, $chapter, $pending]), [
        'content' => '',
    ])->assertUnprocessable();
});

test('acceptPartialVersion preserves scenes with hr tags', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 10]);

    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'status' => VersionStatus::Accepted,
    ]);

    Scene::factory()->for($chapter)->create(['title' => 'Act I', 'content' => '<p>First</p>', 'sort_order' => 0]);
    Scene::factory()->for($chapter)->create(['title' => 'Act II', 'content' => '<p>Second</p>', 'sort_order' => 1]);

    $pending = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => false,
        'version_number' => 2,
        'status' => VersionStatus::Pending,
        'scene_map' => [
            ['title' => 'Act I', 'sort_order' => 0],
            ['title' => 'Act II', 'sort_order' => 1],
        ],
    ]);

    $this->postJson(route('chapters.acceptPartialVersion', [$book, $chapter, $pending]), [
        'content' => '<p>Merged first</p><hr><p>Merged second</p>',
    ])->assertOk();

    $chapter->refresh();
    $chapter->load('scenes');

    expect($chapter->scenes)->toHaveCount(2);
    expect($chapter->scenes[0]->title)->toBe('Act I');
    expect($chapter->scenes[0]->content)->toBe('<p>Merged first</p>');
    expect($chapter->scenes[1]->title)->toBe('Act II');
    expect($chapter->scenes[1]->content)->toBe('<p>Merged second</p>');
});

test('getContentWithSceneBreaks joins scenes with hr tags', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    Scene::factory()->for($chapter)->create(['content' => '<p>Scene one</p>', 'sort_order' => 0]);
    Scene::factory()->for($chapter)->create(['content' => '<p>Scene two</p>', 'sort_order' => 1]);

    $chapter->load('scenes');

    expect($chapter->getContentWithSceneBreaks())->toBe('<p>Scene one</p><hr><p>Scene two</p>');
});

test('replaceSceneContents handles fewer segments than existing scenes', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    Scene::factory()->for($chapter)->create(['title' => 'One', 'content' => '<p>One</p>', 'sort_order' => 0]);
    Scene::factory()->for($chapter)->create(['title' => 'Two', 'content' => '<p>Two</p>', 'sort_order' => 1]);
    Scene::factory()->for($chapter)->create(['title' => 'Three', 'content' => '<p>Three</p>', 'sort_order' => 2]);

    $sceneMap = [
        ['title' => 'Combined', 'sort_order' => 0],
        ['title' => 'Final', 'sort_order' => 1],
    ];

    $chapter->replaceSceneContents('<p>Combined content</p><hr><p>Final content</p>', $sceneMap);

    $chapter->refresh();
    $chapter->load('scenes');

    expect($chapter->scenes)->toHaveCount(2);
    expect($chapter->scenes[0]->title)->toBe('Combined');
    expect($chapter->scenes[1]->title)->toBe('Final');
});
