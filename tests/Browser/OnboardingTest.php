<?php

use App\Models\AppSetting;
use App\Models\Book;
use App\Models\License;
use App\Models\Storyline;

it('completes full onboarding: create book and skip import', function () {
    $page = visit('/');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Begin your story')
        ->assertSee('Create your first book')
        ->click('Create your first book')
        ->assertSee('New book')
        ->type('input[placeholder="The Weight of Silence"]', 'My First Novel')
        ->type('input[placeholder="Your name (optional)"]', 'Test Author')
        ->click('Continue')
        ->assertPathBeginsWith('/books/')
        ->assertPathEndsWith('/import')
        ->assertNoJavaScriptErrors()
        ->assertSee('My First Novel')
        ->assertSee('Skip — start blank')
        ->click('Skip — start blank')
        ->assertNoJavaScriptErrors()
        ->assertSee('No chapters yet')
        ->assertSee('Create first chapter')
        ->assertSee('Import manuscript');

    expect(Book::where('title', 'My First Novel')->exists())->toBeTrue();
    expect(Storyline::where('name', 'Main Storyline')->exists())->toBeTrue();
});

it('import page renders correctly and shows file after attach', function () {
    $book = Book::factory()->create(['title' => 'Import Book']);

    $page = visit("/books/{$book->id}/import");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Import Book')
        ->assertSee('Drop manuscript files here')
        ->assertSee('Supports .docx, .odt, .txt, and .md')
        ->assertSee('How import works')
        ->assertSee('One file per storyline')
        ->assertSee('Chapters detected automatically')
        ->assertSee('Skip — start blank');

    // Attach a file — UI should reflect it even though FormData
    // serialization won't work in headless Playwright
    $fixturePath = base_path('tests/Feature/fixtures/chapters.docx');
    $page->attach('input[type="file"]', $fixturePath)
        ->assertSee('chapters.docx')
        ->assertSee('Import 1 file')
        ->assertNoJavaScriptErrors();
});

it('skip import creates a default storyline and reaches the editor', function () {
    $book = Book::factory()->create(['title' => 'Blank Book']);

    $page = visit("/books/{$book->id}/import");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Blank Book')
        ->click('Skip — start blank')
        ->assertNoJavaScriptErrors()
        ->assertSee('No chapters yet');

    expect($book->fresh()->storylines()->count())->toBe(1);
    expect($book->fresh()->storylines()->first()->name)->toBe('Main Storyline');
});

it('shows the book library when books exist', function () {
    Book::factory()->create(['title' => 'Existing Book']);

    $page = visit('/');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Your books')
        ->assertSee('Existing Book')
        ->assertSee('Create new book');
});

it('can create a second book from the library with pro license', function () {
    License::factory()->create();
    Book::factory()->create(['title' => 'First Book']);

    $page = visit('/');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Your books')
        ->click('Create new book')
        ->assertSee('New book')
        ->type('input[placeholder="The Weight of Silence"]', 'Second Book')
        ->type('input[placeholder="Your name (optional)"]', 'Test Author')
        ->click('Continue')
        ->assertPathEndsWith('/import')
        ->assertNoJavaScriptErrors()
        ->assertSee('Second Book');

    expect(Book::where('title', 'Second Book')->exists())->toBeTrue();
});

it('validates book title is required', function () {
    $page = visit('/');

    $page->assertNoJavaScriptErrors()
        ->click('Create your first book')
        ->assertSee('New book')
        ->click('Continue');

    // Should stay on the dialog — title is required server-side
    $page->assertSee('New book')
        ->assertNoJavaScriptErrors();

    expect(Book::count())->toBe(0);
});

it('dismisses crash report dialog on first visit', function () {
    AppSetting::set('crash_report_prompted', false);

    $page = visit('/');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Help Improve Manuscript')
        ->click('Not Now')
        ->assertSee('Begin your story')
        ->assertSee('Create your first book')
        ->assertNoJavaScriptErrors();
});

it('new book card is locked on free tier with existing book', function () {
    Book::factory()->create(['title' => 'My Only Book']);

    $page = visit('/');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Your books')
        ->assertSee('My Only Book')
        ->assertSee('Create new book')
        ->assertSee('Upgrade to Pro');
});
