<?php

use App\Models\Beat;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\EditorialReview;
use App\Models\EditorialReviewChapterNote;
use App\Models\License;
use App\Models\PlotPoint;
use App\Models\Scene;
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

it('edits notes in a multi-line textarea so long lines wrap to the panel width', function () {
    [$book, $chapters] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/editor?panes={$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="notes"]')
        ->fill('[data-notes-input]', 'A very long note line that should wrap onto multiple visual rows instead of scrolling sideways inside a single-line input field')
        ->wait(1)
        ->assertCount('textarea[data-notes-input]', 1);

    expect($chapters[0]->fresh()->notes)
        ->toBe('A very long note line that should wrap onto multiple visual rows instead of scrolling sideways inside a single-line input field');
});

it('shows the notes save status as an icon rather than text', function () {
    [$book, $chapters] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/editor?panes={$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="notes"]')
        ->fill('[data-notes-input]', 'Trigger a save')
        ->assertCount('svg[data-notes-save-status]', 1);
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

it('plot panel connects a beat to the active chapter', function () {
    License::factory()->create();

    [$book, $chapters] = createBookWithChapters(1);
    $plotPoint = PlotPoint::factory()->for($book)->create(['title' => 'Inciting incident']);
    Beat::factory()->for($plotPoint, 'plotPoint')->create(['title' => 'Murder of the duke']);

    $page = visit("/books/{$book->id}/editor?panes={$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="plot"]')
        // The search prompt only exists as an input placeholder — assertSee
        // matches text nodes, so assert on the input element itself.
        ->assertCount('input[placeholder="Search beats..."]', 1)
        ->fill('input[placeholder="Search beats..."]', 'Murder')
        ->wait(1)
        ->assertSee('Murder of the duke')
        ->assertSee('Inciting incident');
});

it('creates a scene from the chapter list "+ Add scene" button', function () {
    [$book, $chapters] = createBookWithChapters(1);

    expect(Scene::where('chapter_id', $chapters[0]->id)->count())->toBe(1);

    $page = visit("/books/{$book->id}/chapters/{$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-testid="add-scene-button"]')
        ->wait(1)
        ->assertNoJavaScriptErrors();

    expect(Scene::where('chapter_id', $chapters[0]->id)->count())->toBe(2);
});

it('removes a deleted scene from the sidebar without a page refresh', function () {
    [$book, $chapters] = createBookWithChapters(1);

    Scene::factory()->for($chapters[0])->create([
        'title' => 'Second Scene',
        'sort_order' => 1,
        'word_count' => 0,
    ]);

    expect(Scene::where('chapter_id', $chapters[0]->id)->count())->toBe(2);

    $secondSceneId = Scene::where('chapter_id', $chapters[0]->id)
        ->where('sort_order', 1)
        ->value('id');

    $page = visit("/books/{$book->id}/chapters/{$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->assertCount('[data-sidebar-scene]', 2)
        ->rightClick("[data-sidebar-scene='{$secondSceneId}']")
        ->click('Delete')
        ->wait(1)
        ->assertNoJavaScriptErrors()
        ->assertCount('[data-sidebar-scene]', 1);

    expect(Scene::where('chapter_id', $chapters[0]->id)->count())->toBe(1);
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
