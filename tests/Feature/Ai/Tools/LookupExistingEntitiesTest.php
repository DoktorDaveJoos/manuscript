<?php

use App\Ai\Tools\LookupExistingEntities;
use App\Enums\WikiEntryKind;
use App\Models\Book;
use App\Models\Character;
use App\Models\WikiEntry;
use Laravel\Ai\Tools\Request;

it('returns formatted list of existing characters and entities', function () {
    $book = Book::factory()->create();
    Character::factory()->for($book)->create([
        'name' => 'John Smith',
        'aliases' => ['Johnny', 'JS'],
        'description' => 'The protagonist',
    ]);
    WikiEntry::factory()->for($book)->create([
        'name' => 'The Brass Lantern',
        'kind' => WikiEntryKind::Location,
        'type' => 'Tavern',
        'description' => 'A recurring meeting place.',
    ]);

    $tool = new LookupExistingEntities;
    $request = new Request(['book_id' => $book->id]);

    $result = $tool->handle($request);

    expect($result)
        ->toContain('John Smith')
        ->toContain('(aliases: Johnny, JS)')
        ->toContain('The protagonist')
        ->toContain('Existing Characters')
        ->toContain('The Brass Lantern')
        ->toContain('[location]')
        ->toContain('(Tavern)')
        ->toContain('Existing World Entities');
});

it('returns message when no characters or entities exist', function () {
    $book = Book::factory()->create();

    $tool = new LookupExistingEntities;
    $request = new Request(['book_id' => $book->id]);

    $result = $tool->handle($request);

    expect($result)->toBe('No existing characters or entities found for this book.');
});

it('only returns data for the specified book', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    Character::factory()->for($bookA)->create(['name' => 'Book A Character']);
    Character::factory()->for($bookB)->create(['name' => 'Book B Character']);
    WikiEntry::factory()->for($bookA)->create([
        'name' => 'Book A Location',
        'kind' => WikiEntryKind::Location,
    ]);
    WikiEntry::factory()->for($bookB)->create([
        'name' => 'Book B Location',
        'kind' => WikiEntryKind::Location,
    ]);

    $tool = new LookupExistingEntities;
    $request = new Request(['book_id' => $bookA->id]);

    $result = $tool->handle($request);

    expect($result)
        ->toContain('Book A Character')
        ->toContain('Book A Location')
        ->not->toContain('Book B Character')
        ->not->toContain('Book B Location');
});

it('returns only characters when no entities exist', function () {
    $book = Book::factory()->create();
    Character::factory()->for($book)->create(['name' => 'Solo Character']);

    $tool = new LookupExistingEntities;
    $request = new Request(['book_id' => $book->id]);

    $result = $tool->handle($request);

    expect($result)
        ->toContain('Existing Characters')
        ->toContain('Solo Character')
        ->not->toContain('Existing World Entities');
});
