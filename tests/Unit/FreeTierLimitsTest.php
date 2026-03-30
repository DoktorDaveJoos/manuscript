<?php

use App\Models\Book;
use App\Models\Character;
use App\Models\License;
use App\Models\Storyline;
use App\Models\WikiEntry;
use App\Services\FreeTierLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// --- Book creation ---

test('canCreateBook returns true when licensed', function () {
    License::factory()->create();
    Book::factory()->create();

    expect(FreeTierLimits::canCreateBook())->toBeTrue();
});

test('canCreateBook returns true when under limit', function () {
    expect(FreeTierLimits::canCreateBook())->toBeTrue();
});

test('canCreateBook returns false when at limit without license', function () {
    Book::factory()->create();

    expect(FreeTierLimits::canCreateBook())->toBeFalse();
});

// --- Storyline creation ---

test('canCreateStoryline returns true when licensed', function () {
    License::factory()->create();
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    expect(FreeTierLimits::canCreateStoryline($book))->toBeTrue();
});

test('canCreateStoryline returns true when under limit', function () {
    $book = Book::factory()->create();

    expect(FreeTierLimits::canCreateStoryline($book))->toBeTrue();
});

test('canCreateStoryline returns false when at limit without license', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    expect(FreeTierLimits::canCreateStoryline($book))->toBeFalse();
});

// --- Wiki entry creation ---

test('canCreateWikiEntry returns true when licensed', function () {
    License::factory()->create();
    $book = Book::factory()->create();
    Character::factory()->count(5)->for($book)->create();

    expect(FreeTierLimits::canCreateWikiEntry($book))->toBeTrue();
});

test('canCreateWikiEntry returns true when under limit', function () {
    $book = Book::factory()->create();
    Character::factory()->count(3)->for($book)->create();

    expect(FreeTierLimits::canCreateWikiEntry($book))->toBeTrue();
});

test('canCreateWikiEntry returns false when at limit without license', function () {
    $book = Book::factory()->create();
    Character::factory()->count(3)->for($book)->create();
    WikiEntry::factory()->count(2)->for($book)->create();

    expect(FreeTierLimits::canCreateWikiEntry($book))->toBeFalse();
});

test('wikiEntryCount combines characters and wiki entries', function () {
    $book = Book::factory()->create();
    Character::factory()->count(2)->for($book)->create();
    WikiEntry::factory()->count(3)->for($book)->create();

    expect(FreeTierLimits::wikiEntryCount($book))->toBe(5);
});

// --- Export format ---

test('canExportFormat returns true for free formats without license', function () {
    expect(FreeTierLimits::canExportFormat('docx'))->toBeTrue();
    expect(FreeTierLimits::canExportFormat('txt'))->toBeTrue();
});

test('canExportFormat returns false for pro formats without license', function () {
    expect(FreeTierLimits::canExportFormat('pdf'))->toBeFalse();
    expect(FreeTierLimits::canExportFormat('epub'))->toBeFalse();
    expect(FreeTierLimits::canExportFormat('kdp'))->toBeFalse();
});

test('canExportFormat returns true for all formats when licensed', function () {
    License::factory()->create();

    expect(FreeTierLimits::canExportFormat('docx'))->toBeTrue();
    expect(FreeTierLimits::canExportFormat('pdf'))->toBeTrue();
    expect(FreeTierLimits::canExportFormat('epub'))->toBeTrue();
    expect(FreeTierLimits::canExportFormat('kdp'))->toBeTrue();
});

test('isProExportFormat identifies pro-only formats', function () {
    expect(FreeTierLimits::isProExportFormat('pdf'))->toBeTrue();
    expect(FreeTierLimits::isProExportFormat('epub'))->toBeTrue();
    expect(FreeTierLimits::isProExportFormat('kdp'))->toBeTrue();
    expect(FreeTierLimits::isProExportFormat('docx'))->toBeFalse();
    expect(FreeTierLimits::isProExportFormat('txt'))->toBeFalse();
});
