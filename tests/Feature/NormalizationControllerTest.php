<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Storyline;

it('previews book normalization and returns changes', function () {
    [$book, $chapters] = createBookWithChapters(2);

    $response = $this->postJson(route('books.normalize.preview', $book));

    $response->assertOk()
        ->assertJsonStructure([
            'chapters',
            'total_changes',
        ]);
});

it('applies book normalization', function () {
    [$book, $chapters] = createBookWithChapters(2);

    $response = $this->postJson(route('books.normalize.apply', $book));

    $response->assertOk()
        ->assertJsonStructure(['applied_chapters']);

    expect($response->json('applied_chapters'))->toBeGreaterThanOrEqual(0);
});

it('previews chapter normalization', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];

    $response = $this->postJson(route('chapters.normalize.preview', [$book, $chapter]));

    $response->assertOk()
        ->assertJsonStructure([
            'chapters',
            'total_changes',
        ]);
});

it('applies chapter normalization', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];

    $response = $this->postJson(route('chapters.normalize.apply', [$book, $chapter]));

    $response->assertOk()
        ->assertJsonStructure(['applied']);
});

it('returns empty changes for chapter without content', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    // Chapter has no version/content
    $response = $this->postJson(route('chapters.normalize.preview', [$book, $chapter]));

    $response->assertOk()
        ->assertJson([
            'chapters' => [],
            'total_changes' => 0,
        ]);
});
