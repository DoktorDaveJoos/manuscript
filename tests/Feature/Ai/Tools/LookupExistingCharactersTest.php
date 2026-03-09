<?php

use App\Ai\Tools\LookupExistingCharacters;
use App\Models\Book;
use App\Models\Character;
use Laravel\Ai\Tools\Request;

it('returns formatted list of existing characters', function () {
    $book = Book::factory()->create();
    Character::factory()->for($book)->create([
        'name' => 'John Smith',
        'aliases' => ['Johnny', 'JS'],
        'description' => 'The protagonist',
    ]);
    Character::factory()->for($book)->create([
        'name' => 'Jane Doe',
        'aliases' => null,
        'description' => 'A supporting character',
    ]);

    $tool = new LookupExistingCharacters;
    $request = new Request(['book_id' => $book->id]);

    $result = $tool->handle($request);

    expect($result)
        ->toContain('John Smith')
        ->toContain('(aliases: Johnny, JS)')
        ->toContain('The protagonist')
        ->toContain('Jane Doe')
        ->toContain('A supporting character');
});

it('returns message when no characters exist', function () {
    $book = Book::factory()->create();

    $tool = new LookupExistingCharacters;
    $request = new Request(['book_id' => $book->id]);

    $result = $tool->handle($request);

    expect($result)->toBe('No existing characters found for this book.');
});

it('only returns characters for the specified book', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    Character::factory()->for($bookA)->create(['name' => 'Book A Character']);
    Character::factory()->for($bookB)->create(['name' => 'Book B Character']);

    $tool = new LookupExistingCharacters;
    $request = new Request(['book_id' => $bookA->id]);

    $result = $tool->handle($request);

    expect($result)
        ->toContain('Book A Character')
        ->not->toContain('Book B Character');
});
