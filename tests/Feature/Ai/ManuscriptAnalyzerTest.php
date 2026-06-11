<?php

use App\Ai\Agents\ManuscriptAnalyzer;
use App\Enums\AnalysisType;
use App\Models\Book;
use Laravel\Ai\Enums\Lab;

test('manuscript analyzer returns structured analysis', function () {
    ManuscriptAnalyzer::fake();

    $book = Book::factory()->withAi()->create();

    $agent = new ManuscriptAnalyzer($book, AnalysisType::Plothole);
    $response = $agent->prompt('Analyze plot holes of the manuscript (book ID: 1).');

    expect($response['score'])->toBeInt()
        ->and($response['findings'])->toBeArray()
        ->and($response['recommendations'])->toBeArray();

    ManuscriptAnalyzer::assertPrompted(fn ($prompt) => true);
});

test('manuscript analyzer uses correct instructions per analysis type', function (AnalysisType $type, string $expectedKeyword) {
    ManuscriptAnalyzer::fake();

    $book = Book::factory()->withAi()->create();

    $agent = new ManuscriptAnalyzer($book, $type);
    $instructions = $agent->instructions();

    expect((string) $instructions)->toContain($expectedKeyword);
})->with([
    'plothole' => [AnalysisType::Plothole, 'plot holes'],
    'character_consistency' => [AnalysisType::CharacterConsistency, 'character consistency'],
    'plot_deviation' => [AnalysisType::PlotDeviation, 'plot progression'],
]);

test('manuscript analyzer includes book title in instructions', function () {
    $book = Book::factory()->create(['title' => 'The Great Novel']);

    $agent = new ManuscriptAnalyzer($book, AnalysisType::Plothole);
    $instructions = $agent->instructions();

    expect((string) $instructions)->toContain('The Great Novel');
});

test('manuscript analyzer exposes retrieval tools when no inline context is given', function () {
    $book = Book::factory()->withAi()->create();

    $agent = new ManuscriptAnalyzer($book, AnalysisType::Plothole);

    expect(iterator_to_array($agent->tools()))->toHaveCount(2);
});

test('manuscript analyzer runs single-shot without tools when context is inlined', function () {
    $book = Book::factory()->withAi()->create();

    $agent = new ManuscriptAnalyzer($book, AnalysisType::CharacterConsistency, inlineContext: 'UNIQUE_CONTEXT_MARKER');

    expect(iterator_to_array($agent->tools()))->toBeEmpty()
        ->and((string) $agent->instructions())->toContain('UNIQUE_CONTEXT_MARKER');
});

test('manuscript analyzer caches the persona prefix but not the inline context for Anthropic', function () {
    $book = Book::factory()->withAi()->create();

    $agent = new ManuscriptAnalyzer($book, AnalysisType::PlotDeviation, inlineContext: 'UNIQUE_CONTEXT_MARKER');
    $blocks = $agent->providerOptions(Lab::Anthropic)['system'];

    expect($blocks[0]['cache_control'])->toBe(['type' => 'ephemeral'])
        ->and($blocks[0]['text'])->not->toContain('UNIQUE_CONTEXT_MARKER');

    $last = $blocks[count($blocks) - 1];
    expect($last)->not->toHaveKey('cache_control')
        ->and($last['text'])->toContain('UNIQUE_CONTEXT_MARKER');
});

test('manuscript analyzer includes middleware', function () {
    $book = Book::factory()->withAi()->create();
    $agent = new ManuscriptAnalyzer($book, AnalysisType::Plothole);

    expect($agent->middleware())->toHaveCount(1);
});
