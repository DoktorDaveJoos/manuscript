<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\Storyline;
use App\Services\FreeTierLimits;

test('book creation gate sets flash error', function () {
    Book::factory()->create();

    $this->post(route('books.store'), [
        'title' => 'Blocked',
        'author' => 'Author',
        'language' => 'en',
    ])->assertRedirect(route('books.index'))
        ->assertSessionHas('error');
});

test('book duplicate gate sets flash error', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->post(route('books.duplicate', $book))
        ->assertRedirect(route('books.index'))
        ->assertSessionHas('error');
});

test('storyline creation gate sets flash error', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->post(route('storylines.store', $book), [
        'name' => 'Blocked',
    ])->assertRedirect(route('books.editor', $book))
        ->assertSessionHas('error');
});

test('character creation gate sets flash error', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();
    Character::factory()->count(FreeTierLimits::MAX_WIKI_ENTRIES)->for($book)->create();

    $this->post(route('characters.store', $book), [
        'name' => 'Blocked',
    ])->assertRedirect(route('books.wiki', ['book' => $book, 'tab' => 'characters']))
        ->assertSessionHas('error');
});

test('wiki entry creation gate sets flash error', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();
    Character::factory()->count(FreeTierLimits::MAX_WIKI_ENTRIES)->for($book)->create();

    $this->post(route('wikiEntries.store', $book), [
        'name' => 'Blocked',
        'kind' => 'location',
    ])->assertRedirect(route('books.wiki', ['book' => $book, 'tab' => 'location']))
        ->assertSessionHas('error');
});

test('export format gate returns 403 with message', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $this->postJson(route('books.settings.export.run', $book), [
        'format' => 'epub',
        'scope' => 'full',
        'chapter_id' => $chapter->id,
    ])->assertForbidden()
        ->assertJsonStructure(['message']);
});

test('pdf preview gate returns 403 with message', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $this->postJson(route('books.export.preview', $book), [
        'format' => 'pdf',
        'scope' => 'full',
        'chapter_id' => $chapter->id,
    ])->assertForbidden()
        ->assertJsonStructure(['message']);
});
