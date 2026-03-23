<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\License;
use App\Models\Storyline;

function createBookWithChapter(): array
{
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    return [$book, $chapter];
}

function exportPayload(string $format, int $chapterId): array
{
    return [
        'format' => $format,
        'scope' => 'full',
        'chapter_id' => $chapterId,
        'include_chapter_titles' => true,
    ];
}

test('free user can export docx', function () {
    [$book, $chapter] = createBookWithChapter();

    $this->postJson(route('books.settings.export.run', $book), exportPayload('docx', $chapter->id))
        ->assertSuccessful();
});

test('free user can export txt', function () {
    [$book, $chapter] = createBookWithChapter();

    $this->postJson(route('books.settings.export.run', $book), exportPayload('txt', $chapter->id))
        ->assertSuccessful();
});

test('free user cannot export epub', function () {
    [$book, $chapter] = createBookWithChapter();

    $this->postJson(route('books.settings.export.run', $book), exportPayload('epub', $chapter->id))
        ->assertForbidden();
});

test('free user cannot export pdf', function () {
    [$book, $chapter] = createBookWithChapter();

    $this->postJson(route('books.settings.export.run', $book), exportPayload('pdf', $chapter->id))
        ->assertForbidden();
});

test('free user cannot export kdp', function () {
    [$book, $chapter] = createBookWithChapter();

    $this->postJson(route('books.settings.export.run', $book), exportPayload('kdp', $chapter->id))
        ->assertForbidden();
});

test('free user cannot preview pdf', function () {
    [$book, $chapter] = createBookWithChapter();

    $this->postJson(route('books.export.preview', $book), exportPayload('pdf', $chapter->id))
        ->assertForbidden();
});

test('pro user can export all formats', function () {
    License::factory()->create();
    [$book, $chapter] = createBookWithChapter();

    foreach (['docx', 'txt', 'epub'] as $format) {
        $this->postJson(route('books.settings.export.run', $book), exportPayload($format, $chapter->id))
            ->assertSuccessful();
    }
});
