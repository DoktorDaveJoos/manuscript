<?php

use App\Ai\Agents\BookChatAgent;
use App\Ai\Contracts\BelongsToBook;
use App\Listeners\RecordAiTokenUsage;
use App\Models\Book;
use App\Models\License;
use App\Services\AiUsageService;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;

beforeEach(function () {
    License::factory()->create();
});

test('Book::recordAiUsage accumulates tokens and cost', function () {
    $book = Book::factory()->create();

    $book->recordAiUsage(100, 50, 500);
    $book->refresh();

    expect($book->ai_input_tokens)->toBe(100)
        ->and($book->ai_output_tokens)->toBe(50)
        ->and($book->ai_cost_microdollars)->toBe(500);

    $book->recordAiUsage(200, 100, 1000);
    $book->refresh();

    expect($book->ai_input_tokens)->toBe(300)
        ->and($book->ai_output_tokens)->toBe(150)
        ->and($book->ai_cost_microdollars)->toBe(1500);
});

test('Book::resetAiUsage zeros counters and sets timestamp', function () {
    $book = Book::factory()->create([
        'ai_input_tokens' => 5000,
        'ai_output_tokens' => 2000,
        'ai_cost_microdollars' => 30000,
    ]);

    $book->resetAiUsage();
    $book->refresh();

    expect($book->ai_input_tokens)->toBe(0)
        ->and($book->ai_output_tokens)->toBe(0)
        ->and($book->ai_cost_microdollars)->toBe(0)
        ->and($book->ai_usage_reset_at)->not->toBeNull();
});

test('Book::ai_cost_display formats microdollars correctly', function () {
    $book = Book::factory()->create(['ai_cost_microdollars' => 1_500_000]);

    expect($book->ai_cost_display)->toBe('$1.5000');

    $book->ai_cost_microdollars = 123;
    expect($book->ai_cost_display)->toBe('$0.0001');

    $book->ai_cost_microdollars = 0;
    expect($book->ai_cost_display)->toBe('$0.0000');
});

test('AiUsageService calculates cost for known models', function () {
    $service = new AiUsageService;

    $cost = $service->calculateCost(1000, 500, 'gpt-4o-mini');

    // gpt-4o-mini: input 150_000/1M, output 600_000/1M
    // input: 1000 * 150_000 / 1_000_000 = 150
    // output: 500 * 600_000 / 1_000_000 = 300
    expect($cost)->toBe(450);
});

test('AiUsageService resolves versioned model names to base pricing', function () {
    $service = new AiUsageService;

    // gpt-4o-2024-11-20 should resolve to gpt-4o pricing
    $cost = $service->calculateCost(1000, 500, 'gpt-4o-2024-11-20');

    // gpt-4o: input 2_500_000/1M, output 10_000_000/1M
    // input: 1000 * 2_500_000 / 1_000_000 = 2500
    // output: 500 * 10_000_000 / 1_000_000 = 5000
    expect($cost)->toBe(7500);
});

test('AiUsageService resolves compact date-suffixed model names', function () {
    $service = new AiUsageService;

    // gpt-4o-mini-20241022 should resolve to gpt-4o-mini pricing
    $cost = $service->calculateCost(1000, 500, 'gpt-4o-mini-20241022');

    // gpt-4o-mini: input 150_000/1M, output 600_000/1M
    expect($cost)->toBe(450);
});

test('AiUsageService resolves preview-suffixed model names', function () {
    $service = new AiUsageService;

    // gemini-2.5-flash-preview-04-17 is already in config (exact match)
    $cost = $service->calculateCost(1000, 500, 'gemini-2.5-flash-preview-04-17');
    expect($cost)->toBe(450);

    // gemini-2.5-pro-preview-06-01 should strip to gemini-2.5-pro which isn't in config,
    // so it falls back to default
    $cost = $service->calculateCost(1000, 500, 'gemini-2.5-pro-preview-06-01');
    expect($cost)->toBe(10500);
});

test('AiUsageService applies OpenAI cache read discount at 50%', function () {
    $service = new AiUsageService;

    $fullCost = $service->calculateCost(1000, 500, 'gpt-4o');
    $cachedCost = $service->calculateCost(1000, 500, 'gpt-4o', cacheReadTokens: 600, provider: 'openai');

    // Full cost: input 2500 + output 5000 = 7500
    // Cache discount: 600 * 2_500_000 / 1_000_000 * 0.50 = 750
    // Cached cost: 7500 - 750 = 6750
    expect($fullCost)->toBe(7500)
        ->and($cachedCost)->toBe(6750)
        ->and($cachedCost)->toBeLessThan($fullCost);
});

test('AiUsageService applies Anthropic cache read discount at 90%', function () {
    $service = new AiUsageService;

    $fullCost = $service->calculateCost(1000, 500, 'claude-sonnet-4-20250514');
    $cachedCost = $service->calculateCost(1000, 500, 'claude-sonnet-4-20250514', cacheReadTokens: 600, provider: 'anthropic');

    // Full cost: input 3000 + output 7500 = 10500
    // Cache discount: 600 * 3_000_000 / 1_000_000 * 0.90 = 1620
    // Cached cost: 10500 - 1620 = 8880
    expect($fullCost)->toBe(10500)
        ->and($cachedCost)->toBe(8880)
        ->and($cachedCost)->toBeLessThan($fullCost);
});

