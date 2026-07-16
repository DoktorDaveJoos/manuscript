<?php

namespace App\Enums;

use App\Ai\Support\AiErrorClassifier;
use Throwable;

/**
 * Machine-readable reason a review failed, translated to plain-language copy
 * by the frontend (resources/js/i18n/{en,de,es}/editorial-review.json,
 * `failed.reasons.*`). Provider-level cases mirror AiErrorClassifier kinds;
 * NoProvider/NoContent are review-level conditions the classifier never sees.
 */
enum EditorialReviewErrorCode: string
{
    case RateLimited = 'rate_limited';
    case Overloaded = 'overloaded';
    case InsufficientCredits = 'insufficient_credits';
    case InvalidKey = 'invalid_key';
    case ModelUnavailable = 'model_unavailable';
    case ContextTooLong = 'context_too_long';
    case BadRequest = 'bad_request';
    case ConnectionFailed = 'connection_failed';
    case NoProvider = 'no_provider';
    case NoContent = 'no_content';
    case AppUnavailable = 'app_unavailable';
    case QueueUnavailable = 'queue_unavailable';
    case Timeout = 'timeout';
    case Unknown = 'unknown';

    /**
     * Classify a throwable via AiErrorClassifier; kinds without a matching
     * case fall back to Unknown, where the UI shows a safe generic message.
     */
    public static function fromThrowable(Throwable $exception): self
    {
        return self::tryFrom(AiErrorClassifier::classify($exception)['kind']) ?? self::Unknown;
    }

    /**
     * Whether the failure should halt the whole run for a later resume:
     * either a temporary provider condition (rate limit, overload, out of
     * credits) or a configuration problem (invalid key) that would fail
     * every remaining call anyway.
     */
    public function shouldHaltRun(): bool
    {
        return in_array($this, [
            self::RateLimited,
            self::Overloaded,
            self::InsufficientCredits,
            self::InvalidKey,
            self::ModelUnavailable,
            self::ContextTooLong,
            self::BadRequest,
            self::ConnectionFailed,
            self::Timeout,
        ], true);
    }
}
