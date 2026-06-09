<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * Guards the NativePHP startup-resilience patch (docs/plans/startup-port-resilience.md).
 *
 * The Electron startup code lives in NativePHP's package and is overwritten by
 * every `composer install`, so we re-apply our version on each CI run via
 * scripts/nativephp-patches/apply.sh (a whole-file copy — diff-based patching was
 * line-ending-fragile and broke the Windows build). Crucially the patch targets
 * the COMPILED dist/: the publish build runs `electron-vite build`, never tsc, so
 * the package's src/*.ts is never recompiled — only dist/ is loaded at runtime.
 *
 * These tests fail loudly if the patched behaviour is lost or stops being wired,
 * or if a NativePHP bump moves the files we copy onto.
 */
const STARTUP_PATCH_FILES = 'scripts/nativephp-patches/files/resources/electron/electron-plugin';

const NATIVEPHP_ELECTRON_DIST = 'vendor/nativephp/desktop/resources/electron/electron-plugin/dist';

it('keeps the startup-resilience behaviours in the shipped dist patch files', function (): void {
    $index = (string) file_get_contents(base_path(STARTUP_PATCH_FILES.'/dist/index.js'));
    $api = (string) file_get_contents(base_path(STARTUP_PATCH_FILES.'/dist/server/api.js'));

    // The behaviours that define the fix: a single-instance lock + a visible
    // startup-failure dialog (index.js), and an API port-bind retry (api.js).
    expect($index)->toContain('requestSingleInstanceLock')->toContain('showErrorBox')
        ->and($api)->toContain('EADDRINUSE');
});

it('wires both dist patch files through apply.sh', function (): void {
    $apply = (string) file_get_contents(base_path('scripts/nativephp-patches/apply.sh'));

    expect($apply)
        ->toContain('resources/electron/electron-plugin/dist/index.js')
        ->toContain('resources/electron/electron-plugin/dist/server/api.js');
});

it('targets compiled dist files that still exist in the installed NativePHP package', function (): void {
    // If a NativePHP bump renames/restructures these, apply.sh fails loudly with
    // "vendor file missing" at publish time — guard the same paths here.
    expect(base_path(NATIVEPHP_ELECTRON_DIST.'/index.js'))->toBeReadableFile()
        ->and(base_path(NATIVEPHP_ELECTRON_DIST.'/server/api.js'))->toBeReadableFile();
});

it('no longer applies these via the line-ending-fragile composer-patches mechanism', function (): void {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    // extra.patches on Windows runs `git apply` under core.autocrlf=true, which
    // silently fails to apply and kills the build. We must not regress to it.
    expect($composer['extra'] ?? [])->not->toHaveKey('patches')
        ->and($composer['extra'] ?? [])->not->toHaveKey('composer-exit-on-patch-failure');
});
