<?php

namespace App\Ai\Tools\Plot\Concerns;

/**
 * Accept a tool argument that may arrive as a JSON-encoded string (from an
 * LLM under OpenAI strict-mode schemas) or as a native array (from tests or
 * providers that accept richer schemas).
 */
trait DecodesJsonPayload
{
    /**
     * When the input is a non-empty string and `json_decode` fails (or yields
     * a non-array), the decoder returns `[]` for backwards compatibility but
     * also writes a human-readable description of the failure to the optional
     * out-parameter so the caller can surface a clear error to the LLM
     * instead of silently treating malformed JSON as "no input".
     *
     * @return array<int, mixed>
     */
    protected function decodeJsonPayload(mixed $raw, ?string &$error = null): array
    {
        $error = null;

        if (is_string($raw)) {
            $trimmed = trim($raw);

            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);

            if (! is_array($decoded)) {
                $message = json_last_error_msg();
                $error = $message !== '' && $message !== 'No error'
                    ? $message
                    : 'expected a JSON array';

                return [];
            }

            return $decoded;
        }

        return is_array($raw) ? $raw : [];
    }
}
