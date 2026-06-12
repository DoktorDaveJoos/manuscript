<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * Guards the microphone entitlement patch.
 *
 * Speech input records audio in the renderer via getUserMedia. Under macOS
 * hardened runtime (required for notarization) that needs the
 * com.apple.security.device.audio-input entitlement at signing time.
 * NativePHP's stock entitlements.mac.plist doesn't include it, so we re-apply
 * it to vendor on every CI run via scripts/nativephp-patches/apply.sh (a
 * whole-file copy). These tests fail loudly if the entitlement is lost or
 * stops being wired after a NativePHP bump. The matching
 * NSMicrophoneUsageDescription already ships in NativePHP's electron-builder
 * config, so only the entitlement needs patching.
 */
const ENTITLEMENTS_PATCH_FILE = 'scripts/nativephp-patches/files/resources/electron/build/entitlements.mac.plist';

const NATIVEPHP_ENTITLEMENTS_FILE = 'vendor/nativephp/desktop/resources/electron/build/entitlements.mac.plist';

it('keeps the microphone entitlement in the patch file', function (): void {
    $plist = (string) file_get_contents(base_path(ENTITLEMENTS_PATCH_FILE));

    expect($plist)->toContain('<key>com.apple.security.device.audio-input</key>');
});

it('preserves the stock entitlements alongside the microphone one', function (): void {
    $plist = (string) file_get_contents(base_path(ENTITLEMENTS_PATCH_FILE));

    expect($plist)->toContain('<key>com.apple.security.cs.allow-jit</key>')
        ->toContain('<key>com.apple.security.cs.allow-unsigned-executable-memory</key>')
        ->toContain('<key>com.apple.security.cs.allow-dyld-environment-variables</key>');
});

it('wires the entitlements patch through apply.sh', function (): void {
    $apply = (string) file_get_contents(base_path('scripts/nativephp-patches/apply.sh'));

    expect($apply)->toContain('resources/electron/build/entitlements.mac.plist');
});

it('targets an entitlements file that still exists in the installed NativePHP package', function (): void {
    expect(base_path(NATIVEPHP_ENTITLEMENTS_FILE))->toBeReadableFile();
});
