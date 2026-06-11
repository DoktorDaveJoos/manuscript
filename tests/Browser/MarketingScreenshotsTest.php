<?php

use App\Models\AiSetting;
use App\Models\Book;
use App\Models\EditorialReview;
use Database\Seeders\MarketingSeeder;
use Database\Seeders\PlotCoachConversationSeeder;

it('captures English marketing screenshots from the seeded book', function () {
    AiSetting::factory()->create();

    $this->seed(MarketingSeeder::class);
    $this->seed(PlotCoachConversationSeeder::class);

    $book = Book::query()->where('title', 'The Vanishing Hour')->firstOrFail();

    // Plot Coach — Coach mode is the default when an active session exists.
    visit("/books/{$book->id}/plot")
        ->resize(1440, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee('Act 2 feels thin to me')
        ->screenshot(true, 'marketing/plot-coach');

    // Plot Board — toggle to Board view.
    visit("/books/{$book->id}/plot")
        ->resize(1440, 900)
        ->click('Board')
        ->wait(1)
        ->assertSee('ACT 1')
        ->screenshot(true, 'marketing/plot-board');

    // Chapter Editor — first chapter prose view.
    $chapter = $book->chapters()->orderBy('reader_order')->firstOrFail();
    visit("/books/{$book->id}/chapters/{$chapter->id}")
        ->resize(1440, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee('The Box in the Basement')
        ->screenshot(true, 'marketing/editor');

    // Dashboard — writing stats + streak heatmap.
    visit("/books/{$book->id}/dashboard")
        ->resize(1440, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee('The Vanishing Hour')
        ->screenshot(true, 'marketing/dashboard');

    // Editorial Review — AI Assistant scorecard.
    $review = EditorialReview::query()->where('book_id', $book->id)->latest('id')->firstOrFail();
    visit("/books/{$book->id}/ai/editorial-review/{$review->id}")
        ->resize(1440, 900)
        ->assertNoJavaScriptErrors()
        ->screenshot(true, 'marketing/editorial-review');
})->skipOnCi();
