<?php

namespace App\Jobs\Concerns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

trait DetectsTransientErrors
{
    protected function isTransient(Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        if ($e instanceof RequestException) {
            $status = $e->response->status();

            return in_array($status, [408, 429, 500, 502, 503, 504]);
        }

        // cURL transient errors that surface as RuntimeException
        if (preg_match('/cURL error (7|28|35|52|56)\b/', $e->getMessage())) {
            return true;
        }

        return false;
    }
}
