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

test('panel index includes the POV character for the chapter', function () {
    $book = Book::factory()->create();
    $povCharacter = Character::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->create([
        'pov_character_id' => $povCharacter->id,
    ]);

    $response = $this->getJson("/books/{$book->id}/wiki/panel?chapter_id={$chapter->id}");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'connected.characters')
        ->assertJsonPath('connected.characters.0.id', $povCharacter->id);

    expect($povCharacter->chapters()->where('chapter_id', $chapter->id)->first()?->pivot?->role)
        ->toBe('protagonist');
});

test('updating pov_character_id attaches the new POV to the supporting cast pivot', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();
    $povCharacter = Character::factory()->for($book)->create();

    $chapter->update(['pov_character_id' => $povCharacter->id]);

    expect($povCharacter->chapters()->where('chapter_id', $chapter->id)->first()?->pivot?->role)
        ->toBe('protagonist');
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

test('promoting a character to protagonist sets the chapter pov_character_id', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();
    $character = Character::factory()->for($book)->create();
    $character->chapters()->attach($chapter, ['role' => 'mentioned']);

    $response = $this->patchJson("/books/{$book->id}/wiki/panel/characters/{$character->id}/role", [
        'chapter_id' => $chapter->id,
        'role' => 'protagonist',
    ]);

    $response->assertSuccessful();
    expect($chapter->fresh()->pov_character_id)->toBe($character->id);
});

test('promoting a new protagonist demotes the previous one to supporting', function () {
    $book = Book::factory()->create();
    $previousPov = Character::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->create([
        'pov_character_id' => $previousPov->id,
    ]);
    $newPov = Character::factory()->for($book)->create();
    $newPov->chapters()->attach($chapter, ['role' => 'supporting']);

    $response = $this->patchJson("/books/{$book->id}/wiki/panel/characters/{$newPov->id}/role", [
        'chapter_id' => $chapter->id,
        'role' => 'protagonist',
    ]);

    $response->assertSuccessful();
    expect($chapter->fresh()->pov_character_id)->toBe($newPov->id);
    expect($previousPov->chapters()->where('chapter_id', $chapter->id)->first()?->pivot?->role)
        ->toBe('supporting');
    expect($newPov->chapters()->where('chapter_id', $chapter->id)->first()?->pivot?->role)
        ->toBe('protagonist');
});

test('demoting the current pov to supporting clears chapter.pov_character_id', function () {
    $book = Book::factory()->create();
    $character = Character::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->create([
        'pov_character_id' => $character->id,
    ]);

    $response = $this->patchJson("/books/{$book->id}/wiki/panel/characters/{$character->id}/role", [
        'chapter_id' => $chapter->id,
        'role' => 'supporting',
    ]);

    $response->assertSuccessful();
    expect($chapter->fresh()->pov_character_id)->toBeNull();
    expect($character->chapters()->where('chapter_id', $chapter->id)->first()?->pivot?->role)
        ->toBe('supporting');
});