test('AiUsageService applies Anthropic cache write surcharge at 25%', function () {
    $service = new AiUsageService;

    $costWithWrite = $service->calculateCost(1000, 500, 'claude-sonnet-4-20250514', cacheWriteTokens: 400, provider: 'anthropic');

    // Base cost: input 3000 + output 7500 = 10500
    // Write surcharge: 400 * 3_000_000 / 1_000_000 * 0.25 = 300
    // Total: 10500 + 300 = 10800
    expect($costWithWrite)->toBe(10800);
});

test('AiUsageService does not apply cache write surcharge for OpenAI', function () {
    $service = new AiUsageService;

    $baseCost = $service->calculateCost(1000, 500, 'gpt-4o');
    $costWithWrite = $service->calculateCost(1000, 500, 'gpt-4o', cacheWriteTokens: 400, provider: 'openai');

    // OpenAI has no write surcharge — cost should be identical
    expect($costWithWrite)->toBe($baseCost);
});

test('AiUsageService falls back to default pricing for unknown models', function () {
    $service = new AiUsageService;

    $cost = $service->calculateCost(1000, 500, 'unknown-model-xyz');

    // default: input 3_000_000/1M, output 15_000_000/1M
    // input: 1000 * 3_000_000 / 1_000_000 = 3000
    // output: 500 * 15_000_000 / 1_000_000 = 7500
    expect($cost)->toBe(10500);
});

test('AiUsageService calculates embedding cost', function () {
    $service = new AiUsageService;

    $cost = $service->calculateEmbeddingCost(10000, 'text-embedding-3-small');

    // 20_000/1M => 10000 * 20_000 / 1_000_000 = 200
    expect($cost)->toBe(200);
});

test('reset-usage endpoint zeros counters', function () {
    $book = Book::factory()->withAi()->create([
        'ai_input_tokens' => 5000,
        'ai_output_tokens' => 2000,
        'ai_cost_microdollars' => 30000,
    ]);

    $this->postJson(route('books.ai.resetUsage', $book))
        ->assertOk()
        ->assertJsonPath('message', 'AI usage counters reset.');

    $book->refresh();

    expect($book->ai_input_tokens)->toBe(0)
        ->and($book->ai_output_tokens)->toBe(0)
        ->and($book->ai_cost_microdollars)->toBe(0);
});

test('event listener records usage when agent implements BelongsToBook', function () {
    $book = Book::factory()->create();
    $agent = new BookChatAgent($book);

    expect($agent)->toBeInstanceOf(BelongsToBook::class);

    $usage = new Usage(promptTokens: 500, completionTokens: 200);
    $meta = new Meta(model: 'gpt-4o-mini', provider: 'openai');
    $response = new AgentResponse('inv-1', 'Hello', $usage, $meta);

    $provider = Mockery::mock(TextProvider::class);
    $prompt = new AgentPrompt($agent, 'test', [], $provider, 'gpt-4o-mini');

    $event = new AgentPrompted('inv-1', $prompt, $response);

    $listener = app(RecordAiTokenUsage::class);
    $listener->handle($event);

    $book->refresh();

    expect($book->ai_input_tokens)->toBe(500)
        ->and($book->ai_output_tokens)->toBe(200)
        ->and($book->ai_cost_microdollars)->toBeGreaterThan(0);
});

test('event listener records lower cost when cache tokens are present', function () {
    $book = Book::factory()->create();
    $agent = new BookChatAgent($book);

    // First call: no cache
    $usage = new Usage(promptTokens: 1000, completionTokens: 500);
    $meta = new Meta(model: 'gpt-4o', provider: 'openai');
    $response = new AgentResponse('inv-1', 'Hello', $usage, $meta);
    $provider = Mockery::mock(TextProvider::class);
    $prompt = new AgentPrompt($agent, 'test', [], $provider, 'gpt-4o');
    $event = new AgentPrompted('inv-1', $prompt, $response);

    $listener = app(RecordAiTokenUsage::class);
    $listener->handle($event);
    $book->refresh();
    $fullCost = $book->ai_cost_microdollars;

    // Reset for second call
    $book->resetAiUsage();

    // Second call: same tokens but 800 are cache reads
    $cachedUsage = new Usage(promptTokens: 1000, completionTokens: 500, cacheReadInputTokens: 800);
    $cachedResponse = new AgentResponse('inv-2', 'Hello', $cachedUsage, $meta);
    $cachedPrompt = new AgentPrompt($agent, 'test', [], $provider, 'gpt-4o');
    $cachedEvent = new AgentPrompted('inv-2', $cachedPrompt, $cachedResponse);

    $listener->handle($cachedEvent);
    $book->refresh();
    $cachedCost = $book->ai_cost_microdollars;

    expect($cachedCost)->toBeLessThan($fullCost);
});
