<?php

use App\Models\Book;
use App\Models\Character;
use App\Models\License;
use App\Models\Storyline;
use App\Models\WikiEntry;

test('free user can create characters under limit', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->post(route('characters.store', $book), [
        'name' => 'Alice',
    ])->assertRedirect();

    expect($book->characters()->count())->toBe(1);
});

test('free user cannot create character when at limit', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();
    Character::factory()->count(3)->for($book)->create();
    WikiEntry::factory()->count(2)->for($book)->create();

    $this->post(route('characters.store', $book), [
        'name' => 'Over Limit',
    ])->assertRedirect(route('books.wiki', ['book' => $book, 'tab' => 'characters']));

    expect($book->characters()->count())->toBe(3);
});

test('free user cannot create wiki entry when at limit', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();
    Character::factory()->count(5)->for($book)->create();

    $this->post(route('wikiEntries.store', $book), [
        'name' => 'Some Place',
        'kind' => 'location',
    ])->assertRedirect(route('books.wiki', ['book' => $book, 'tab' => 'location']));

    expect($book->wikiEntries()->count())->toBe(0);
});

test('combined count of characters and wiki entries is checked', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();
    Character::factory()->count(2)->for($book)->create();
    WikiEntry::factory()->count(2)->for($book)->create();

    // 4 total, should still be able to add one more
    $this->post(route('characters.store', $book), [
        'name' => 'Fifth Entry',
    ])->assertRedirect();

    expect($book->characters()->count())->toBe(3);

    // 5 total, should be blocked
    $this->post(route('wikiEntries.store', $book), [
        'name' => 'Sixth Entry',
        'kind' => 'location',
    ])->assertRedirect(route('books.wiki', ['book' => $book, 'tab' => 'location']));

    expect($book->wikiEntries()->count())->toBe(2);
});

test('pro user can create unlimited entries', function () {
    License::factory()->create();
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();
    Character::factory()->count(5)->for($book)->create();

    $this->post(route('characters.store', $book), [
        'name' => 'Sixth Character',
    ])->assertRedirect();

    expect($book->characters()->count())->toBe(6);
});

test('existing entries remain accessible when over limit', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();
    Character::factory()->count(10)->for($book)->create();

    $this->get(route('books.wiki', $book))->assertOk();
});
