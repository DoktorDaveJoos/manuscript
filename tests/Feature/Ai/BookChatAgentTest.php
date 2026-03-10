<?php

use App\Ai\Agents\BookChatAgent;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Storyline;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;

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

test('book chat agent includes chapter context in instructions', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 5,
        'title' => 'The Storm',
    ]);

    $agent = new BookChatAgent($book, $chapter);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Chapter 5')
        ->toContain('The Storm')
        ->toContain("chapter_id={$chapter->id}");
});

test('book chat agent omits chapter context when no chapter given', function () {
    $book = Book::factory()->create();

    $agent = new BookChatAgent($book);
    $instructions = (string) $agent->instructions();

    expect($instructions)->not->toContain('currently editing');
});

test('book chat agent messages converts history to message objects', function () {
    $book = Book::factory()->create();
    $history = [
        ['role' => 'user', 'content' => 'Who is the protagonist?'],
        ['role' => 'assistant', 'content' => 'The protagonist is Elena.'],
        ['role' => 'user', 'content' => 'Tell me more about her.'],
    ];

    $agent = new BookChatAgent($book, null, $history);
    $messages = iterator_to_array($agent->messages());

    expect($messages)->toHaveCount(3);
    expect($messages[0])->toBeInstanceOf(UserMessage::class);
    expect($messages[0]->content)->toBe('Who is the protagonist?');
    expect($messages[1])->toBeInstanceOf(AssistantMessage::class);
    expect($messages[1]->content)->toBe('The protagonist is Elena.');
    expect($messages[2])->toBeInstanceOf(UserMessage::class);
});

test('book chat agent messages returns empty when no history', function () {
    $book = Book::factory()->create();

    $agent = new BookChatAgent($book);
    $messages = iterator_to_array($agent->messages());

    expect($messages)->toBeEmpty();
});
