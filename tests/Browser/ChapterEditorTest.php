<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Storyline;

it('shows empty state when book has no chapters', function () {
    $book = Book::factory()->create(['title' => 'Empty Book']);

    $page = visit("/books/{$book->id}/editor");

    $page->assertNoJavaScriptErrors()
        ->assertSee('No chapters yet')
        ->assertSee('Create first chapter')
        ->assertSee('Import manuscript');
});

it('navigates to editor and displays chapter content', function () {
    [$book, $chapters] = createBookWithChapters(2);

    $page = visit("/books/{$book->id}/editor");

    $page->assertNoJavaScriptErrors()
        ->assertSee($chapters[0]->title);
});

it('shows specific chapter when navigated directly', function () {
    [$book, $chapters] = createBookWithChapters(3);

    $page = visit("/books/{$book->id}/chapters/{$chapters[1]->id}");

    $page->assertNoJavaScriptErrors()
        ->assertSee($chapters[1]->title);
});

it('creates a new chapter from empty state', function () {
    $book = Book::factory()->create(['title' => 'New Chapter Book']);
    Storyline::factory()->for($book)->create(['name' => 'Main']);

    $page = visit("/books/{$book->id}/editor");

    $page->assertNoJavaScriptErrors()
        ->assertSee('No chapters yet')
        ->click('Create first chapter')
        ->assertNoJavaScriptErrors();

    expect(Chapter::where('book_id', $book->id)->count())->toBe(1);
});

it('renders chapter sidebar with multiple chapters', function () {
    [$book, $chapters] = createBookWithChapters(3);

    $page = visit("/books/{$book->id}/chapters/{$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->assertSee($chapters[0]->title)
        ->assertSee($chapters[1]->title)
        ->assertSee($chapters[2]->title);
});
