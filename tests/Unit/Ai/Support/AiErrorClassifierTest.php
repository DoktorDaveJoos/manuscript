<?php

use App\Ai\Support\AiErrorClassifier;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as HttpResponse;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;

/**
 * Build a fake Illuminate RequestException with a chosen status + JSON body
 * so we can exercise the classifier without hitting a real provider.
 */
function fakeRequestException(int $status, array $body): RequestException
{
    $psrResponse = new Response($status, [], json_encode($body));
    $response = new HttpResponse($psrResponse);

    return new RequestException($response);
}

test('classifies typed SDK exceptions to their kinds', function () {
    expect(AiErrorClassifier::classify(RateLimitedException::forProvider('openai'))['kind'])
        ->toBe(AiErrorClassifier::KIND_RATE_LIMITED);

    expect(AiErrorClassifier::classify(ProviderOverloadedException::forProvider('anthropic'))['kind'])
        ->toBe(AiErrorClassifier::KIND_OVERLOADED);

    expect(AiErrorClassifier::classify(InsufficientCreditsException::forProvider('openai'))['kind'])
        ->toBe(AiErrorClassifier::KIND_INSUFFICIENT_CREDITS);
});

test('classifies 401/403 as invalid_key', function () {
    $e = fakeRequestException(401, ['error' => ['message' => 'Invalid API key']]);

    $result = AiErrorClassifier::classify($e, provider: 'openai');

    expect($result['kind'])->toBe(AiErrorClassifier::KIND_INVALID_KEY);
    expect($result['status_code'])->toBe(401);
    expect($result['provider'])->toBe('openai');
    expect($result['message'])->toBe('Invalid API key');
});

test('classifies 400 with model-not-found message as model_unavailable', function () {
    $e = fakeRequestException(400, [
        'error' => ['message' => 'The model `gpt-5.4-pro` does not exist or you do not have access to it.'],
    ]);

    expect(AiErrorClassifier::classify($e)['kind'])->toBe(AiErrorClassifier::KIND_MODEL_UNAVAILABLE);
});

test('classifies 400 with model_not_found code as model_unavailable', function () {
    $e = fakeRequestException(400, [
        'error' => ['code' => 'model_not_found', 'message' => 'unknown model'],
    ]);

    expect(AiErrorClassifier::classify($e)['kind'])->toBe(AiErrorClassifier::KIND_MODEL_UNAVAILABLE);
});

test('classifies 400 with context-length errors as context_too_long', function () {
    $e = fakeRequestException(400, [
        'error' => ['message' => "This model's maximum context length is 200000 tokens."],
    ]);

    expect(AiErrorClassifier::classify($e)['kind'])->toBe(AiErrorClassifier::KIND_CONTEXT_TOO_LONG);
});

test('classifies billing/quota messages as insufficient_credits', function () {
    $e = fakeRequestException(400, [
        'error' => ['message' => 'You exceeded your current quota, please check your plan and billing details.'],
    ]);

    expect(AiErrorClassifier::classify($e)['kind'])->toBe(AiErrorClassifier::KIND_INSUFFICIENT_CREDITS);
});

test('classifies 429 as rate_limited', function () {
    $e = fakeRequestException(429, ['error' => ['message' => 'Too many requests']]);

    expect(AiErrorClassifier::classify($e)['kind'])->toBe(AiErrorClassifier::KIND_RATE_LIMITED);
});

test('classifies 503/529 as overloaded', function () {
    expect(AiErrorClassifier::classify(fakeRequestException(503, ['error' => ['message' => 'overloaded']]))['kind'])
        ->toBe(AiErrorClassifier::KIND_OVERLOADED);

    expect(AiErrorClassifier::classify(fakeRequestException(529, ['error' => ['message' => 'overloaded']]))['kind'])
        ->toBe(AiErrorClassifier::KIND_OVERLOADED);
});

test('falls back to bad_request for unrecognized 4xx messages', function () {
    $e = fakeRequestException(400, ['error' => ['message' => 'Some unfamiliar error']]);

    expect(AiErrorClassifier::classify($e)['kind'])->toBe(AiErrorClassifier::KIND_BAD_REQUEST);
});

test('classifies timeout errors by message', function () {
    $e = new RuntimeException('cURL error 28: Operation timed out after 60000 milliseconds');

    expect(AiErrorClassifier::classify($e)['kind'])->toBe(AiErrorClassifier::KIND_TIMEOUT);
});

test('falls back to unknown for unclassifiable exceptions', function () {
    $e = new RuntimeException('Something weird happened');

    $result = AiErrorClassifier::classify($e);

    expect($result['kind'])->toBe(AiErrorClassifier::KIND_UNKNOWN);
    expect($result['message'])->toBe('Something weird happened');
});

test('preserves provider message verbatim when present', function () {
    $e = fakeRequestException(400, [
        'error' => ['message' => 'You tried something specific. Here is the detail.'],
    ]);

    expect(AiErrorClassifier::classify($e)['message'])
        ->toBe('You tried something specific. Here is the detail.');
});
