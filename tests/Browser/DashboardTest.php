<?php

use App\Models\Book;
use App\Models\License;
use App\Models\WritingSession;

it('renders dashboard with book stats', function () {
    [$book, $chapters] = createBookWithChapters(3);

    $page = visit("/books/{$book->id}/dashboard");

    $page->assertNoJavaScriptErrors()
        ->assertSee($book->title)
        ->assertSee('Words')
        ->assertSee('Pages')
        ->assertSee('Chapters');
});

it('shows chapter progress section', function () {
    [$book, $chapters] = createBookWithChapters(3);

    $page = visit("/books/{$book->id}/dashboard");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Chapter Progress')
        ->assertSee('draft');
});

it('shows writing goal section for pro users', function () {
    License::factory()->create();
    $book = Book::factory()->create([
        'title' => 'Pro Dashboard Book',
        'daily_word_count_goal' => 2000,
    ]);

    $page = visit("/books/{$book->id}/dashboard");

    $page->assertNoJavaScriptErrors()
        ->assertSee("Today's Writing Goal");
});

it('displays writing session data on dashboard', function () {
    License::factory()->create();
    [$book, $chapters] = createBookWithChapters(1);
    $book->update(['daily_word_count_goal' => 1000]);

    WritingSession::factory()->for($book)->create([
        'date' => now()->toDateString(),
        'words_written' => 500,
    ]);

    $page = visit("/books/{$book->id}/dashboard");

    $page->assertNoJavaScriptErrors()
        ->assertSee("Today's Writing Goal");
});
