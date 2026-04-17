<?php

namespace App\Jobs\Concerns;

use Throwable;

/**
 * Forwards job-level failures to Sentry without altering queue retry semantics.
 *
 * Use in jobs that should always surface errors to Sentry even when they
 * bubble up uncaught. The `failed()` hook fires once after all retries are
 * exhausted; use it to mirror the final exception to telemetry.
 */
trait ReportsJobFailures
{
    public function failed(?Throwable $exception): void
    {
        if ($exception) {
            report($exception);
        }
    }
}
