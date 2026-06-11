<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * Guards the per-launch-optimize patch.
 *
 * Stock NativePHP runs `php artisan optimize` synchronously on EVERY
 * production launch (shouldOptimize() ignores the `optimized_version` it
 * stores), adding a full cold PHP boot + cache rebuild to startup. The patch
 * version-guards it exactly like shouldMigrateDatabase(), so optimize only
 * runs on the first launch after an install/update. The per-launch values
 * baked into the cached config (NATIVEPHP_SECRET, NATIVEPHP_API_URL) are
 * reconciled at request time by AppServiceProvider::healStaleNativePhpConfig.
 *
 * Like the startup-resilience patches, this targets the COMPILED dist/ —
 * the publish build never recompiles the package's src/*.ts.
 */
const OPTIMIZE_PATCH_FILE = 'scripts/nativephp-patches/files/resources/electron/electron-plugin/dist/server/php.js';

const NATIVEPHP_DIST_PHP_JS = 'vendor/nativephp/desktop/resources/electron/electron-plugin/dist/server/php.js';

it('version-guards shouldOptimize in the shipped dist patch file', function (): void {
    $php = (string) file_get_contents(base_path(OPTIMIZE_PATCH_FILE));

    expect($php)->toContain("store.get('optimized_version') !== app.getVersion()")
        // The migrate guard and the store writes must survive the whole-file copy.
        ->toContain("store.get('migrated_version') !== app.getVersion()")
        ->toContain("store.set('optimized_version', app.getVersion())");
});

it('wires the php.js dist patch file through apply.sh', function (): void {
    $apply = (string) file_get_contents(base_path('scripts/nativephp-patches/apply.sh'));

    expect($apply)->toContain('resources/electron/electron-plugin/dist/server/php.js');
});

it('targets a compiled dist file that still exists in the installed NativePHP package', function (): void {
    expect(base_path(NATIVEPHP_DIST_PHP_JS))->toBeReadableFile();
});
