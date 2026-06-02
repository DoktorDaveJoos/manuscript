<?php

namespace App\Ai\Tools\Plot\Concerns;

/**
 * Coerce an LLM-supplied `book_id` into a real int.
 *
 * Providers differ: some honour JSON schema strictness and send integers,
 * others serialize to strings. Accept both, reject anything else.
 */
trait CoercesBookId
{
    protected function coerceBookId(mixed $raw): ?int
    {
        if (is_int($raw)) {
            return $raw;
        }

        if (is_string($raw) && is_numeric($raw)) {
            return (int) $raw;
        }

        return null;
    }
}
