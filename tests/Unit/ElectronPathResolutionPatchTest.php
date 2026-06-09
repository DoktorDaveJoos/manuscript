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
 * patchPlist(). We re-apply the fix via scripts/nativephp-patches/apply.sh (a
 * whole-file copy); these tests fail loudly if the fix is lost or stops being
 * wired after a NativePHP bump.
 */
const ELECTRON_PATH_PATCH_FILE = 'scripts/nativephp-patches/files/src/Drivers/Electron/ElectronServiceProvider.php';

const NATIVEPHP_ELECTRON_PROVIDER = 'vendor/nativephp/desktop/src/Drivers/Electron/ElectronServiceProvider.php';

it('keeps the electronPath fix in the patch file', function (): void {
    $provider = (string) file_get_contents(base_path(ELECTRON_PATH_PATCH_FILE));

    // The fix: detect the published project by its root package.json, and never
    // by a package.json nested under the (sub-path-inclusive) target path.
    expect($provider)->toContain("file_exists(base_path('nativephp/electron/package.json'))")
        ->not->toContain('file_exists("{$publishedProjectPath}/package.json")');
});

it('wires the provider patch through apply.sh', function (): void {
    $apply = (string) file_get_contents(base_path('scripts/nativephp-patches/apply.sh'));

    expect($apply)->toContain('src/Drivers/Electron/ElectronServiceProvider.php');
});

it('targets a provider file that still exists in the installed NativePHP package', function (): void {
    expect(base_path(NATIVEPHP_ELECTRON_PROVIDER))->toBeReadableFile();
});
