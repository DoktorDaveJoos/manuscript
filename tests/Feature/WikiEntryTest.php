<?php

use App\Enums\WikiEntryKind;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\WikiEntry;

test('wiki entry belongs to a book', function () {
    $entry = WikiEntry::factory()->location()->create();

    expect($entry->book)->toBeInstanceOf(Book::class);
});

test('wiki entry has chapters via pivot', function () {
    $book = Book::factory()->create();
    $entry = WikiEntry::factory()->for($book)->location()->create();
    $chapter = Chapter::factory()->for($book)->create();

    $entry->chapters()->attach($chapter, ['notes' => 'test note']);

    expect($entry->chapters)->toHaveCount(1)
        ->and($entry->chapters->first()->pivot->notes)->toBe('test note');
});

test('wiki entry casts kind to enum', function () {
    $entry = WikiEntry::factory()->organization()->create();

    expect($entry->kind)->toBe(WikiEntryKind::Organization);
});

test('wiki entry casts metadata to array', function () {
    $entry = WikiEntry::factory()->create([
        'metadata' => ['key' => 'value'],
    ]);

    expect($entry->fresh()->metadata)->toBe(['key' => 'value']);
});

test('wiki entry factory states produce correct kinds', function (string $state, WikiEntryKind $expectedKind) {
    $entry = WikiEntry::factory()->{$state}()->create();

    expect($entry->kind)->toBe($expectedKind);
})->with([
    ['location', WikiEntryKind::Location],
    ['organization', WikiEntryKind::Organization],
    ['item', WikiEntryKind::Item],
    ['lore', WikiEntryKind::Lore],
]);

test('book has wiki entries relationship', function () {
    $book = Book::factory()->create();
    WikiEntry::factory()->for($book)->location()->count(2)->create();
    WikiEntry::factory()->for($book)->item()->create();

    expect($book->wikiEntries)->toHaveCount(3);
});

test('wiki entry first appearance chapter relationship', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();
    $entry = WikiEntry::factory()->for($book)->location()->create([
        'first_appearance' => $chapter->id,
    ]);

    expect($entry->firstAppearanceChapter->id)->toBe($chapter->id);
});
