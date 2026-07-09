<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Chapter;
use RuntimeException;

trait EnsuresChapterVersion
{
    /**
     * Refuse to mutate a chapter when the caller's idea of the current
     * version no longer matches reality. A null expectation means the caller
     * believes no current version exists — that too must match, so a stale
     * tab or parallel AI flow can never silently overwrite newer work.
     * Callers are responsible for validating the field is present on the
     * request (`present`, `nullable`, `integer`).
     *
     * Mismatches are reported to Sentry via `report()` and surface as a 409.
     */
    protected function ensureCurrentVersion(Chapter $chapter, ?int $expectedVersionId): void
    {
        $currentId = $chapter->currentVersion?->id;

        if ($currentId === $expectedVersionId) {
            return;
        }

        report(new RuntimeException(sprintf(
            'Stale chapter version write attempt: chapter=%d expected=%s current=%s',
            $chapter->id,
            $expectedVersionId === null ? 'null' : (string) $expectedVersionId,
            $currentId === null ? 'null' : (string) $currentId,
        )));

        abort(409, __('This chapter has changed since you started — reload to continue.'));
    }
}
