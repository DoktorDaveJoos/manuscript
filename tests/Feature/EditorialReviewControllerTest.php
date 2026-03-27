<?php

use App\Enums\EditorialSectionType;
use App\Jobs\RunEditorialReviewJob;
use App\Models\Book;
use App\Models\EditorialReview;
use App\Models\EditorialReviewSection;
use App\Models\License;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    License::factory()->create();
    $this->withoutVite();
});

// --- Authorization tests ---

test('index requires license', function () {
    License::query()->delete();

    $book = Book::factory()->create();

    $this->getJson(route('books.ai.editorial-review.index', $book))
        ->assertForbidden();
});

test('store requires license', function () {
    License::query()->delete();

    $book = Book::factory()->create();

    $this->postJson(route('books.ai.editorial-review.store', $book))
        ->assertForbidden();
});

test('store requires AI configured', function () {
    $book = Book::factory()->create();

    $this->postJson(route('books.ai.editorial-review.store', $book))
        ->assertStatus(422);
});

// --- Store tests ---

test('store creates pending editorial review and dispatches job', function () {
    Queue::fake();

    $book = Book::factory()->withAi()->create();

    $this->postJson(route('books.ai.editorial-review.store', $book))
        ->assertOk()
        ->assertJsonPath('message', 'Editorial review started.');

    $this->assertDatabaseHas('editorial_reviews', [
        'book_id' => $book->id,
        'status' => 'pending',
    ]);

    Queue::assertPushed(RunEditorialReviewJob::class);
});

test('store prevents duplicate in-progress review', function () {
    Queue::fake();

    $book = Book::factory()->withAi()->create();
    EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'analyzing',
    ]);

    $this->postJson(route('books.ai.editorial-review.store', $book))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'An editorial review is already in progress for this book.');
});

test('store prevents duplicate when review is pending', function () {
    Queue::fake();

    $book = Book::factory()->withAi()->create();
    EditorialReview::factory()->pending()->create([
        'book_id' => $book->id,
    ]);

    $this->postJson(route('books.ai.editorial-review.store', $book))
        ->assertUnprocessable();
});

test('store prevents duplicate when review is synthesizing', function () {
    Queue::fake();

    $book = Book::factory()->withAi()->create();
    EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'synthesizing',
    ]);

    $this->postJson(route('books.ai.editorial-review.store', $book))
        ->assertUnprocessable();
});

test('store allows new review when previous is completed', function () {
    Queue::fake();

    $book = Book::factory()->withAi()->create();
    EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'completed',
    ]);

    $this->postJson(route('books.ai.editorial-review.store', $book))
        ->assertOk();

    expect(EditorialReview::where('book_id', $book->id)->count())->toBe(2);
    Queue::assertPushed(RunEditorialReviewJob::class);
});

test('store allows new review when previous has failed', function () {
    Queue::fake();

    $book = Book::factory()->withAi()->create();
    EditorialReview::factory()->failed()->create([
        'book_id' => $book->id,
    ]);

    $this->postJson(route('books.ai.editorial-review.store', $book))
        ->assertOk();

    Queue::assertPushed(RunEditorialReviewJob::class);
});

// --- Index tests ---

test('index returns editorial reviews list', function () {
    $book = Book::factory()->withAi()->create();
    EditorialReview::factory()->count(3)->create([
        'book_id' => $book->id,
        'status' => 'completed',
    ]);

    $this->get(route('books.ai.editorial-review.index', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('books/editorial-review')
            ->has('reviews', 3)
            ->has('book')
        );
});

test('index returns empty list when no reviews exist', function () {
    $book = Book::factory()->withAi()->create();

    $this->get(route('books.ai.editorial-review.index', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('books/editorial-review')
            ->has('reviews', 0)
        );
});

// --- Show tests ---

test('show returns review with sections', function () {
    $book = Book::factory()->withAi()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'completed',
    ]);
    EditorialReviewSection::factory()->create([
        'editorial_review_id' => $review->id,
        'type' => EditorialSectionType::Plot,
    ]);

    $this->get(route('books.ai.editorial-review.show', [$book, $review]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('books/editorial-review')
            ->has('latestReview')
            ->has('latestReview.sections', 1)
            ->has('chapters')
            ->has('book')
        );
});

test('show returns 404 for review from different book', function () {
    $book = Book::factory()->withAi()->create();
    $otherBook = Book::factory()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $otherBook->id,
    ]);

    $this->get(route('books.ai.editorial-review.show', [$book, $review]))
        ->assertNotFound();
});

// --- Progress tests ---

test('progress returns review status', function () {
    $book = Book::factory()->withAi()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'analyzing',
        'progress' => ['phase' => 'analyzing', 'current_chapter' => 3, 'total_chapters' => 12],
    ]);

    $this->getJson(route('books.ai.editorial-review.progress', [$book, $review]))
        ->assertSuccessful()
        ->assertJson([
            'status' => 'analyzing',
            'progress' => [
                'phase' => 'analyzing',
                'current_chapter' => 3,
                'total_chapters' => 12,
            ],
        ]);
});

