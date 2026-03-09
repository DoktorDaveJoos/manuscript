<?php

use App\Ai\Agents\NextChapterAdvisor;
use App\Models\Book;

test('next chapter advisor includes book title in instructions', function () {
    $book = Book::factory()->create(['title' => 'My Great Novel']);

    $agent = new NextChapterAdvisor($book);
    $instructions = $agent->instructions();

    expect((string) $instructions)->toContain('My Great Novel');
});

test('next chapter advisor includes writing style when available', function () {
    $book = Book::factory()->create([
        'writing_style' => ['tone' => 'literary', 'narrative_voice' => 'first person, close narrator distance'],
    ]);

    $agent = new NextChapterAdvisor($book);
    $instructions = $agent->instructions();

    expect((string) $instructions)
        ->toContain('literary')
        ->toContain("author's prose style");
});

test('next chapter advisor omits writing style when empty', function () {
    $book = Book::factory()->create(['writing_style' => null]);

    $agent = new NextChapterAdvisor($book);
    $instructions = $agent->instructions();

    expect((string) $instructions)->not->toContain("author's prose style");
});

test('next chapter advisor has tools', function () {
    $book = Book::factory()->create();
    $agent = new NextChapterAdvisor($book);

    expect(iterator_to_array($agent->tools()))->toHaveCount(2);
});

test('next chapter advisor can be faked for structured output', function () {
    NextChapterAdvisor::fake();

    $book = Book::factory()->withAi()->create();
    $agent = new NextChapterAdvisor($book);

    $response = $agent->prompt('What should happen next?');

    expect($response['suggestion'])->toBeString()
        ->and($response['open_plot_points'])->toBeArray()
        ->and($response['neglected_characters'])->toBeArray()
        ->and($response['hook_ideas'])->toBeArray();

    NextChapterAdvisor::assertPrompted(fn ($prompt) => true);
});
