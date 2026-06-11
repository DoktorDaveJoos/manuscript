<?php

use App\Ai\Agents\WritingStyleExtractor;
use App\Models\Book;
use App\Services\WritingStyleService;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('writing style extractor returns the structured style fields', function () {
    WritingStyleExtractor::fake();

    $book = Book::factory()->withAi()->create();

    $response = (new WritingStyleExtractor($book))->prompt('She walked. The rain kept falling.');

    expect($response['narrative_voice'])->toBeString()
        ->and($response['tense'])->toBeString()
        ->and($response['tone'])->toBeString()
        ->and($response['sentence_rhythm'])->toBeString()
        ->and($response['paragraph_style'])->toBeString()
        ->and($response['vocabulary'])->toBeString()
        ->and($response['figurative_language'])->toBeString()
        ->and($response['pacing'])->toBeString()
        ->and($response['distinctive_features'])->toBeArray();
});

test('writing style extractor instructions enforce concision and forbid padding', function () {
    $book = Book::factory()->create();

    $instructions = (string) (new WritingStyleExtractor($book))->instructions();

    expect($instructions)
        ->toContain('unremarkable')
        ->toContain('at most 5')
        ->toContain('Do not quote');
});

test('writing style extractor responds in the manuscript language rather than a hardcoded one', function () {
    $book = Book::factory()->create(['language' => 'es']);

    $instructions = (string) (new WritingStyleExtractor($book))->instructions();

    expect($instructions)->toContain('same language the manuscript is written in')
        ->and($instructions)->not->toContain('Respond in English');
});

test('writing style service extracts through the extractor agent', function () {
    WritingStyleExtractor::fake();

    $book = Book::factory()->withAi()->create();

    $style = app(WritingStyleService::class)->extract('A long manuscript excerpt.', $book);

    expect($style)->toBeArray()->toHaveKey('narrative_voice');

    WritingStyleExtractor::assertPrompted(fn ($prompt) => $prompt->contains('A long manuscript excerpt.'));
});

test('writing style service aborts when no AI provider is configured', function () {
    $book = Book::factory()->create();

    app(WritingStyleService::class)->extract('Some text.', $book);
})->throws(HttpException::class);
