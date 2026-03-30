<?php

use App\Models\Book;
use App\Models\Character;
use App\Models\WikiEntry;
use App\Support\EntityNameMatcher;

test('matches character by exact name', function () {
    $book = Book::factory()->create();
    $character = Character::factory()->for($book)->create(['name' => 'Maja Paulsen']);
    $characters = $book->characters()->get();

    $matcher = new EntityNameMatcher($characters, collect());
    $match = $matcher->findCharacter('Maja Paulsen');

    expect($match)->not->toBeNull()
        ->and($match->id)->toBe($character->id);
});

test('matches character case-insensitively', function () {
    $book = Book::factory()->create();
    $character = Character::factory()->for($book)->create(['name' => 'Maja Paulsen']);
    $characters = $book->characters()->get();

    $matcher = new EntityNameMatcher($characters, collect());
    $match = $matcher->findCharacter('maja paulsen');

    expect($match)->not->toBeNull()
        ->and($match->id)->toBe($character->id);
});

test('matches character after stripping leading articles', function () {
    $book = Book::factory()->create();
    $character = Character::factory()->for($book)->create(['name' => 'The Dark Knight']);
    $characters = $book->characters()->get();

    $matcher = new EntityNameMatcher($characters, collect());

    expect($matcher->findCharacter('Dark Knight'))->not->toBeNull()
        ->and($matcher->findCharacter('the dark knight'))->not->toBeNull();
});

test('matches character by alias', function () {
    $book = Book::factory()->create();
    $character = Character::factory()->for($book)->create([
        'name' => 'Maja Paulsen',
        'aliases' => ['Maja', 'The Commander'],
    ]);
    $characters = $book->characters()->get();

    $matcher = new EntityNameMatcher($characters, collect());

    expect($matcher->findCharacter('Maja'))->not->toBeNull()
        ->and($matcher->findCharacter('The Commander'))->not->toBeNull()
        ->and($matcher->findCharacter('commander'))->not->toBeNull();
});

test('returns null for no match', function () {
    $book = Book::factory()->create();
    Character::factory()->for($book)->create(['name' => 'Maja Paulsen']);
    $characters = $book->characters()->get();

    $matcher = new EntityNameMatcher($characters, collect());

    expect($matcher->findCharacter('Unknown Person'))->toBeNull();
});

test('matches wiki entry by exact name and kind', function () {
    $book = Book::factory()->create();
    $entry = WikiEntry::factory()->location()->for($book)->create(['name' => 'The Brass Lantern']);
    $entries = $book->wikiEntries()->get();

    $matcher = new EntityNameMatcher(collect(), $entries);
    $match = $matcher->findWikiEntry('The Brass Lantern', 'location');

    expect($match)->not->toBeNull()
        ->and($match->id)->toBe($entry->id);
});

test('matches wiki entry case-insensitively with article stripping', function () {
    $book = Book::factory()->create();
    WikiEntry::factory()->location()->for($book)->create(['name' => 'The Brass Lantern']);
    $entries = $book->wikiEntries()->get();

    $matcher = new EntityNameMatcher(collect(), $entries);

    expect($matcher->findWikiEntry('brass lantern', 'location'))->not->toBeNull()
        ->and($matcher->findWikiEntry('Brass Lantern', 'location'))->not->toBeNull();
});

test('matches wiki entry by alias', function () {
    $book = Book::factory()->create();
    WikiEntry::factory()->organization()->for($book)->create([
        'name' => 'Green Zone Protection Party',
        'metadata' => ['aliases' => ['GZP', 'The Party']],
    ]);
    $entries = $book->wikiEntries()->get();

    $matcher = new EntityNameMatcher(collect(), $entries);

    expect($matcher->findWikiEntry('GZP', 'organization'))->not->toBeNull()
        ->and($matcher->findWikiEntry('the party', 'organization'))->not->toBeNull();
});

test('does not match wiki entry with wrong kind', function () {
    $book = Book::factory()->create();
    WikiEntry::factory()->location()->for($book)->create(['name' => 'The Brass Lantern']);
    $entries = $book->wikiEntries()->get();

    $matcher = new EntityNameMatcher(collect(), $entries);

    expect($matcher->findWikiEntry('The Brass Lantern', 'organization'))->toBeNull();
});

test('handles whitespace trimming', function () {
    $book = Book::factory()->create();
    Character::factory()->for($book)->create(['name' => 'Maja Paulsen']);
    $characters = $book->characters()->get();

    $matcher = new EntityNameMatcher($characters, collect());

    expect($matcher->findCharacter('  Maja Paulsen  '))->not->toBeNull();
});
