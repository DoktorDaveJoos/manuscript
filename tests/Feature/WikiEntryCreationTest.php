<?php

use App\Models\Book;
use App\Models\Character;
use App\Models\Storyline;
use App\Models\WikiEntry;

test('can create a character with name and description', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->post(route('characters.store', $book), [
        'name' => 'Gandalf',
        'description' => 'A wizard of great power',
    ])->assertRedirect();

    $this->assertDatabaseHas('characters', [
        'book_id' => $book->id,
        'name' => 'Gandalf',
        'description' => 'A wizard of great power',
        'is_ai_extracted' => false,
    ]);
});

test('can create a character with aliases and storylines', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $this->post(route('characters.store', $book), [
        'name' => 'Aragorn',
        'description' => 'Heir of Isildur',
        'aliases' => ['Strider', 'Elessar'],
        'storylines' => [$storyline->id],
    ])->assertRedirect();

    $character = Character::where('name', 'Aragorn')->first();
    expect($character)->not->toBeNull()
        ->and($character->aliases)->toBe(['Strider', 'Elessar'])
        ->and($character->storylines)->toBe([$storyline->id])
        ->and($character->is_ai_extracted)->toBeFalse();
});

test('character name is required', function () {
    $book = Book::factory()->create();

    $this->post(route('characters.store', $book), [
        'description' => 'No name provided',
    ])->assertSessionHasErrors('name');
});

test('can create wiki entries for each kind', function (string $kind, string $factoryState) {
    $book = Book::factory()->create();

    $this->post(route('wikiEntries.store', $book), [
        'name' => 'Test Entry',
        'kind' => $kind,
        'type' => 'TestType',
        'description' => 'A test entry',
    ])->assertRedirect();

    $this->assertDatabaseHas('wiki_entries', [
        'book_id' => $book->id,
        'name' => 'Test Entry',
        'kind' => $kind,
        'type' => 'TestType',
        'is_ai_extracted' => false,
    ]);
})->with([
    ['location', 'location'],
    ['organization', 'organization'],
    ['item', 'item'],
    ['lore', 'lore'],
]);

test('wiki entry name and kind are required', function () {
    $book = Book::factory()->create();

    $this->post(route('wikiEntries.store', $book), [
        'description' => 'No name or kind',
    ])->assertSessionHasErrors(['name', 'kind']);
});

test('can update an existing character', function () {
    $book = Book::factory()->create();
    $character = Character::factory()->for($book)->create(['name' => 'Old Name']);

    $this->patch(route('characters.update', [$book, $character]), [
        'name' => 'New Name',
        'description' => 'Updated description',
    ])->assertRedirect();

    $character->refresh();
    expect($character->name)->toBe('New Name')
        ->and($character->description)->toBe('Updated description');
});

test('can update an existing wiki entry', function () {
    $book = Book::factory()->create();
    $entry = WikiEntry::factory()->location()->for($book)->create(['name' => 'Old Place']);

    $this->patch(route('wikiEntries.update', [$book, $entry]), [
        'name' => 'New Place',
        'type' => 'Mountain',
        'description' => 'A tall mountain',
    ])->assertRedirect();

    $entry->refresh();
    expect($entry->name)->toBe('New Place')
        ->and($entry->type)->toBe('Mountain');
});

test('can delete a character', function () {
    $book = Book::factory()->create();
    $character = Character::factory()->for($book)->create();

    $this->delete(route('characters.destroy', [$book, $character]))
        ->assertRedirect();

    $this->assertSoftDeleted('characters', ['id' => $character->id]);
});

test('can delete a wiki entry', function () {
    $book = Book::factory()->create();
    $entry = WikiEntry::factory()->location()->for($book)->create();

    $this->delete(route('wikiEntries.destroy', [$book, $entry]))
        ->assertRedirect();

    $this->assertSoftDeleted('wiki_entries', ['id' => $entry->id]);
});

test('new entries have is_ai_extracted set to false', function () {
    $book = Book::factory()->create();

    $this->post(route('characters.store', $book), [
        'name' => 'Manual Character',
    ]);

    $this->post(route('wikiEntries.store', $book), [
        'name' => 'Manual Location',
        'kind' => 'location',
    ]);

    expect(Character::where('name', 'Manual Character')->first()->is_ai_extracted)->toBeFalse()
        ->and(WikiEntry::where('name', 'Manual Location')->first()->is_ai_extracted)->toBeFalse();
});
