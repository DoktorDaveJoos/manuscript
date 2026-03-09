<?php

use App\Ai\Agents\ChapterAnalyzer;
use App\Models\Book;

test('chapter analyzer returns structured chapter analysis', function () {
    ChapterAnalyzer::fake();

    $book = Book::factory()->withAi()->create();

    $agent = new ChapterAnalyzer($book);
    $response = $agent->prompt('Analyze this chapter: John walked into the dark room.');

    expect($response['summary'])->toBeString()
        ->and($response['key_events'])->toBeArray()
        ->and($response['characters_present'])->toBeArray()
        ->and($response['tension_score'])->toBeInt()
        ->and($response['micro_tension_score'])->toBeInt()
        ->and($response['scene_purpose'])->toBeString()
        ->and($response['emotional_state_open'])->toBeString()
        ->and($response['emotional_state_close'])->toBeString()
        ->and($response['emotional_shift_magnitude'])->toBeInt()
        ->and($response['hook_score'])->toBeInt()
        ->and($response['hook_type'])->toBeString()
        ->and($response['hook_reasoning'])->toBeString()
        ->and($response['entry_hook_score'])->toBeInt()
        ->and($response['pacing_feel'])->toBeString()
        ->and($response['sensory_grounding'])->toBeInt()
        ->and($response['information_delivery'])->toBeString()
        ->and($response['plot_points'])->toBeArray();

    ChapterAnalyzer::assertPrompted(fn ($prompt) => true);
});

test('chapter analyzer includes preceding context in instructions', function () {
    $book = Book::factory()->create();

    $context = 'Ch1: John arrived at the castle.';
    $agent = new ChapterAnalyzer($book, $context);
    $instructions = $agent->instructions();

    expect((string) $instructions)->toContain('John arrived at the castle');
});

test('chapter analyzer includes book title in instructions', function () {
    $book = Book::factory()->create(['title' => 'The Dark Tower']);

    $agent = new ChapterAnalyzer($book);
    $instructions = $agent->instructions();

    expect((string) $instructions)->toContain('The Dark Tower');
});

test('chapter analyzer instructions omit context section when empty', function () {
    $book = Book::factory()->create();

    $agent = new ChapterAnalyzer($book);
    $instructions = $agent->instructions();

    expect((string) $instructions)->not->toContain('Context from preceding chapters');
});

test('chapter analyzer includes middleware', function () {
    $book = Book::factory()->withAi()->create();
    $agent = new ChapterAnalyzer($book);

    expect($agent->middleware())->toHaveCount(1);
});
