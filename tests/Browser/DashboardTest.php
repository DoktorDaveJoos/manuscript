<?php

use App\Models\License;
use App\Models\WritingSession;

it('renders dashboard with book stats', function () {
    [$book] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/dashboard");

    $page->assertNoJavaScriptErrors()
        ->assertSee($book->title)
        ->assertSee('Words')
        ->assertSee('Pages')
        ->assertSee('Chapters');
});

it('shows chapter progress section', function () {
    [$book] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/dashboard");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Chapter Progress')
        ->assertSee('draft');
});

it('shows writing goal with session data for pro users', function () {
    License::factory()->create();
    [$book] = createBookWithChapters(1);
    $book->update(['daily_word_count_goal' => 1000]);

    WritingSession::factory()->for($book)->create([
        'date' => now()->toDateString(),
        'words_written' => 500,
    ]);

    $page = visit("/books/{$book->id}/dashboard");

    $page->assertNoJavaScriptErrors()
        ->assertSee("Today's Writing Goal")
        ->assertSee('500');
});
