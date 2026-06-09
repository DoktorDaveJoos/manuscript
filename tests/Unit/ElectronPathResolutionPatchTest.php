<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * Guards the NativePHP electronPath() resolution patch.
 *
 * NativePHP's ElectronServiceProvider::electronPath() detected the published
 * electron project with file_exists("{$publishedProjectPath}/package.json"),
 * where $publishedProjectPath already includes the requested sub-path. For any
 * sub-path (e.g. node_modules/.../Info.plist) this checks for a package.json
 * *inside a file*, always fails, and silently falls back to the vendor
 * resources/electron directory. Once `composer install` re-extracts the package
 * and wipes vendor's resources/electron/node_modules, `native:run` crashes in
 * patchPlist(). We harden the fix via cweagans/composer-patches; these tests
 * fail loudly if the patch ever stops applying after a NativePHP bump.
 */
const ELECTRON_PATH_PATCH_PATH = 'patches/nativephp-desktop-electron-path-resolution.patch';

const NATIVEPHP_ELECTRON_PROVIDER = 'vendor/nativephp/desktop/src/Drivers/Electron/ElectronServiceProvider.php';

it('wires the electronPath patch so a failed apply fails the build', function (): void {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['require-dev'])->toHaveKey('cweagans/composer-patches')
        ->and($composer['config']['allow-plugins']['cweagans/composer-patches'] ?? null)->toBeTrue()
        ->and($composer['extra']['composer-exit-on-patch-failure'] ?? null)->toBeTrue()
        ->and($composer['extra']['patches']['nativephp/desktop'] ?? [])->toContain(ELECTRON_PATH_PATCH_PATH)
        ->and(base_path(ELECTRON_PATH_PATCH_PATH))->toBeReadableFile();
});

it('applies the patch to the installed NativePHP ElectronServiceProvider', function (): void {
    $provider = (string) file_get_contents(base_path(NATIVEPHP_ELECTRON_PROVIDER));

    // The fix: detect the published project by its root package.json, and never
    // by a package.json nested under the (sub-path-inclusive) target path.
    expect($provider)->toContain("file_exists(base_path('nativephp/electron/package.json'))")
        ->not->toContain('file_exists("{$publishedProjectPath}/package.json")');
});
