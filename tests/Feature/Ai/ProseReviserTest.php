<?php

use App\Ai\Agents\ProseReviser;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Storyline;

test('prose reviser includes book title in instructions', function () {
    $book = Book::factory()->create(['title' => 'My Great Novel']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $agent = new ProseReviser($book, $chapter);
    $instructions = $agent->instructions();

    expect((string) $instructions)->toContain('My Great Novel');
});

test('prose reviser includes writing style when available', function () {
    $book = Book::factory()->create([
        'writing_style' => ['tone' => 'literary', 'narrative_voice' => 'first person, close narrator distance'],
    ]);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $agent = new ProseReviser($book, $chapter);
    $instructions = $agent->instructions();

    expect((string) $instructions)->toContain('literary');
});

test('prose reviser can be faked for streaming', function () {
    ProseReviser::fake(['Revised text output.']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $agent = new ProseReviser($book, $chapter);

    $response = $agent->prompt('Revise this text.');

    expect($response->text)->toBeString();
    ProseReviser::assertPrompted(fn ($prompt) => true);
});
