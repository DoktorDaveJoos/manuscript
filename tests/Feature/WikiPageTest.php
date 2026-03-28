<?php

use App\Models\Book;
use App\Models\Character;
use App\Models\Storyline;
use App\Models\WikiEntry;

test('wiki page loads successfully with all props', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();
    Character::factory()->for($book)->create();
    WikiEntry::factory()->location()->for($book)->create();
    WikiEntry::factory()->organization()->for($book)->create();
    WikiEntry::factory()->item()->for($book)->create();
    WikiEntry::factory()->lore()->for($book)->create();

    $this->get(route('books.wiki', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('wiki/index')
            ->has('book')
            ->has('characters', 1)
            ->has('locations', 1)
            ->has('organizations', 1)
            ->has('items', 1)
            ->has('lore', 1)
            ->where('tab', 'characters')
        );
});

test('wiki page forwards tab query param', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->get(route('books.wiki', ['book' => $book, 'tab' => 'location']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('wiki/index')
            ->where('tab', 'location')
        );
});

test('wiki page includes character aliases and storylines in response', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    Character::factory()->for($book)->create([
        'name' => 'Aragorn',
        'aliases' => ['Strider', 'Elessar'],
        'storylines' => [$storyline->id],
    ]);

    $this->get(route('books.wiki', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('wiki/index')
            ->has('characters', 1)
            ->where('characters.0.name', 'Aragorn')
            ->where('characters.0.aliases', ['Strider', 'Elessar'])
            ->where('characters.0.storylines', [$storyline->id])
        );
});

test('wiki page loads with empty book', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->get(route('books.wiki', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('wiki/index')
            ->has('characters', 0)
            ->has('locations', 0)
            ->has('organizations', 0)
            ->has('items', 0)
            ->has('lore', 0)
        );
});
