<?php

use App\Enums\EditorialSectionType;
use App\Jobs\RunEditorialReviewJob;
use App\Models\Book;
use App\Models\EditorialReview;
use App\Models\EditorialReviewSection;
use App\Models\License;
use App\Models\Scene;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    License::factory()->create();
    $this->withoutVite();
});

// --- Authorization tests ---

test('index requires license', function () {
    clearLicense();

    $book = Book::factory()->create();

    $this->getJson(route('books.ai.editorial-review.index', $book))
        ->assertForbidden();
});

test('store requires license', function () {
    clearLicense();

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

test('ensureFindingKeys regenerates stale keys to match current hash', function () {
    $book = Book::factory()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'completed',
    ]);
    $section = EditorialReviewSection::factory()->create([
        'editorial_review_id' => $review->id,
        'type' => EditorialSectionType::Plot,
        'findings' => [
            ['key' => 'stale-key', 'severity' => 'critical', 'description' => 'Plot hole', 'chapter_references' => [], 'recommendation' => 'Fix'],
        ],
    ]);

    $section->ensureFindingKeys();
    $section->refresh();

    $expected = EditorialReviewSection::findingKey('plot', 'Plot hole');
    expect($section->findings[0]['key'])->toBe($expected);
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

test('chat returns 422 when review is not completed', function () {
    $book = Book::factory()->withAi()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'analyzing',
    ]);

    $this->postJson(route('books.ai.editorial-review.chat', [$book, $review]), [
        'message' => 'Tell me about the plot.',
    ])->assertStatus(422);
});

test('index returns in-progress review as latest review', function () {
    $book = Book::factory()->withAi()->create();
    EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'completed',
    ]);
    $inProgress = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'analyzing',
    ]);

    $this->get(route('books.ai.editorial-review.index', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('books/editorial-review')
            ->where('latestReview.id', $inProgress->id)
            ->where('latestReview.status', 'analyzing')
        );
});

test('index returns failed review when no in-progress or completed exists', function () {
    $book = Book::factory()->withAi()->create();
    $failed = EditorialReview::factory()->failed()->create([
        'book_id' => $book->id,
    ]);

    $this->get(route('books.ai.editorial-review.index', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('books/editorial-review')
            ->where('latestReview.id', $failed->id)
            ->where('latestReview.status', 'failed')
        );
});

test('index prefers the newest review when created_at ties', function () {
    $book = Book::factory()->withAi()->create();

    $this->freezeSecond();

    EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'completed',
    ]);
    $newer = EditorialReview::factory()->failed()->create([
        'book_id' => $book->id,
    ]);

    $this->get(route('books.ai.editorial-review.index', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('books/editorial-review')
            ->where('latestReview.id', $newer->id)
        );
});

test('store fails a stale stuck review instead of blocking new reviews', function () {
    Queue::fake();

    $book = Book::factory()->withAi()->create();
    $stale = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'analyzing',
        'updated_at' => now()->subHour(),
    ]);

    $this->postJson(route('books.ai.editorial-review.store', $book))
        ->assertOk();

    $stale->refresh();
    expect($stale->status)->toBe('failed')
        ->and($stale->error_message)->toContain('timed out')
        ->and($stale->error_code)->toBe('timeout');

    Queue::assertPushed(RunEditorialReviewJob::class);
});

// --- Resume tests ---

test('resume requires license', function () {
    clearLicense();

    $book = Book::factory()->create();
    $review = EditorialReview::factory()->failed()->create(['book_id' => $book->id]);

    $this->postJson(route('books.ai.editorial-review.resume', [$book, $review]))
        ->assertForbidden();
});

test('resume requires AI configured', function () {
    $book = Book::factory()->create();
    $review = EditorialReview::factory()->failed()->create(['book_id' => $book->id]);

    $this->postJson(route('books.ai.editorial-review.resume', [$book, $review]))
        ->assertUnprocessable();
});

test('resume returns 404 for review from different book', function () {
    $book = Book::factory()->withAi()->create();
    $otherBook = Book::factory()->create();
    $review = EditorialReview::factory()->failed()->create(['book_id' => $otherBook->id]);

    $this->postJson(route('books.ai.editorial-review.resume', [$book, $review]))
        ->assertNotFound();
});

test('resume rejects reviews that have not failed', function () {
    Queue::fake();

    $book = Book::factory()->withAi()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'completed',
    ]);

    $this->postJson(route('books.ai.editorial-review.resume', [$book, $review]))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Only a failed review can be resumed.');

    Queue::assertNothingPushed();
});

