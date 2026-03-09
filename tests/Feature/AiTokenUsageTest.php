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
    $meta = new Meta(model: 'gpt-4o-mini');
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
