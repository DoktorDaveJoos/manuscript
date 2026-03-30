<?php

use App\Enums\EditorialSectionType;
use App\Models\EditorialReview;
use App\Models\EditorialReviewChapterNote;
use App\Models\EditorialReviewSection;
use App\Models\License;

beforeEach(fn () => License::factory()->create());

it('shows empty state when no review exists', function () {
    [$book] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/ai/editorial-review");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Editorial Review')
        ->assertSee('Start Editorial Review');
});

it('renders completed review with sections and scores', function () {
    [$book] = createBookWithChapters(1);

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
    [$book, $chapters] = createBookWithChapters(1);

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

// License gate redirect is tested at feature level (EditorialReviewControllerTest).
// Browser tests can't reliably test middleware redirects due to RefreshDatabase
// transaction isolation — the server process may see stale license state.
