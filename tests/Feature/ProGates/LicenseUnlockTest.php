<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\License;
use App\Models\Storyline;
use App\Services\FreeTierLimits;

test('activating license unlocks book creation', function () {
    Book::factory()->create();

    // Blocked without license
    $this->post(route('books.store'), [
        'title' => 'Second Book',
        'author' => 'Author',
        'language' => 'en',
    ])->assertRedirect(route('books.index'));

    expect(Book::count())->toBe(1);

    // Unlocked with license
    License::factory()->create();

    $this->post(route('books.store'), [
        'title' => 'Second Book',
        'author' => 'Author',
        'language' => 'en',
    ])->assertRedirect();

    expect(Book::count())->toBe(2);
});

test('activating license unlocks plot board', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    // Blocked without license
    $this->get(route('books.plot', $book))
        ->assertRedirect(route('settings.index'));

    // Unlocked with license
    License::factory()->create();

    $this->get(route('books.plot', $book))
        ->assertOk();
});

test('activating license unlocks pro export formats', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    // Blocked without license
    $this->postJson(route('books.settings.export.run', $book), [
        'format' => 'epub',
        'scope' => 'full',
        'chapter_id' => $chapter->id,
    ])->assertForbidden();

    // Unlocked with license
    License::factory()->create();

    $this->postJson(route('books.settings.export.run', $book), [
        'format' => 'epub',
        'scope' => 'full',
        'chapter_id' => $chapter->id,
    ])->assertSuccessful();
});

test('activating license unlocks storyline creation', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    // Blocked without license
    $this->post(route('storylines.store', $book), [
        'name' => 'Subplot',
    ])->assertRedirect(route('books.editor', $book));

    expect($book->storylines()->count())->toBe(1);

    // Unlocked with license
    License::factory()->create();

    $this->post(route('storylines.store', $book), [
        'name' => 'Subplot',
    ])->assertRedirect();

    expect($book->storylines()->count())->toBe(2);
});

test('activating license unlocks wiki entry creation past limit', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();
    Character::factory()->count(FreeTierLimits::MAX_WIKI_ENTRIES)->for($book)->create();

    // Blocked without license
    $this->post(route('characters.store', $book), [
        'name' => 'Over Limit',
    ])->assertRedirect(route('books.wiki', ['book' => $book, 'tab' => 'characters']));

    expect($book->characters()->count())->toBe(5);

    // Unlocked with license
    License::factory()->create();

    $this->post(route('characters.store', $book), [
        'name' => 'Over Limit',
    ])->assertRedirect();

    expect($book->characters()->count())->toBe(6);
});

test('activating license unlocks dashboard advanced features', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    // Free: no writing_goal
    $this->get(route('books.dashboard', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('writing_goal', null)
            ->where('manuscript_target', null)
        );

    // Pro: writing_goal present
    License::factory()->create();

    $this->get(route('books.dashboard', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('writing_goal')
            ->has('manuscript_target')
        );
});

test('free_tier prop disappears after license activation', function () {
    $this->get(route('books.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('free_tier'));

    License::factory()->create();

    $this->get(route('books.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('free_tier', null));
});
