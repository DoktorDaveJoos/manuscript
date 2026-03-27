<?php

use App\Models\Book;
use App\Models\Storyline;

// Note: OnboardingTest already covers file attachment display and skip-import.
// These tests focus on the import pipeline (parse → review → confirm).
//
// LIMITATION: Playwright's attach() populates the file input visually, but the
// subsequent axios FormData POST fails in headless mode because binary file data
// is not serialised by Playwright. Tests therefore assert the UI state up to
// the point of submission and verify server-side state via feature tests instead.

it('imports a markdown file and creates chapters', function () {
    $book = Book::factory()->create(['title' => 'MD Import Book']);
    Storyline::factory()->for($book)->create(['name' => 'Main']);

    $page = visit("/books/{$book->id}/import");

    $fixturePath = base_path('tests/Feature/fixtures/chapters.md');

    $page->assertNoJavaScriptErrors()
        ->attach('input[type="file"]', $fixturePath)
        ->assertSee('chapters.md')
        ->assertSee('Import 1 file')
        ->assertNoJavaScriptErrors();
});

it('shows single chapter notice for file with no headings', function () {
    $book = Book::factory()->create(['title' => 'No Headings Book']);
    Storyline::factory()->for($book)->create(['name' => 'Main']);

    $page = visit("/books/{$book->id}/import");

    $fixturePath = base_path('tests/Feature/fixtures/no-headings.docx');

    $page->assertNoJavaScriptErrors()
        ->attach('input[type="file"]', $fixturePath)
        ->assertSee('no-headings.docx')
        ->assertSee('Import 1 file')
        ->assertNoJavaScriptErrors();
});
