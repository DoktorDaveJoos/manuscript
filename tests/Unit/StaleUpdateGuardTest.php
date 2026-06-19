<?php

declare(strict_types=1);

use App\Services\StaleUpdateGuard;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The safety-critical part of the boot-time updater heal is the decision of
 * WHETHER to intervene. It must heal a genuinely stranded install (downloaded
 * but not applied while we run) but never abort a legitimate in-flight or
 * just-completed install — so when the staged version equals the running version
 * (the relaunched app IS the staged build), or the staged version is unknown, it
 * must refuse to heal.
 */
it('only heals when the staged version differs from the running one', function (
    ?string $stagedVersion,
    string $runningVersion,
    bool $expected,
): void {
    $guard = new StaleUpdateGuard;

    expect($guard->shouldHeal($stagedVersion, $runningVersion))->toBe($expected);
})->with([
    'staged version unreadable (null)' => [null, '0.7.3', false],
    'staged version unreadable (empty)' => ['', '0.7.3', false],
    'staged matches running — post-install / in-flight, do not abort' => ['0.7.4', '0.7.4', false],
    'staged differs from running — stranded, heal it' => ['0.7.4', '0.7.3', true],
    'staged older than running (already past it) — still a stuck job, heal it' => ['0.7.2', '0.7.3', true],
]);
