<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * Guards the auto-update "no silent install-on-quit" patch.
 *
 * electron-updater defaults autoInstallOnAppQuit=true: a downloaded update is
 * staged as a Squirrel install that runs when the app quits, WITHOUT relaunching
 * (launchAfterInstallation=false). If the app is reopened during that install
 * window, the running instance blocks ShipIt ("App Still Running Error"), launchd
 * respawns ShipIt, and it loops forever burning CPU while the update never
 * applies. We disable it so updates apply only via the explicit Install action
 * (quitAndInstall), which relaunches (autoRunAppAfterInstall=true).
 *
 * Like the other NativePHP patches this targets the COMPILED dist/: the publish
 * build runs `electron-vite build`, never tsc, so src/*.ts is never recompiled —
 * only dist/ is loaded at runtime. The fix is re-applied on every CI run via
 * scripts/nativephp-patches/apply.sh (a whole-file copy). These tests fail loudly
 * if the behaviour is lost, stops being wired, or a NativePHP bump moves the file.
 */
const UPDATER_PATCH_FILE = 'scripts/nativephp-patches/files/resources/electron/electron-plugin/dist/server/api/autoUpdater.js';

const UPDATER_DIST_FILE = 'vendor/nativephp/desktop/resources/electron/electron-plugin/dist/server/api/autoUpdater.js';

it('disables autoInstallOnAppQuit in the shipped dist patch file', function (): void {
    $patch = (string) file_get_contents(base_path(UPDATER_PATCH_FILE));

    expect($patch)->toContain('autoUpdater.autoInstallOnAppQuit = false');
});

it('wires the updater patch through apply.sh', function (): void {
    $apply = (string) file_get_contents(base_path('scripts/nativephp-patches/apply.sh'));

    expect($apply)->toContain('resources/electron/electron-plugin/dist/server/api/autoUpdater.js');
});

it('targets a compiled dist file that still exists in the installed NativePHP package', function (): void {
    // If a NativePHP bump renames/restructures this, apply.sh fails loudly with
    // "vendor file missing" at publish time — guard the same path here.
    expect(base_path(UPDATER_DIST_FILE))->toBeReadableFile();
});

it('still routes the explicit install action through the relaunching quitAndInstall path', function (): void {
    // Disabling install-on-quit must NOT disable updates: the Install button
    // (/quit-and-install) must still call quitAndInstall(), which on macOS
    // relaunches the app after install (autoRunAppAfterInstall defaults true).
    $patch = (string) file_get_contents(base_path(UPDATER_PATCH_FILE));

    expect($patch)
        ->toContain('/quit-and-install')
        ->toContain('autoUpdater.quitAndInstall()');
});
