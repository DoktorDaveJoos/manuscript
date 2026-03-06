<?php

use App\Ai\Agents\ProseReviser;
use App\Models\Book;

test('prose reviser includes book title in instructions', function () {
    $book = Book::factory()->create(['title' => 'My Great Novel']);

    $agent = new ProseReviser($book);
    $instructions = $agent->instructions();

    expect((string) $instructions)->toContain('My Great Novel');
});

test('prose reviser includes writing style when available', function () {
    $book = Book::factory()->create([
        'writing_style' => ['tone' => 'literary', 'pov' => 'first_person'],
    ]);

    $agent = new ProseReviser($book);
    $instructions = $agent->instructions();

    expect((string) $instructions)->toContain('literary');
});

test('prose reviser has tools', function () {
    $book = Book::factory()->create();
    $agent = new ProseReviser($book);

    expect(iterator_to_array($agent->tools()))->toHaveCount(1);
});

test('prose reviser can be faked for streaming', function () {
    ProseReviser::fake(['Revised text output.']);

    $book = Book::factory()->withAi()->create();
    $agent = new ProseReviser($book);

    $response = $agent->prompt('Revise this text.');

    expect($response->text)->toBeString();
    ProseReviser::assertPrompted(fn ($prompt) => true);
});
