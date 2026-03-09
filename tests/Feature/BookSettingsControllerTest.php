<?php

use App\Models\Book;

test('update writing style saves', function () {
    $book = Book::factory()->create();

    $this->putJson(route('books.settings.writing-style.update', $book), [
        'writing_style_text' => 'Dark, moody prose with short sentences.',
    ])->assertOk();

    $book->refresh();
    expect($book->writing_style_text)->toBe('Dark, moody prose with short sentences.');
});

test('update prose pass rules saves', function () {
    $book = Book::factory()->create();

    $rules = Book::defaultProsePassRules();
    $rules[0]['enabled'] = false;

    $this->putJson(route('books.settings.prose-pass-rules.update', $book), [
        'rules' => $rules,
    ])->assertOk();

    $book->refresh();
    expect($book->prose_pass_rules[0]['enabled'])->toBeFalse();
});

test('writing style page loads', function () {
    $book = Book::factory()->create(['writing_style_text' => 'Formal, third person']);

    $this->get(route('books.settings.writing-style', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/book/writing-style')
            ->has('book')
            ->has('writing_style_display')
        );
});

test('prose pass rules page loads with defaults', function () {
    $book = Book::factory()->create();

    $this->get(route('books.settings.prose-pass-rules', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/book/prose-pass-rules')
            ->has('rules', 6)
        );
});

test('export page loads with storylines', function () {
    $book = Book::factory()->create();

    $this->get(route('books.settings.export', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/book/export')
            ->has('book')
            ->has('storylines')
        );
});
