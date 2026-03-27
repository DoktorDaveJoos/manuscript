<?php

use App\Enums\EditorialSectionType;
use App\Models\EditorialReview;
use App\Models\EditorialReviewChapterNote;
use App\Models\EditorialReviewSection;
use App\Models\License;

it('shows empty state when no review exists', function () {
    License::factory()->create();
    [$book] = createBookWithChapters(3);

    $page = visit("/books/{$book->id}/ai/editorial-review");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Editorial Review')
        ->assertSee('Start Editorial Review');
});

it('renders completed review with sections and scores', function () {
    License::factory()->create();
    [$book, $chapters] = createBookWithChapters(3);

    $review = EditorialReview::factory()->for($book)->create([
        'overall_score' => 72,
        'executive_summary' => 'A promising manuscript with strong character work but pacing issues in the middle act.',
    ]);

    EditorialReviewSection::factory()->for($review)->create([
        'type' => EditorialSectionType::Plot,
        'score' => 68,
        'summary' => 'The plot has a solid premise but the second act sags.',
        'findings' => [
            [
                'severity' => 'warning',
                'description' => 'The midpoint reversal comes too late at chapter 15.',
                'chapter_references' => [],
                'recommendation' => 'Move the reversal to chapter 12.',
            ],
        ],
    ]);

    EditorialReviewSection::factory()->for($review)->create([
        'type' => EditorialSectionType::Characters,
        'score' => 85,
        'summary' => 'Strong character development with clear arcs.',
    ]);

    $page = visit("/books/{$book->id}/ai/editorial-review");

    $page->assertNoJavaScriptErrors()
        ->assertSee('A promising manuscript')
        ->assertSee('Plot')
        ->assertSee('Characters');
});

it('shows chapter notes when present', function () {
    License::factory()->create();
    [$book, $chapters] = createBookWithChapters(3);

    $review = EditorialReview::factory()->for($book)->create();

    EditorialReviewSection::factory()->for($review)->create([
        'type' => EditorialSectionType::Plot,
    ]);

    EditorialReviewChapterNote::create([
        'editorial_review_id' => $review->id,
        'chapter_id' => $chapters[0]->id,
        'notes' => ['The opening hook is effective but could be sharper.'],
    ]);

    $page = visit("/books/{$book->id}/ai/editorial-review");

    $page->assertNoJavaScriptErrors()
        ->assertSee($chapters[0]->title);
});

it('redirects to settings when accessing editorial review without license', function () {
    [$book] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/ai/editorial-review");

    // RequiresLicense middleware redirects to settings
    $page->assertNoJavaScriptErrors()
        ->assertPathBeginsWith('/settings');
});
