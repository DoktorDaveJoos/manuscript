<?php

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Character;
use App\Models\PlotPoint;
use App\Models\Storyline;

test('shows empty state when no books exist', function () {
    $this->get(route('books.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('books/index')
            ->has('books', 0)
        );
});

test('lists existing books with counts', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();

    Chapter::factory()->count(2)->for($book)->for($storyline)->create();
    Chapter::factory()->revised()->for($book)->for($storyline)->create();
    Chapter::factory()->final()->for($book)->for($storyline)->create();

    $this->get(route('books.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('books/index')
            ->has('books', 1)
            ->where('books.0.chapters_count', 4)
            ->where('books.0.draft_chapters_count', 2)
            ->where('books.0.revised_chapters_count', 1)
            ->where('books.0.final_chapters_count', 1)
        );
});

test('creates a book with valid data and redirects to import', function () {
    $response = $this->post(route('books.store'), [
        'title' => 'My Novel',
        'author' => 'Jane Doe',
        'language' => 'en',
    ]);

    $book = Book::query()->where('title', 'My Novel')->first();

    $response->assertRedirect(route('books.import', $book));

    expect($book)->not->toBeNull()
        ->and($book->author)->toBe('Jane Doe')
        ->and($book->language)->toBe('en')
        ->and($book->storylines)->toHaveCount(0);
});

test('skip import creates default storyline and redirects to editor', function () {
    $book = Book::factory()->create();

    $this->post(route('books.import.skip', $book))
        ->assertRedirect(route('books.editor', $book));

    expect($book->storylines)->toHaveCount(1)
        ->and($book->storylines->first()->name)->toBe('Main');
});

test('validates title is required', function () {
    $this->post(route('books.store'), [
        'title' => '',
        'language' => 'en',
    ])->assertSessionHasErrors('title');
});

test('shows import page for a book', function () {
    $book = Book::factory()->create();

    $this->get(route('books.import', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('books/import')
            ->where('book.id', $book->id)
            ->has('book.storylines', 0)
        );
});

test('renames a book', function () {
    $book = Book::factory()->create(['title' => 'Old Title']);

    $this->patch(route('books.update', $book), [
        'title' => 'New Title',
    ])->assertRedirect(route('books.index'));

    expect($book->fresh()->title)->toBe('New Title');
});

test('rename validates title is required', function () {
    $book = Book::factory()->create();

    $this->patch(route('books.update', $book), [
        'title' => '',
    ])->assertSessionHasErrors('title');
});

test('deletes a book and cascades related data', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $act = Act::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $version = ChapterVersion::factory()->for($chapter)->create();
    $character = Character::factory()->for($book)->create();
    PlotPoint::factory()->for($book)->create(['act_id' => $act->id]);

    $this->delete(route('books.destroy', $book))
        ->assertRedirect(route('books.index'));

    expect(Book::find($book->id))->toBeNull()
        ->and(Storyline::find($storyline->id))->toBeNull()
        ->and(Act::find($act->id))->toBeNull()
        ->and(Chapter::find($chapter->id))->toBeNull()
        ->and(ChapterVersion::find($version->id))->toBeNull()
        ->and(Character::find($character->id))->toBeNull();
});

test('delete returns 404 for non-existent book', function () {
    $this->delete(route('books.destroy', 99999))
        ->assertNotFound();
});

test('duplicates a book with all relationships', function () {
    $book = Book::factory()->create(['title' => 'My Novel']);
    $storyline = Storyline::factory()->for($book)->create();
    $act = Act::factory()->for($book)->create();
    $character = Character::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['act_id' => $act->id]);
    ChapterVersion::factory()->for($chapter)->create();
    PlotPoint::factory()->for($book)->create([
        'act_id' => $act->id,
    ]);

    $this->post(route('books.duplicate', $book))
        ->assertRedirect(route('books.index'));

    $copy = Book::query()->where('title', 'My Novel (Copy)')->first();
    expect($copy)->not->toBeNull()
        ->and($copy->storylines)->toHaveCount(1)
        ->and($copy->acts)->toHaveCount(1)
        ->and($copy->characters)->toHaveCount(1)
        ->and($copy->chapters)->toHaveCount(1)
        ->and($copy->chapters->first()->versions)->toHaveCount(1)
        ->and($copy->plotPoints)->toHaveCount(1);

    // Verify foreign keys are remapped to new records
    $copyChapter = $copy->chapters->first();
    expect($copyChapter->storyline_id)->toBe($copy->storylines->first()->id)
        ->and($copyChapter->act_id)->toBe($copy->acts->first()->id);

    $copyPlotPoint = $copy->plotPoints->first();
    expect($copyPlotPoint->act_id)->toBe($copy->acts->first()->id);
});

test('duplicate resets AI-derived fields', function () {
    $book = Book::factory()->withAi()->create([
        'writing_style' => ['tone' => 'dark'],
        'story_bible' => ['setting' => 'Medieval'],
        'prose_pass_rules' => [['key' => 'test', 'enabled' => true]],
    ]);

    $this->post(route('books.duplicate', $book))
        ->assertRedirect(route('books.index'));

    $copy = Book::query()->where('title', 'like', '%(Copy)%')->first();
    expect($copy->writing_style)->toBeNull()
        ->and($copy->story_bible)->toBeNull()
        ->and($copy->prose_pass_rules)->toBeNull();
});
