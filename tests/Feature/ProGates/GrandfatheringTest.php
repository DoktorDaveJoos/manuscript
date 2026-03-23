<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\Storyline;
use App\Services\FreeTierLimits;

test('free user with multiple books can still access all of them', function () {
    $books = Book::factory()->count(3)->create();

    foreach ($books as $book) {
        Storyline::factory()->for($book)->create();
    }

    // Can view book index
    $this->get(route('books.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('books', 3));

    // Can access each book's dashboard
    foreach ($books as $book) {
        $this->get(route('books.dashboard', $book))->assertOk();
    }
});

test('free user with multiple storylines can still access editor', function () {
    $book = Book::factory()->create();
    $storylines = Storyline::factory()->count(3)->for($book)->create();

    $chapter = Chapter::factory()
        ->for($book)
        ->for($storylines->first())
        ->create();

    $chapter->versions()->create([
        'version_number' => 1,
        'content' => '<p>Hello</p>',
        'source' => 'original',
        'is_current' => true,
    ]);

    // Editor redirects to first chapter — assertRedirect is expected
    $this->get(route('books.editor', $book))->assertRedirect();
    $this->get(route('chapters.show', [$book, $chapter]))->assertOk();
});

test('free user with over-limit wiki entries can still view and edit them', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();
    $overLimit = FreeTierLimits::MAX_WIKI_ENTRIES + 1;
    $characters = Character::factory()->count($overLimit)->for($book)->create();

    // Can view wiki page with all entries
    $this->get(route('books.wiki', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('characters', $overLimit));

    // Can edit existing character
    $this->patch(route('characters.update', [$book, $characters->first()]), [
        'name' => 'Updated Name',
    ])->assertRedirect();

    expect($characters->first()->fresh()->name)->toBe('Updated Name');
});

test('free user with over-limit wiki entries cannot create new ones', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();
    Character::factory()->count(FreeTierLimits::MAX_WIKI_ENTRIES)->for($book)->create();

    $this->post(route('characters.store', $book), [
        'name' => 'Sixth Character',
    ])->assertRedirect(route('books.wiki', ['book' => $book, 'tab' => 'characters']));

    expect($book->characters()->count())->toBe(5);
});

test('free user can update and delete books when over limit', function () {
    $books = Book::factory()->count(3)->create();

    // Can update
    $this->patch(route('books.update', $books->first()), [
        'title' => 'Updated Title',
    ])->assertRedirect();

    expect($books->first()->fresh()->title)->toBe('Updated Title');

    // Can delete
    $this->delete(route('books.destroy', $books->last()))->assertRedirect();
    expect(Book::count())->toBe(2);
});
