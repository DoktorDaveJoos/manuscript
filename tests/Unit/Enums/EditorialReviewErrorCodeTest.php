<?php

use App\Enums\EditorialReviewErrorCode;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as HttpResponse;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;

it('maps typed SDK exceptions to their codes via the classifier', function () {
    expect(EditorialReviewErrorCode::fromThrowable(RateLimitedException::forProvider('openai')))
        ->toBe(EditorialReviewErrorCode::RateLimited);

    expect(EditorialReviewErrorCode::fromThrowable(ProviderOverloadedException::forProvider('anthropic')))
        ->toBe(EditorialReviewErrorCode::Overloaded);

    expect(EditorialReviewErrorCode::fromThrowable(InsufficientCreditsException::forProvider('openai')))
        ->toBe(EditorialReviewErrorCode::InsufficientCredits);
});

it('maps HTTP auth failures to invalid_key', function () {
    $psrResponse = new Response(401, [], json_encode(['error' => ['message' => 'Incorrect API key provided']]));
    $exception = new RequestException(new HttpResponse($psrResponse));

    expect(EditorialReviewErrorCode::fromThrowable($exception))
        ->toBe(EditorialReviewErrorCode::InvalidKey);
});

it('falls back to unknown for classifier kinds without an editorial case', function () {
    $psrResponse = new Response(404, [], json_encode(['error' => ['code' => 'model_not_found', 'message' => 'The model does not exist']]));
    $exception = new RequestException(new HttpResponse($psrResponse));

    expect(EditorialReviewErrorCode::fromThrowable($exception))
        ->toBe(EditorialReviewErrorCode::Unknown);

    expect(EditorialReviewErrorCode::fromThrowable(new RuntimeException('boom')))
        ->toBe(EditorialReviewErrorCode::Unknown);
});

it('halts the run only for provider availability and key problems', function () {
    expect(EditorialReviewErrorCode::RateLimited->shouldHaltRun())->toBeTrue()
        ->and(EditorialReviewErrorCode::Overloaded->shouldHaltRun())->toBeTrue()
        ->and(EditorialReviewErrorCode::InsufficientCredits->shouldHaltRun())->toBeTrue()
        ->and(EditorialReviewErrorCode::InvalidKey->shouldHaltRun())->toBeTrue()
        ->and(EditorialReviewErrorCode::NoProvider->shouldHaltRun())->toBeFalse()
        ->and(EditorialReviewErrorCode::NoContent->shouldHaltRun())->toBeFalse()
        ->and(EditorialReviewErrorCode::Timeout->shouldHaltRun())->toBeFalse()
        ->and(EditorialReviewErrorCode::Unknown->shouldHaltRun())->toBeFalse();
});
