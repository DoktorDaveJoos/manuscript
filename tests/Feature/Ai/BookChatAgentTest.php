<?php

use App\Ai\Agents\BookChatAgent;
use App\Models\Book;

test('book chat agent includes book title in instructions', function () {
    $book = Book::factory()->create(['title' => 'My Great Novel']);

    $agent = new BookChatAgent($book);
    $instructions = $agent->instructions();

    expect((string) $instructions)->toContain('My Great Novel');
});

test('book chat agent includes writing style when available', function () {
    $book = Book::factory()->create([
        'writing_style' => ['tone' => 'literary', 'narrative_voice' => 'first person, close narrator distance'],
    ]);

    $agent = new BookChatAgent($book);
    $instructions = $agent->instructions();

    expect((string) $instructions)
        ->toContain('literary')
        ->toContain("author's prose style");
});

test('book chat agent omits writing style when empty', function () {
    $book = Book::factory()->create(['writing_style' => null]);

    $agent = new BookChatAgent($book);
    $instructions = $agent->instructions();

    expect((string) $instructions)->not->toContain("author's prose style");
});

test('book chat agent has tools', function () {
    $book = Book::factory()->create();
    $agent = new BookChatAgent($book);

    expect(iterator_to_array($agent->tools()))->toHaveCount(2);
});

test('book chat agent can be faked for streaming', function () {
    BookChatAgent::fake(['Here is some helpful information about your manuscript.']);

    $book = Book::factory()->withAi()->create();
    $agent = new BookChatAgent($book);

    $response = $agent->prompt('Tell me about the main character.');

    expect($response->text)->toBeString();
    BookChatAgent::assertPrompted(fn ($prompt) => true);
});
