<?php

use App\Ai\Agents\StoryBibleBuilder;
use App\Models\Book;

test('story bible builder returns structured story bible', function () {
    StoryBibleBuilder::fake();

    $book = Book::factory()->withAi()->create();

    $agent = new StoryBibleBuilder($book);
    $response = $agent->prompt('Build a Story Bible from the following manuscript data.');

    expect($response['themes'])->toBeArray()
        ->and($response['style_rules'])->toBeArray()
        ->and($response['genre_rules'])->toBeArray()
        ->and($response['timeline'])->toBeArray();

    StoryBibleBuilder::assertPrompted(fn ($prompt) => true);
});

test('story bible builder includes book title in instructions', function () {
    $book = Book::factory()->create(['title' => 'Epic Fantasy']);

    $agent = new StoryBibleBuilder($book);
    $instructions = $agent->instructions();

    expect((string) $instructions)->toContain('Epic Fantasy');
});

test('story bible builder includes book id in instructions for search tool', function () {
    $book = Book::factory()->create();

    $agent = new StoryBibleBuilder($book);
    $instructions = $agent->instructions();

    expect((string) $instructions)->toContain("The book ID is {$book->id}");
});

test('story bible builder includes middleware', function () {
    $book = Book::factory()->withAi()->create();
    $agent = new StoryBibleBuilder($book);

    expect($agent->middleware())->toHaveCount(1);
});