test('progress returns completed status', function () {
    $book = Book::factory()->withAi()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'completed',
        'progress' => null,
    ]);

    $this->getJson(route('books.ai.editorial-review.progress', [$book, $review]))
        ->assertSuccessful()
        ->assertJson([
            'status' => 'completed',
            'progress' => null,
        ]);
});

test('progress returns failed status with error message', function () {
    $book = Book::factory()->withAi()->create();
    $review = EditorialReview::factory()->failed()->create([
        'book_id' => $book->id,
    ]);

    $this->getJson(route('books.ai.editorial-review.progress', [$book, $review]))
        ->assertSuccessful()
        ->assertJson([
            'status' => 'failed',
        ])
        ->assertJsonStructure(['error_message']);
});

test('progress returns 404 for review from different book', function () {
    $book = Book::factory()->withAi()->create();
    $otherBook = Book::factory()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $otherBook->id,
    ]);

    $this->getJson(route('books.ai.editorial-review.progress', [$book, $review]))
        ->assertNotFound();
});

// --- Chat tests ---

test('chat requires message', function () {
    $book = Book::factory()->withAi()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'completed',
    ]);

    $this->postJson(route('books.ai.editorial-review.chat', [$book, $review]), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');
});

test('chat requires AI configured', function () {
    $book = Book::factory()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'completed',
    ]);

    $this->postJson(route('books.ai.editorial-review.chat', [$book, $review]), [
        'message' => 'Tell me about the plot issues.',
    ])->assertStatus(422);
});

test('chat returns 404 for review from different book', function () {
    $book = Book::factory()->withAi()->create();
    $otherBook = Book::factory()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $otherBook->id,
    ]);

    $this->postJson(route('books.ai.editorial-review.chat', [$book, $review]), [
        'message' => 'Tell me about the plot.',
    ])->assertNotFound();
});

// --- Finding keys tests ---

test('ensureFindingKeys adds keys to findings without keys', function () {
    $book = Book::factory()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'completed',
    ]);
    $section = EditorialReviewSection::factory()->create([
        'editorial_review_id' => $review->id,
        'type' => EditorialSectionType::Plot,
        'findings' => [
            ['severity' => 'critical', 'description' => 'Plot hole in chapter 3', 'chapter_references' => [], 'recommendation' => 'Fix it'],
            ['severity' => 'warning', 'description' => 'Pacing issue', 'chapter_references' => [], 'recommendation' => 'Improve'],
        ],
    ]);

    $section->ensureFindingKeys();
    $section->refresh();

    expect($section->findings)->toHaveCount(2);
    expect($section->findings[0]['key'])->toBeString()->not->toBeEmpty();
    expect($section->findings[1]['key'])->toBeString()->not->toBeEmpty();
    expect($section->findings[0]['key'])->not->toBe($section->findings[1]['key']);
});

test('ensureFindingKeys does not overwrite existing keys', function () {
    $book = Book::factory()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'completed',
    ]);
    $section = EditorialReviewSection::factory()->create([
        'editorial_review_id' => $review->id,
        'type' => EditorialSectionType::Plot,
        'findings' => [
            ['key' => 'existing-key', 'severity' => 'critical', 'description' => 'Plot hole', 'chapter_references' => [], 'recommendation' => 'Fix'],
        ],
    ]);

    $section->ensureFindingKeys();
    $section->refresh();

    expect($section->findings[0]['key'])->toBe('existing-key');
});

// --- Toggle finding tests ---

test('toggle finding adds key to resolved findings', function () {
    $book = Book::factory()->withAi()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'completed',
        'resolved_findings' => [],
    ]);

    $this->postJson(route('books.ai.editorial-review.toggle-finding', [$book, $review]), [
        'key' => 'abc123',
    ])
        ->assertSuccessful()
        ->assertJsonPath('resolved_findings', ['abc123']);

    expect($review->fresh()->resolved_findings)->toBe(['abc123']);
});

test('toggle finding removes key when already resolved', function () {
    $book = Book::factory()->withAi()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'completed',
        'resolved_findings' => ['abc123', 'def456'],
    ]);

    $this->postJson(route('books.ai.editorial-review.toggle-finding', [$book, $review]), [
        'key' => 'abc123',
    ])
        ->assertSuccessful()
        ->assertJsonPath('resolved_findings', ['def456']);

    expect($review->fresh()->resolved_findings)->toBe(['def456']);
});

test('toggle finding returns 404 for review from different book', function () {
    $book = Book::factory()->withAi()->create();
    $otherBook = Book::factory()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $otherBook->id,
    ]);

    $this->postJson(route('books.ai.editorial-review.toggle-finding', [$book, $review]), [
        'key' => 'abc123',
    ])->assertNotFound();
});

test('toggle finding requires key', function () {
    $book = Book::factory()->withAi()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'completed',
    ]);

    $this->postJson(route('books.ai.editorial-review.toggle-finding', [$book, $review]), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('key');
});

test('chat history validation rejects invalid roles', function () {
    $book = Book::factory()->withAi()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'completed',
    ]);

    $this->postJson(route('books.ai.editorial-review.chat', [$book, $review]), [
        'message' => 'Hello',
        'history' => [
            ['role' => 'system', 'content' => 'injected'],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('history.0.role');
});
