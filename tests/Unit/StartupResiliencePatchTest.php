<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * Guards the NativePHP startup-resilience patch (docs/plans/startup-port-resilience.md).
 *
 * The Electron startup code lives in NativePHP's package — regenerated on
 * `native:publish` and overwritten by `composer install` — so we harden it via
 * cweagans/composer-patches. The failure mode worth guarding is the patch
 * silently no longer applying after a NativePHP bump; these tests fail loudly
 * when that happens, in CI and locally.
 */
const STARTUP_PATCH_PATH = 'patches/nativephp-desktop-startup-resilience.patch';

const NATIVEPHP_ELECTRON_SRC = 'vendor/nativephp/desktop/resources/electron/electron-plugin/src';

it('wires the startup-resilience patch so a failed apply fails the build', function (): void {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['require-dev'])->toHaveKey('cweagans/composer-patches')
        ->and($composer['config']['allow-plugins']['cweagans/composer-patches'] ?? null)->toBeTrue()
        ->and($composer['extra']['composer-exit-on-patch-failure'] ?? null)->toBeTrue()
        ->and($composer['extra']['patches']['nativephp/desktop'] ?? [])->toContain(STARTUP_PATCH_PATH)
        ->and(base_path(STARTUP_PATCH_PATH))->toBeReadableFile();
});

it('applies the patch to the installed NativePHP Electron source', function (): void {
    $index = (string) file_get_contents(base_path(NATIVEPHP_ELECTRON_SRC.'/index.ts'));
    $api = (string) file_get_contents(base_path(NATIVEPHP_ELECTRON_SRC.'/server/api.ts'));

    // The behaviours that define the fix: a single-instance lock + a visible
    // startup-failure dialog (index.ts), and an API port-bind retry (api.ts).
    expect($index)->toContain('requestSingleInstanceLock')->toContain('showErrorBox')
        ->and($api)->toContain('EADDRINUSE');
});
