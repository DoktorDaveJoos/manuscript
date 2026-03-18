<?php

use App\Models\Book;

test('writing style page redirects to unified settings', function () {
    $book = Book::factory()->create();

    $this->get(route('books.settings.writing-style', $book))
        ->assertRedirect('/settings');
});

test('prose pass rules page redirects to unified settings', function () {
    $book = Book::factory()->create();

    $this->get(route('books.settings.prose-pass-rules', $book))
        ->assertRedirect('/settings');
});

test('export page loads with chapters and trim sizes', function () {
    $book = Book::factory()->create();

    $this->get(route('books.settings.export', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('books/export')
            ->has('book')
            ->has('storylines')
            ->has('chapters')
            ->has('trimSizes')
            ->has('acts')
        );
});
