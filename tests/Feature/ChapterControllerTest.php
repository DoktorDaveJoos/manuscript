<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Storyline;

test('editor redirects to first chapter by reader_order', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $second = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 1, 'title' => 'Second']);
    $first = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 0, 'title' => 'First']);

    $this->get(route('books.editor', $book))
        ->assertRedirect(route('chapters.show', [$book, $first]));
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

test('show renders chapter with book, storylines, and version count', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create(['is_current' => true, 'version_number' => 1]);
    ChapterVersion::factory()->for($chapter)->create(['is_current' => false, 'version_number' => 2]);

    $this->get(route('chapters.show', [$book, $chapter]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('chapters/show')
            ->where('book.id', $book->id)
            ->has('book.storylines', 1)
            ->where('chapter.id', $chapter->id)
            ->has('chapter.current_version')
            ->where('versionCount', 2)
        );
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
        'title' => str_repeat('a', 256),
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
