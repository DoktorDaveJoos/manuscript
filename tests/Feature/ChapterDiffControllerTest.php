<?php

use App\Enums\VersionSource;
use App\Enums\VersionStatus;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\License;
use App\Models\Storyline;

beforeEach(function () {
    License::factory()->create();
});

test('show renders the diff page with current and previous versions', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $previous = ChapterVersion::factory()->for($chapter)->create([
        'version_number' => 4,
        'content' => '<p>Before.</p>',
        'is_current' => false,
        'status' => VersionStatus::Accepted,
    ]);
    $current = ChapterVersion::factory()->for($chapter)->create([
        'version_number' => 5,
        'content' => '<p>After AI.</p>',
        'is_current' => true,
        'status' => VersionStatus::Accepted,
        'source' => VersionSource::ContinueWriting,
    ]);

    $this->get(route('chapters.diff.show', [$book, $chapter, $current]))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('chapters/diff')
                ->where('currentVersion.id', $previous->id)
                ->where('currentVersion.version_number', 4)
                ->where('pendingVersion.id', $current->id)
                ->where('pendingVersion.version_number', 5)
                ->where('pendingVersion.source', 'continue_writing'),
        );
});

test('show returns null currentVersion when there is no prior version', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $only = ChapterVersion::factory()->for($chapter)->create([
        'version_number' => 1,
        'is_current' => true,
    ]);

    $this->get(route('chapters.diff.show', [$book, $chapter, $only]))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->where('currentVersion', null),
        );
});

test('show 404s when chapter does not belong to the book', function () {
    $book = Book::factory()->withAi()->create();
    $otherBook = Book::factory()->create();
    $storyline = Storyline::factory()->for($otherBook)->create();
    $chapter = Chapter::factory()->for($otherBook)->for($storyline)->create();
    $version = ChapterVersion::factory()->for($chapter)->create();

    $this->get(route('chapters.diff.show', [$book, $chapter, $version]))
        ->assertNotFound();
});

test('show 404s when version does not belong to the chapter', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $otherChapter = Chapter::factory()->for($book)->for($storyline)->create();
    $foreign = ChapterVersion::factory()->for($otherChapter)->create();

    $this->get(route('chapters.diff.show', [$book, $chapter, $foreign]))
        ->assertNotFound();
});

test('openWindow returns 422 outside the desktop runtime', function () {
    config(['nativephp-internal.running' => false]);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $version = ChapterVersion::factory()->for($chapter)->create();

    $this->postJson(route('chapters.diff.open', [$book, $chapter, $version]))
        ->assertStatus(422);
});

test('openWindow 404s for cross-book chapter even when desktop is running', function () {
    config(['nativephp-internal.running' => true]);

    $book = Book::factory()->withAi()->create();
    $otherBook = Book::factory()->create();
    $storyline = Storyline::factory()->for($otherBook)->create();
    $chapter = Chapter::factory()->for($otherBook)->for($storyline)->create();
    $version = ChapterVersion::factory()->for($chapter)->create();

    $this->postJson(route('chapters.diff.open', [$book, $chapter, $version]))
        ->assertNotFound();
});

test('closeWindow returns 204 even when desktop is not running', function () {
    config(['nativephp-internal.running' => false]);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $this->postJson(route('chapters.diff.close', $chapter))
        ->assertNoContent();
});
