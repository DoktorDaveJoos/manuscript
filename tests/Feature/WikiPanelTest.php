<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\WikiEntry;

test('panel index returns connected entries for chapter', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();
    $character = Character::factory()->for($book)->create();
    $entry = WikiEntry::factory()->for($book)->location()->create();

    $character->chapters()->attach($chapter, ['role' => 'protagonist']);
    $entry->chapters()->attach($chapter);

    $response = $this->getJson("/books/{$book->id}/wiki/panel?chapter_id={$chapter->id}");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'connected.characters')
        ->assertJsonCount(1, 'connected.entries')
        ->assertJsonPath('connected.characters.0.id', $character->id)
        ->assertJsonPath('connected.entries.0.id', $entry->id);
});

test('panel index returns search results when query provided', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();
    $character = Character::factory()->for($book)->create(['name' => 'Elena Voss']);
    $entry = WikiEntry::factory()->for($book)->location()->create(['name' => 'Fortress of Winds']);

    $response = $this->getJson("/books/{$book->id}/wiki/panel?chapter_id={$chapter->id}&q=Elena");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'search_results');
});

test('panel search excludes already connected entries', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();
    $character = Character::factory()->for($book)->create(['name' => 'Elena Voss']);
    $character->chapters()->attach($chapter, ['role' => 'mentioned']);

    $response = $this->getJson("/books/{$book->id}/wiki/panel?chapter_id={$chapter->id}&q=Elena");

    $response->assertSuccessful()
        ->assertJsonCount(0, 'search_results');
});

test('connect character to chapter', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();
    $character = Character::factory()->for($book)->create();

    $response = $this->postJson("/books/{$book->id}/wiki/panel/connect", [
        'chapter_id' => $chapter->id,
        'type' => 'character',
        'id' => $character->id,
        'role' => 'supporting',
    ]);

    $response->assertSuccessful();
    expect($character->chapters()->where('chapter_id', $chapter->id)->exists())->toBeTrue()
        ->and($character->chapters()->first()->pivot->role)->toBe('supporting');
});

test('connect wiki entry to chapter', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();
    $entry = WikiEntry::factory()->for($book)->lore()->create();

    $response = $this->postJson("/books/{$book->id}/wiki/panel/connect", [
        'chapter_id' => $chapter->id,
        'type' => 'wiki_entry',
        'id' => $entry->id,
    ]);

    $response->assertSuccessful();
    expect($entry->chapters()->where('chapter_id', $chapter->id)->exists())->toBeTrue();
});

test('disconnect character from chapter', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();
    $character = Character::factory()->for($book)->create();
    $character->chapters()->attach($chapter, ['role' => 'mentioned']);

    $response = $this->postJson("/books/{$book->id}/wiki/panel/disconnect", [
        'chapter_id' => $chapter->id,
        'type' => 'character',
        'id' => $character->id,
    ]);

    $response->assertSuccessful();
    expect($character->chapters()->where('chapter_id', $chapter->id)->exists())->toBeFalse();
});

test('disconnect wiki entry from chapter', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();
    $entry = WikiEntry::factory()->for($book)->location()->create();
    $entry->chapters()->attach($chapter);

    $response = $this->postJson("/books/{$book->id}/wiki/panel/disconnect", [
        'chapter_id' => $chapter->id,
        'type' => 'wiki_entry',
        'id' => $entry->id,
    ]);

    $response->assertSuccessful();
    expect($entry->chapters()->where('chapter_id', $chapter->id)->exists())->toBeFalse();
});

test('update character role for chapter', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();
    $character = Character::factory()->for($book)->create();
    $character->chapters()->attach($chapter, ['role' => 'mentioned']);

    $response = $this->patchJson("/books/{$book->id}/wiki/panel/characters/{$character->id}/role", [
        'chapter_id' => $chapter->id,
        'role' => 'protagonist',
    ]);

    $response->assertSuccessful();
    expect($character->chapters()->first()->pivot->role)->toBe('protagonist');
});