test('resume rejects when another review is already in progress', function () {
    Queue::fake();

    $book = Book::factory()->withAi()->create();
    $failed = EditorialReview::factory()->failed()->create(['book_id' => $book->id]);
    EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'analyzing',
    ]);

    $this->postJson(route('books.ai.editorial-review.resume', [$book, $failed]))
        ->assertUnprocessable();

    Queue::assertNothingPushed();
});

test('resume resets the failed review and re-dispatches the pipeline on the same record', function () {
    Queue::fake();

    $book = Book::factory()->withAi()->create();
    $review = EditorialReview::factory()->failed()->create([
        'book_id' => $book->id,
        'error_code' => 'rate_limited',
    ]);

    $this->postJson(route('books.ai.editorial-review.resume', [$book, $review]))
        ->assertOk()
        ->assertJsonPath('review.id', $review->id);

    $review->refresh();

    expect($review->status)->toBe('pending')
        ->and($review->error_message)->toBeNull()
        ->and($review->error_code)->toBeNull()
        ->and(EditorialReview::where('book_id', $book->id)->count())->toBe(1);

    Queue::assertPushed(RunEditorialReviewJob::class, fn ($job) => $job->review->is($review));
});

test('progress includes the error code for failed reviews', function () {
    $book = Book::factory()->withAi()->create();
    $review = EditorialReview::factory()->failed()->create([
        'book_id' => $book->id,
        'error_code' => 'rate_limited',
    ]);

    $this->getJson(route('books.ai.editorial-review.progress', [$book, $review]))
        ->assertSuccessful()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('error_code', 'rate_limited');
});

test('store leaves stale reviews of other books to the scheduled cleanup', function () {
    Queue::fake();

    $otherBook = Book::factory()->create();
    EditorialReview::factory()->create([
        'book_id' => $otherBook->id,
        'status' => 'analyzing',
        'updated_at' => now()->subHour(),
    ]);

    $book = Book::factory()->withAi()->create();

    $this->postJson(route('books.ai.editorial-review.store', $book))
        ->assertOk();

    // Stale runs of other books are left to the scheduled cleanup.
    expect(EditorialReview::where('book_id', $otherBook->id)->first()->status)
        ->toBe('analyzing');
});

test('index caps reviews to 20', function () {
    $book = Book::factory()->withAi()->create();
    EditorialReview::factory()->count(25)->create([
        'book_id' => $book->id,
        'status' => 'completed',
    ]);

    $this->get(route('books.ai.editorial-review.index', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('books/editorial-review')
            ->has('reviews', 20)
        );
});

// --- Edited chapters count ---

test('index reports chapters edited since the last completed review', function () {
    [$book, $chapters] = createBookWithChapters(3);

    EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'completed',
        'completed_at' => now()->subDay(),
    ]);

    Scene::query()
        ->whereIn('chapter_id', [$chapters[0]->id, $chapters[1]->id])
        ->update(['updated_at' => now()]);
    Scene::query()
        ->where('chapter_id', $chapters[2]->id)
        ->update(['updated_at' => now()->subDays(2)]);

    $this->get(route('books.ai.editorial-review.index', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('editedChaptersCount', 2)
        );
});

test('index reports null edited chapters count without a completed review', function () {
    $book = Book::factory()->withAi()->create();
    EditorialReview::factory()->failed()->create([
        'book_id' => $book->id,
    ]);

    $this->get(route('books.ai.editorial-review.index', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('editedChaptersCount', null)
        );
});

test('edited chapters count is measured against the newest completed review', function () {
    [$book, $chapters] = createBookWithChapters(2);

    EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'completed',
        'completed_at' => now()->subDays(10),
    ]);
    $latest = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'completed',
        'completed_at' => now()->subDay(),
    ]);

    // Edited between the two reviews — only the older review predates it.
    Scene::query()
        ->whereIn('chapter_id', collect($chapters)->pluck('id'))
        ->update(['updated_at' => now()->subDays(5)]);

    $this->get(route('books.ai.editorial-review.show', [$book, $latest]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('editedChaptersCount', 0)
        );
});

test('chat rejects invalid conversation_id format', function () {
    $book = Book::factory()->withAi()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'completed',
    ]);

    $this->postJson(route('books.ai.editorial-review.chat', [$book, $review]), [
        'message' => 'Hello',
        'conversation_id' => str_repeat('x', 37),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('conversation_id');
});
