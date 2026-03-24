<?php

use App\Jobs\RunEditorialReviewJob;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\EditorialReview;
use App\Models\EditorialReviewChapterNote;
use App\Models\EditorialReviewSection;
use App\Models\License;
use App\Models\Storyline;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    License::factory()->create();
});

test('index renders editorial review page with props', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 1]);

    $this->get(route('books.ai.editorial-review.index', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('books/editorial-review')
            ->has('book')
            ->has('reviews')
            ->has('chapters')
        );
});

test('index includes latest completed review with sections', function () {
    $book = Book::factory()->create();

    EditorialReview::factory()->for($book)->create([
        'status' => 'failed',
    ]);

    $completedReview = EditorialReview::factory()->for($book)->create([
        'status' => 'completed',
        'executive_summary' => 'Great manuscript overall.',
        'overall_score' => 75,
    ]);

    EditorialReviewSection::factory()->for($completedReview, 'editorialReview')->create([
        'type' => 'plot',
        'score' => 80,
    ]);

    $this->get(route('books.ai.editorial-review.index', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('books/editorial-review')
            ->has('reviews', 2)
            ->where('latestReview.id', $completedReview->id)
            ->has('latestReview.sections', 1)
        );
});

test('store creates a pending review and dispatches job', function () {
    Queue::fake();

    $book = Book::factory()->withAi()->create();

    $this->postJson(route('books.ai.editorial-review.store', $book))
        ->assertSuccessful()
        ->assertJsonPath('status', 'pending');

    $this->assertDatabaseHas('editorial_reviews', [
        'book_id' => $book->id,
        'status' => 'pending',
    ]);

    Queue::assertPushed(RunEditorialReviewJob::class);
});

test('store rejects when review is already in progress', function () {
    $book = Book::factory()->withAi()->create();

    EditorialReview::factory()->for($book)->create([
        'status' => 'analyzing',
    ]);

    $this->postJson(route('books.ai.editorial-review.store', $book))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'An editorial review is already in progress for this book.');
});

test('store allows new review when previous completed', function () {
    Queue::fake();

    $book = Book::factory()->withAi()->create();

    EditorialReview::factory()->for($book)->create([
        'status' => 'completed',
    ]);

    $this->postJson(route('books.ai.editorial-review.store', $book))
        ->assertSuccessful();

    Queue::assertPushed(RunEditorialReviewJob::class);
});

test('store allows new review when previous failed', function () {
    Queue::fake();

    $book = Book::factory()->withAi()->create();

    EditorialReview::factory()->for($book)->create([
        'status' => 'failed',
    ]);

    $this->postJson(route('books.ai.editorial-review.store', $book))
        ->assertSuccessful();

    Queue::assertPushed(RunEditorialReviewJob::class);
});

test('store requires ai configured', function () {
    $book = Book::factory()->create();

    $this->postJson(route('books.ai.editorial-review.store', $book))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'No AI provider configured.');
});

test('show renders editorial review with sections and chapter notes', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 1]);

    $review = EditorialReview::factory()->for($book)->create([
        'status' => 'completed',
        'overall_score' => 82,
    ]);

    EditorialReviewSection::factory()->for($review, 'editorialReview')->create([
        'type' => 'plot',
        'score' => 85,
    ]);

    EditorialReviewChapterNote::factory()->for($review, 'editorialReview')->create([
        'chapter_id' => $chapter->id,
    ]);

    $this->get(route('books.ai.editorial-review.show', [$book, $review]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('books/editorial-review')
            ->where('review.id', $review->id)
            ->has('review.sections', 1)
            ->has('review.chapter_notes', 1)
            ->has('chapters', 1)
            ->has('reviews')
        );
});

test('show returns 404 for review belonging to different book', function () {
    $book = Book::factory()->create();
    $otherBook = Book::factory()->create();

    $review = EditorialReview::factory()->for($otherBook)->create();

    $this->get(route('books.ai.editorial-review.show', [$book, $review]))
        ->assertNotFound();
});

test('progress returns review status and progress', function () {
    $book = Book::factory()->create();

    $review = EditorialReview::factory()->for($book)->create([
        'status' => 'analyzing',
        'progress' => ['phase' => 'analyzing', 'current_chapter' => 3, 'total_chapters' => 12],
    ]);

    $this->getJson(route('books.ai.editorial-review.progress', [$book, $review]))
        ->assertSuccessful()
        ->assertJsonPath('status', 'analyzing')
        ->assertJsonPath('progress.phase', 'analyzing')
        ->assertJsonPath('progress.current_chapter', 3)
        ->assertJsonPath('progress.total_chapters', 12)
        ->assertJsonPath('error_message', null);
});

test('progress returns 404 for review belonging to different book', function () {
    $book = Book::factory()->create();
    $otherBook = Book::factory()->create();

    $review = EditorialReview::factory()->for($otherBook)->create();

    $this->getJson(route('books.ai.editorial-review.progress', [$book, $review]))
        ->assertNotFound();
});

test('progress returns error message for failed review', function () {
    $book = Book::factory()->create();

    $review = EditorialReview::factory()->for($book)->create([
        'status' => 'failed',
        'error_message' => 'API rate limit exceeded.',
    ]);

    $this->getJson(route('books.ai.editorial-review.progress', [$book, $review]))
        ->assertSuccessful()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('error_message', 'API rate limit exceeded.');
});

test('editorial review routes require license', function () {
    License::query()->delete();

    $book = Book::factory()->create();

    $this->get(route('books.ai.editorial-review.index', $book))
        ->assertRedirect(route('settings.index'));
});
