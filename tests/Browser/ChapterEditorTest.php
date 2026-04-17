<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\EditorialReview;
use App\Models\EditorialReviewChapterNote;
use App\Models\License;
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
    [$book, $chapters] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/editor");

    $page->assertNoJavaScriptErrors()
        ->assertSee($chapters[0]->title);
});

it('shows specific chapter when navigated directly', function () {
    [$book, $chapters] = createBookWithChapters(2);

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

it('notes panel restores content after close and reopen', function () {
    [$book, $chapters] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/editor?panes={$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="notes"]')
        ->fill('[data-notes-input]', 'Remember the villain twist')
        ->wait(1)
        ->click('[data-access-bar="notes"]')
        ->wait(1)
        ->click('[data-access-bar="notes"]')
        ->assertValue('[data-notes-input]', 'Remember the villain twist');

    expect($chapters[0]->fresh()->notes)->toBe('Remember the villain twist');
});

it('wiki panel remounts cleanly when switching panes in splitscreen', function () {
    [$book, $chapters] = createBookWithChapters(2);

    $page = visit("/books/{$book->id}/editor?panes={$chapters[0]->id},{$chapters[1]->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="wiki"]')
        ->assertNoJavaScriptErrors()
        ->click("[data-pane-chapter='{$chapters[1]->id}']")
        ->assertNoJavaScriptErrors()
        ->click("[data-pane-chapter='{$chapters[0]->id}']")
        ->assertNoJavaScriptErrors();
});

it('ai panel remounts cleanly when switching panes in splitscreen', function () {
    [$book, $chapters] = createBookWithChapters(2);

    $page = visit("/books/{$book->id}/editor?panes={$chapters[0]->id},{$chapters[1]->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="ai"]')
        ->assertNoJavaScriptErrors()
        ->click("[data-pane-chapter='{$chapters[1]->id}']")
        ->assertNoJavaScriptErrors()
        ->click("[data-pane-chapter='{$chapters[0]->id}']")
        ->assertNoJavaScriptErrors();
});

it('ai chat drawer remounts cleanly when switching panes in splitscreen', function () {
    [$book, $chapters] = createBookWithChapters(2);

    $page = visit("/books/{$book->id}/editor?panes={$chapters[0]->id},{$chapters[1]->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="chat"]')
        ->assertNoJavaScriptErrors()
        ->click("[data-pane-chapter='{$chapters[1]->id}']")
        ->assertNoJavaScriptErrors()
        ->click("[data-pane-chapter='{$chapters[0]->id}']")
        ->assertNoJavaScriptErrors();
});

it('editorial panel shows chapter-specific note in splitscreen', function () {
    License::factory()->create();

    [$book, $chapters] = createBookWithChapters(2);

    $review = EditorialReview::factory()->for($book)->create([
        'status' => 'completed',
    ]);

    EditorialReviewChapterNote::factory()
        ->for($review)
        ->for($chapters[0])
        ->create([
            'notes' => ['chapter_note' => 'Note for chapter one'],
        ]);

    EditorialReviewChapterNote::factory()
        ->for($review)
        ->for($chapters[1])
        ->create([
            'notes' => ['chapter_note' => 'Note for chapter two'],
        ]);

    $page = visit("/books/{$book->id}/editor?panes={$chapters[0]->id},{$chapters[1]->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="editorial"]')
        ->assertSee('Note for chapter one')
        ->click("[data-pane-chapter='{$chapters[1]->id}']")
        ->assertSee('Note for chapter two')
        ->click("[data-pane-chapter='{$chapters[0]->id}']")
        ->assertSee('Note for chapter one');
});

it('toggles typewriter mode from the formatting toolbar', function () {
    [$book, $chapters] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/chapters/{$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->assertAttributeMissing('[data-testid="typewriter-toggle"]', 'aria-pressed')
        ->click('[data-testid="typewriter-toggle"]')
        ->assertAttribute('[data-testid="typewriter-toggle"]', 'aria-pressed', 'true')
        ->click('[data-testid="typewriter-toggle"]')
        ->assertAttributeMissing('[data-testid="typewriter-toggle"]', 'aria-pressed');
});
