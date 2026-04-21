<?php

use App\Models\Book;
use App\Models\License;

it('shows the configure-AI CTA in Coach mode when Pro but no AI provider', function () {
    License::factory()->create();
    $book = Book::factory()->create();

    $page = visit("/books/{$book->id}/plot");

    $page->assertNoJavaScriptErrors();

    // Default mode is Board (no active coach session). Switch to Coach.
    $page->click('Coach');

    $page->assertSee('Configure AI');
});

it('shows the intake empty state in Coach mode when Pro + AI configured', function () {
    License::factory()->create();
    $book = Book::factory()->withAi()->create();

    $page = visit("/books/{$book->id}/plot");

    $page->assertNoJavaScriptErrors();

    $page->click('Coach');

    $page->assertSee('Start plotting with Coach');
});

it('renders both mode toggle buttons on the plot page', function () {
    License::factory()->create();
    $book = Book::factory()->withAi()->create();

    $page = visit("/books/{$book->id}/plot");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Coach')
        ->assertSee('Board');
});

it('toggles between coach and board modes via the toggle', function () {
    License::factory()->create();
    $book = Book::factory()->withAi()->create();

    $page = visit("/books/{$book->id}/plot");

    $page->assertNoJavaScriptErrors();

    // Default is Board — "Start plotting with Coach" should not be visible.
    $page->assertDontSee('Start plotting with Coach');

    // Switch to Coach.
    $page->click('Coach');
    $page->assertSee('Start plotting with Coach');

    // Switch back to Board.
    $page->click('Board');
    $page->assertDontSee('Start plotting with Coach');
});

// No-Pro redirect happens at middleware level (see PlotCoachControllerTest
// feature test for the 403). Browser tests can't reliably test that due to
// RefreshDatabase transaction isolation — same reason noted in
// EditorialReviewTest.php.
