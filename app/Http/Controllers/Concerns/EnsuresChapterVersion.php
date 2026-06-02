<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Chapter;
use RuntimeException;

trait EnsuresChapterVersion
{
    /**
     * Refuse to mutate a chapter when the caller declares an expected current
     * version that no longer matches reality. Skipping the check when no
     * expectation is provided keeps legacy/server-internal callers working;
     * the realistic corruption case (stale tab, parallel AI flow) always
     * comes with a specific version the client believes is current.
     *
     * Mismatches are reported to Sentry via `report()` and surface as a 409.
     */
    protected function ensureCurrentVersion(Chapter $chapter, ?int $expectedVersionId): void
    {
        if ($expectedVersionId === null) {
            return;
        }

        $currentId = $chapter->currentVersion?->id;

        if ($currentId === $expectedVersionId) {
            return;
        }

        report(new RuntimeException(sprintf(
            'Stale chapter version write attempt: chapter=%d expected=%d current=%s',
            $chapter->id,
            $expectedVersionId,
            $currentId === null ? 'null' : (string) $currentId,
        )));

        abort(409, __('This chapter has changed since you started — reload to continue.'));
    }
}
