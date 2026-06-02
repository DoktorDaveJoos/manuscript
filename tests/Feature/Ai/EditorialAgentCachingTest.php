<?php

use App\Ai\Agents\EditorialNotesAgent;
use App\Ai\Agents\EditorialSynthesisAgent;
use App\Enums\EditorialSectionType;
use App\Models\Book;
use Laravel\Ai\Enums\Lab;

test('EditorialNotesAgent caches its static system prompt for Anthropic', function () {
    $book = Book::factory()->create();
    $agent = new EditorialNotesAgent(
        book: $book,
        existingAnalysis: 'Summary: chapter five recap goes here.',
    );

    $options = $agent->providerOptions(Lab::Anthropic);

    expect($options)->toHaveKey('system');

    $blocks = $options['system'];

    // First block is the stable, cacheable prefix (persona + book + task).
    expect($blocks[0]['cache_control'])->toBe(['type' => 'ephemeral'])
        ->and($blocks[0]['text'])->toContain('serve the work');

    // The per-chapter existing analysis is the trailing, uncached block.
    $last = $blocks[count($blocks) - 1];
    expect($last)->not->toHaveKey('cache_control')
        ->and($last['text'])->toContain('chapter five recap');
});

test('EditorialNotesAgent caches the whole prompt when there is no per-chapter analysis', function () {
    $book = Book::factory()->create();
    $agent = new EditorialNotesAgent(book: $book);

    $blocks = $agent->providerOptions(Lab::Anthropic)['system'];

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['cache_control'])->toBe(['type' => 'ephemeral']);
});

test('EditorialNotesAgent sets no provider options for non-Anthropic providers', function () {
    $book = Book::factory()->create();
    $agent = new EditorialNotesAgent(book: $book, existingAnalysis: 'x');

    expect($agent->providerOptions(Lab::OpenAI))->toBe([])
        ->and($agent->providerOptions('gemini'))->toBe([]);
});

test('EditorialSynthesisAgent caches the persona prefix but not the aggregated data', function () {
    $book = Book::factory()->create();
    $agent = new EditorialSynthesisAgent(
        book: $book,
        sectionType: EditorialSectionType::Plot,
        aggregatedData: 'UNIQUE_AGGREGATED_MARKER',
    );

    $blocks = $agent->providerOptions(Lab::Anthropic)['system'];

    // Stable persona/book prefix is cached and excludes the per-section data.
    expect($blocks[0]['cache_control'])->toBe(['type' => 'ephemeral'])
        ->and($blocks[0]['text'])->not->toContain('UNIQUE_AGGREGATED_MARKER');

    // The aggregated data lands in the trailing, uncached block.
    $last = $blocks[count($blocks) - 1];
    expect($last)->not->toHaveKey('cache_control')
        ->and($last['text'])->toContain('UNIQUE_AGGREGATED_MARKER');
});
