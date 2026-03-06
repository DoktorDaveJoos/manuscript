<?php

use App\Ai\Agents\ManuscriptAnalyzer;
use App\Enums\AnalysisType;
use App\Models\Book;

test('manuscript analyzer returns structured analysis', function () {
    ManuscriptAnalyzer::fake();

    $book = Book::factory()->withAi()->create();

    $agent = new ManuscriptAnalyzer($book, AnalysisType::Pacing);
    $response = $agent->prompt('Analyze pacing of the manuscript (book ID: 1).');

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
    'pacing' => [AnalysisType::Pacing, 'pacing'],
    'plothole' => [AnalysisType::Plothole, 'plot holes'],
    'character_consistency' => [AnalysisType::CharacterConsistency, 'character consistency'],
    'density' => [AnalysisType::Density, 'density'],
    'plot_deviation' => [AnalysisType::PlotDeviation, 'plot progression'],
]);

test('manuscript analyzer includes book title in instructions', function () {
    $book = Book::factory()->create(['title' => 'The Great Novel']);

    $agent = new ManuscriptAnalyzer($book, AnalysisType::Pacing);
    $instructions = $agent->instructions();

    expect((string) $instructions)->toContain('The Great Novel');
});

test('manuscript analyzer includes middleware', function () {
    $book = Book::factory()->withAi()->create();
    $agent = new ManuscriptAnalyzer($book, AnalysisType::Pacing);

    expect($agent->middleware())->toHaveCount(1);
});
