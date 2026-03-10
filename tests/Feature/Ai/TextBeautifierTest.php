<?php

use App\Ai\Agents\TextBeautifier;
use App\Models\Book;

test('text beautifier includes book title in instructions', function () {
    $book = Book::factory()->create(['title' => 'My Great Novel']);

    $agent = new TextBeautifier($book);
    $instructions = $agent->instructions();

    expect((string) $instructions)->toContain('My Great Novel');
});

test('text beautifier emphasizes no word changes', function () {
    $book = Book::factory()->create();

    $agent = new TextBeautifier($book);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('NOT change')
        ->toContain('STRUCTURAL');
});

test('text beautifier includes writing style when available', function () {
    $book = Book::factory()->create([
        'writing_style' => ['tone' => 'literary', 'narrative_voice' => 'first person, close narrator distance'],
    ]);

    $agent = new TextBeautifier($book);
    $instructions = $agent->instructions();

    expect((string) $instructions)->toContain('literary');
});

test('text beautifier can be faked for streaming', function () {
    TextBeautifier::fake(['Restructured text output.']);

    $book = Book::factory()->withAi()->create();
    $agent = new TextBeautifier($book);

    $response = $agent->prompt('Restructure this text.');

    expect($response->text)->toBeString();
    TextBeautifier::assertPrompted(fn ($prompt) => true);
});
