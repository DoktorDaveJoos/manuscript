<?php

use App\Ai\Agents\ChapterAnalyzer;
use App\Models\Book;
use Laravel\Ai\Enums\Lab;

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
        ->and($response['information_delivery'])->toBeString();

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

test('chapter analyzer caches the static rubric but not the rolling context for Anthropic', function () {
    $book = Book::factory()->create(['title' => 'The Dark Tower']);

    $agent = new ChapterAnalyzer($book, 'Ch1: John arrived at the castle.');
    $blocks = $agent->providerOptions(Lab::Anthropic)['system'];

    // Persona, book context, and the scoring rubric form the cached prefix.
    expect($blocks[0]['cache_control'])->toBe(['type' => 'ephemeral'])
        ->and($blocks[0]['text'])->toContain('The Dark Tower')
        ->and($blocks[0]['text'])->toContain('tension_score')
        ->and($blocks[0]['text'])->not->toContain('John arrived at the castle');

    // The per-chapter rolling context is the trailing, uncached block.
    $last = $blocks[count($blocks) - 1];
    expect($last)->not->toHaveKey('cache_control')
        ->and($last['text'])->toContain('John arrived at the castle');
});

test('chapter analyzer caches the whole prompt when there is no rolling context', function () {
    $book = Book::factory()->create();

    $blocks = (new ChapterAnalyzer($book))->providerOptions(Lab::Anthropic)['system'];

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['cache_control'])->toBe(['type' => 'ephemeral']);
});

test('chapter analyzer includes middleware', function () {
    $book = Book::factory()->withAi()->create();
    $agent = new ChapterAnalyzer($book);

    expect($agent->middleware())->toHaveCount(1);
});
