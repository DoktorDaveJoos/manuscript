<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\EditorialReview;
use App\Models\EditorialReviewChapterNote;

it('returns chapter data as json', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();

    $response = $this->getJson("/books/{$book->id}/chapters/{$chapter->id}/json");

    $response->assertOk()
        ->assertJsonStructure([
            'chapter' => ['id', 'title', 'scenes', 'current_version'],
            'prosePassRules',
            'proofreadingConfig',
            'customDictionary',
            'editorialChapterNote',
        ]);
});

test('showJson returns editorialChapterNote from latest completed review', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();

    $review = EditorialReview::factory()->for($book)->create([
        'status' => 'completed',
    ]);

    EditorialReviewChapterNote::factory()
        ->for($review)
        ->for($chapter)
        ->create([
            'notes' => ['chapter_note' => 'Foreshadow the twist here'],
        ]);

    $response = $this->getJson("/books/{$book->id}/chapters/{$chapter->id}/json");

    $response->assertOk()
        ->assertJsonPath('editorialChapterNote', 'Foreshadow the twist here');
});

test('showJson prefers chapter note from the latest completed review', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();

    $olderReview = EditorialReview::factory()->for($book)->create([
        'status' => 'completed',
        'created_at' => now()->subDay(),
    ]);
    EditorialReviewChapterNote::factory()
        ->for($olderReview)
        ->for($chapter)
        ->create([
            'notes' => ['chapter_note' => 'Older note'],
        ]);

    $newerReview = EditorialReview::factory()->for($book)->create([
        'status' => 'completed',
        'created_at' => now(),
    ]);
    EditorialReviewChapterNote::factory()
        ->for($newerReview)
        ->for($chapter)
        ->create([
            'notes' => ['chapter_note' => 'Newer note'],
        ]);

    $response = $this->getJson("/books/{$book->id}/chapters/{$chapter->id}/json");

    $response->assertOk()
        ->assertJsonPath('editorialChapterNote', 'Newer note');
});

test('showJson returns null editorialChapterNote when no completed review exists', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();

    $response = $this->getJson("/books/{$book->id}/chapters/{$chapter->id}/json");

    $response->assertOk()
        ->assertJsonPath('editorialChapterNote', null);
});

test('showJson ignores non-completed reviews for editorialChapterNote', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();

    $review = EditorialReview::factory()->for($book)->pending()->create();
    EditorialReviewChapterNote::factory()
        ->for($review)
        ->for($chapter)
        ->create([
            'notes' => ['chapter_note' => 'Should not appear'],
        ]);

    $response = $this->getJson("/books/{$book->id}/chapters/{$chapter->id}/json");

    $response->assertOk()
        ->assertJsonPath('editorialChapterNote', null);
});

test('showJson returns null editorialChapterNote when review exists but has no note for this chapter', function () {
    $book = Book::factory()->create();
    $chapterA = Chapter::factory()->for($book)->create();
    $chapterB = Chapter::factory()->for($book)->create();

    $review = EditorialReview::factory()->for($book)->create([
        'status' => 'completed',
    ]);

    EditorialReviewChapterNote::factory()
        ->for($review)
        ->for($chapterA)
        ->create([
            'notes' => ['chapter_note' => 'Note only for A'],
        ]);

    $response = $this->getJson("/books/{$book->id}/chapters/{$chapterB->id}/json");

    $response->assertOk()
        ->assertJsonPath('editorialChapterNote', null);
});
