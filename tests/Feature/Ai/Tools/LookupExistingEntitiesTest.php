<?php

use App\Ai\Tools\LookupExistingEntities;
use App\Enums\WikiEntryKind;
use App\Models\Book;
use App\Models\Character;
use App\Models\WikiEntry;
use Laravel\Ai\Tools\Request;

it('returns formatted list of existing characters and entities', function () {
    $book = Book::factory()->create();
    $character = Character::factory()->for($book)->create([
        'name' => 'John Smith',
        'aliases' => ['Johnny', 'JS'],
        'description' => 'The protagonist',
    ]);
    $entry = WikiEntry::factory()->for($book)->create([
        'name' => 'The Brass Lantern',
        'kind' => WikiEntryKind::Location,
        'type' => 'Tavern',
        'description' => 'A recurring meeting place.',
    ]);

    $tool = new LookupExistingEntities($book->id);
    $request = new Request([]);

    $result = $tool->handle($request);

    expect($result)
        ->toContain('John Smith')
        ->toContain('(aliases: Johnny, JS)')
        ->toContain('The protagonist')
        ->toContain('Existing Characters')
        ->toContain('The Brass Lantern')
        ->toContain('[location]')
        ->toContain('(Tavern)')
        ->toContain('Existing World Entities')
        ->toContain("id={$character->id}")
        ->toContain("id={$entry->id}");
});

it('returns message when no characters or entities exist', function () {
    $book = Book::factory()->create();

    $tool = new LookupExistingEntities($book->id);
    $request = new Request([]);

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

    $tool = new LookupExistingEntities($bookA->id);
    $request = new Request([]);

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

    $tool = new LookupExistingEntities($book->id);
    $request = new Request([]);

    $result = $tool->handle($request);

    expect($result)
        ->toContain('Existing Characters')
        ->toContain('Solo Character')
        ->not->toContain('Existing World Entities');
});

it('truncates long descriptions to keep the tool payload small', function () {
    $book = Book::factory()->create();
    $longDescription = str_repeat('A very detailed character description. ', 50); // ~1900 chars

    Character::factory()->for($book)->create([
        'name' => 'Verbose Character',
        'description' => $longDescription,
    ]);
    WikiEntry::factory()->for($book)->create([
        'name' => 'Ancient Order',
        'kind' => WikiEntryKind::Organization,
        'description' => $longDescription,
    ]);

    $tool = new LookupExistingEntities($book->id);
    $request = new Request([]);

    $result = (string) $tool->handle($request);

    // Each line should be capped well under the original description length
    expect(strlen($result))->toBeLessThan(1000)
        ->and($result)->toContain('Verbose Character')
        ->and($result)->toContain('Ancient Order')
        ->and($result)->toContain('...');
});

it('renders a placeholder when description is empty', function () {
    $book = Book::factory()->create();
    Character::factory()->for($book)->create([
        'name' => 'Nameless Hero',
        'description' => null,
        'ai_description' => null,
    ]);

    $tool = new LookupExistingEntities($book->id);
    $request = new Request([]);

    $result = (string) $tool->handle($request);

    expect($result)->toContain('Nameless Hero: (no description)');
});
