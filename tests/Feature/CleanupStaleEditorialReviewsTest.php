<?php

use App\Models\Book;
use App\Models\EditorialReview;
use App\Models\License;

beforeEach(function () {
    License::factory()->create();
});

test('cleanup marks stale analyzing review as failed', function () {
    $book = Book::factory()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'analyzing',
        'updated_at' => now()->subMinutes(60),
    ]);

    $this->artisan('editorial-reviews:cleanup-stale')
        ->assertSuccessful();

    $review->refresh();
    expect($review->status)->toBe('failed')
        ->and($review->error_message)->toContain('timed out');
});

test('cleanup marks stale pending review as failed', function () {
    $book = Book::factory()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'pending',
        'updated_at' => now()->subMinutes(50),
    ]);

    $this->artisan('editorial-reviews:cleanup-stale')
        ->assertSuccessful();

    expect($review->fresh()->status)->toBe('failed');
});

test('cleanup marks stale synthesizing review as failed', function () {
    $book = Book::factory()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'synthesizing',
        'updated_at' => now()->subMinutes(50),
    ]);

    $this->artisan('editorial-reviews:cleanup-stale')
        ->assertSuccessful();

    expect($review->fresh()->status)->toBe('failed');
});

test('cleanup does not affect recent in-progress review', function () {
    $book = Book::factory()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'analyzing',
        'updated_at' => now()->subMinutes(10),
    ]);

    $this->artisan('editorial-reviews:cleanup-stale')
        ->assertSuccessful();

    expect($review->fresh()->status)->toBe('analyzing');
});

test('cleanup does not affect completed reviews', function () {
    $book = Book::factory()->create();
    $review = EditorialReview::factory()->create([
        'book_id' => $book->id,
        'status' => 'completed',
        'updated_at' => now()->subMinutes(120),
    ]);

    $this->artisan('editorial-reviews:cleanup-stale')
        ->assertSuccessful();

    expect($review->fresh()->status)->toBe('completed');
});

test('cleanup does not affect already failed reviews', function () {
    $book = Book::factory()->create();
    $review = EditorialReview::factory()->failed()->create([
        'book_id' => $book->id,
        'updated_at' => now()->subMinutes(120),
    ]);

    $this->artisan('editorial-reviews:cleanup-stale')
        ->assertSuccessful();

    expect($review->fresh()->status)->toBe('failed');
});
