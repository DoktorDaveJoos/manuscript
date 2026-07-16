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

it('maps actionable HTTP failures to dedicated editorial codes', function () {
    $psrResponse = new Response(404, [], json_encode(['error' => ['code' => 'model_not_found', 'message' => 'The model does not exist']]));
    $exception = new RequestException(new HttpResponse($psrResponse));

    expect(EditorialReviewErrorCode::fromThrowable($exception))
        ->toBe(EditorialReviewErrorCode::ModelUnavailable);

    expect(EditorialReviewErrorCode::fromThrowable(new RuntimeException('boom')))
        ->toBe(EditorialReviewErrorCode::Unknown);
});

it('halts the run for every AI failure that would make required review work incomplete', function () {
    expect(EditorialReviewErrorCode::RateLimited->shouldHaltRun())->toBeTrue()
        ->and(EditorialReviewErrorCode::Overloaded->shouldHaltRun())->toBeTrue()
        ->and(EditorialReviewErrorCode::InsufficientCredits->shouldHaltRun())->toBeTrue()
        ->and(EditorialReviewErrorCode::InvalidKey->shouldHaltRun())->toBeTrue()
        ->and(EditorialReviewErrorCode::ModelUnavailable->shouldHaltRun())->toBeTrue()
        ->and(EditorialReviewErrorCode::ContextTooLong->shouldHaltRun())->toBeTrue()
        ->and(EditorialReviewErrorCode::BadRequest->shouldHaltRun())->toBeTrue()
        ->and(EditorialReviewErrorCode::ConnectionFailed->shouldHaltRun())->toBeTrue()
        ->and(EditorialReviewErrorCode::Timeout->shouldHaltRun())->toBeTrue()
        ->and(EditorialReviewErrorCode::NoProvider->shouldHaltRun())->toBeFalse()
        ->and(EditorialReviewErrorCode::NoContent->shouldHaltRun())->toBeFalse()
        ->and(EditorialReviewErrorCode::AppUnavailable->shouldHaltRun())->toBeFalse()
        ->and(EditorialReviewErrorCode::QueueUnavailable->shouldHaltRun())->toBeFalse()
        ->and(EditorialReviewErrorCode::Unknown->shouldHaltRun())->toBeFalse();
});
