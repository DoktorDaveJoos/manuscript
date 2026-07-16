<?php

use App\Models\Book;

test('renders the book notes page', function () {
    $book = Book::factory()->create([
        'notes' => "## Characters\n- Mara knows the truth",
        'notes_version' => 3,
    ]);

    $this->get(route('books.notes', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('books/notes')
            ->where('book.id', $book->id)
            ->where('book.notes', "## Characters\n- Mara knows the truth")
            ->where('book.notes_version', 3)
        );
});

test('updates book notes with optimistic versioning', function () {
    $book = Book::factory()->create(['notes_version' => 0]);

    $this->patchJson(route('books.notes.update', $book), [
        'notes' => "[ ] Verify the timeline\n\n| Place | Detail |\n| --- | --- |\n| Harbor | Foggy |",
        'expected_version' => 0,
    ])
        ->assertSuccessful()
        ->assertJsonPath('notes_version', 1);

    $book->refresh();

    expect($book->notes)->toContain('Verify the timeline')
        ->and($book->notes_version)->toBe(1);
});

test('clears book notes', function () {
    $book = Book::factory()->create([
        'notes' => 'Old research',
        'notes_version' => 2,
    ]);

    $this->patchJson(route('books.notes.update', $book), [
        'notes' => null,
        'expected_version' => 2,
    ])->assertSuccessful();

    $book->refresh();

    expect($book->notes)->toBeNull()
        ->and($book->notes_version)->toBe(3);
});

test('rejects stale and invalid book note updates', function () {
    $book = Book::factory()->create(['notes_version' => 4]);

    $this->patchJson(route('books.notes.update', $book), [
        'notes' => 'Stale notes',
        'expected_version' => 3,
    ])
        ->assertConflict()
        ->assertJsonPath('conflict', 'notes_version')
        ->assertJsonPath('notes_version', 4);

    $this->patchJson(route('books.notes.update', $book), [
        'notes' => ['not', 'text'],
    ])->assertInvalid(['notes', 'expected_version']);
});

test('returns not found for a missing book notes page', function () {
    $this->get(route('books.notes', 99999))->assertNotFound();
});
