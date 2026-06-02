<?php

namespace App\Ai\Support;

use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

/**
 * Classifies anything thrown by the AI SDK or its underlying HTTP client
 * into a discrete `kind` the frontend can render with a consistent toast.
 *
 * Three classification layers, in order:
 *  1. The SDK's own typed exceptions (RateLimitedException etc.) — these
 *     already encode the failure mode.
 *  2. Provider HTTP responses (status code + body sniffing) — for errors
 *     the SDK passes through verbatim (auth failures, model-not-found,
 *     context-too-long, etc.).
 *  3. Anything else — `unknown`, with the original message preserved.
 *
 * `kind` is the contract with the frontend; do not rename without
 * updating both the i18n strings (resources/js/i18n/{en,de,es}/ai.json)
 * and the toast handler in the chat surface.
 */
final class AiErrorClassifier
{
    public const KIND_INVALID_KEY = 'invalid_key';

    public const KIND_INSUFFICIENT_CREDITS = 'insufficient_credits';

    public const KIND_RATE_LIMITED = 'rate_limited';

    public const KIND_OVERLOADED = 'overloaded';

    public const KIND_MODEL_UNAVAILABLE = 'model_unavailable';

    public const KIND_CONTEXT_TOO_LONG = 'context_too_long';

    public const KIND_BAD_REQUEST = 'bad_request';

    public const KIND_TIMEOUT = 'timeout';

    public const KIND_UNKNOWN = 'unknown';

    /**
     * @return array{kind: string, message: string, status_code: int, provider: ?string}
     */
    public static function classify(Throwable $e, ?string $provider = null): array
    {
        $kind = self::detectKind($e);
        $statusCode = self::extractStatus($e);
        $serverMessage = self::extractProviderMessage($e);

        return [
            'kind' => $kind,
            'message' => $serverMessage ?? trim($e->getMessage()) ?: 'AI request failed.',
            'status_code' => $statusCode,
            'provider' => $provider,
        ];
    }

    private static function detectKind(Throwable $e): string
    {
        if ($e instanceof RateLimitedException) {
            return self::KIND_RATE_LIMITED;
        }

        if ($e instanceof ProviderOverloadedException) {
            return self::KIND_OVERLOADED;
        }

        if ($e instanceof InsufficientCreditsException) {
            return self::KIND_INSUFFICIENT_CREDITS;
        }

        if ($e instanceof RequestException) {
            return self::detectKindFromHttpResponse($e);
        }

        // CURLE_OPERATION_TIMEDOUT (28) is what Guzzle exposes; PHP also
        // surfaces the word "timed out" in fopen errors etc.
        $message = strtolower($e->getMessage());
        if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return self::KIND_TIMEOUT;
        }

        return self::KIND_UNKNOWN;
    }

    private static function detectKindFromHttpResponse(RequestException $e): string
    {
        $status = $e->response?->status() ?? 0;
        $body = $e->response?->json() ?? [];
        // Most providers nest the actionable error under `error.message`;
        // a few (older OpenAI variants, custom proxies) put it at the top
        // level. Coalesce.
        $rawMessage = strtolower((string) ($body['error']['message'] ?? $body['message'] ?? ''));
        $rawCode = strtolower((string) ($body['error']['code'] ?? $body['error']['type'] ?? ''));

        if ($status === 401 || $status === 403) {
            return self::KIND_INVALID_KEY;
        }

        if ($status === 429) {
            return self::KIND_RATE_LIMITED;
        }

        if (in_array($status, [502, 503, 529], true)) {
            return self::KIND_OVERLOADED;
        }

        if ($status === 400 || $status === 404) {
            // Order matters: context-length errors mention "model" too
            // ("this model's max context length…"), so they have to be
            // tested before the model-availability heuristic.
            if (self::matchesAny($rawCode, ['context_length_exceeded'])
                || self::matchesAny($rawMessage, ['context length', 'context_length', 'maximum context', 'too many tokens', 'token limit'])) {
                return self::KIND_CONTEXT_TOO_LONG;
            }

            if (self::matchesAny($rawCode, ['model_not_found', 'invalid_model'])
                || (str_contains($rawMessage, 'model')
                    && self::matchesAny($rawMessage, ['does not exist', 'not found', 'no access']))) {
                return self::KIND_MODEL_UNAVAILABLE;
            }

            if (self::matchesAny($rawMessage, ['insufficient', 'quota', 'credit', 'billing'])) {
                return self::KIND_INSUFFICIENT_CREDITS;
            }

            return self::KIND_BAD_REQUEST;
        }

        return self::KIND_UNKNOWN;
    }

    private static function extractStatus(Throwable $e): int
    {
        if ($e instanceof RequestException && $e->response !== null) {
            return $e->response->status();
        }

        if ($e instanceof RateLimitedException) {
            return 429;
        }

        if ($e instanceof ProviderOverloadedException) {
            return 503;
        }

        if ($e instanceof InsufficientCreditsException) {
            return 402;
        }

        return 500;
    }

    private static function extractProviderMessage(Throwable $e): ?string
    {
        if ($e instanceof RequestException && $e->response !== null) {
            $body = $e->response->json() ?? [];
            $msg = trim((string) ($body['error']['message'] ?? $body['message'] ?? ''));

            if ($msg !== '') {
                return $msg;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $needles
     */
    private static function matchesAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
