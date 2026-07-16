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
 * reconciled during NativeServiceProvider registration before queue workers
 * consume them, with AppServiceProvider retaining a request-time backstop.
 *
 * Like the startup-resilience patches, this targets the COMPILED dist/ —
 * the publish build never recompiles the package's src/*.ts.
 */
const OPTIMIZE_PATCH_FILE = 'scripts/nativephp-patches/files/resources/electron/electron-plugin/dist/server/php.js';

const NATIVEPHP_DIST_PHP_JS = 'vendor/nativephp/desktop/resources/electron/electron-plugin/dist/server/php.js';

it('version-guards shouldOptimize in the shipped dist patch file', function (): void {
    $php = (string) file_get_contents(base_path(OPTIMIZE_PATCH_FILE));

    expect($php)->toContain("store.get('optimized_version') !== app.getVersion()")
        ->toContain('isRecoveryLaunch()')
        // The migrate guard and the store writes must survive the whole-file copy.
        ->toContain("store.get('migrated_version') !== app.getVersion()")
        ->toContain("store.set('optimized_version', app.getVersion())");
});

it('clears only disposable caches before a recovery bootstrap', function (): void {
    $php = (string) file_get_contents(base_path(OPTIMIZE_PATCH_FILE));
    $recoveryStart = strpos($php, 'function prepareRecoveryLaunch()');
    $optimizeStart = strpos($php, 'function hasNightwatchInstalled');
    $recoveryMethod = substr($php, $recoveryStart, $optimizeStart - $recoveryStart);

    expect($recoveryStart)->toBeInt()
        ->and($optimizeStart)->toBeInt()
        ->and($php)->toContain("const RECOVERY_ARG = '--nativephp-recovery';")
        ->and($recoveryMethod)->toContain('isRecoveryLaunch()')
        ->toContain('emptyDirSync(bootstrapCache)')
        ->toContain('emptyDirSync')
        ->toContain("join(storagePath, 'framework', 'views')")
        ->toContain("join(storagePath, 'framework', 'cache', 'opcache')")
        ->toContain("store.delete('optimized_version')")
        ->not->toContain('databaseFile')
        ->not->toContain('database.sqlite');
});

it('gives every bootstrap PHP command the complete live runtime tuple', function (): void {
    $php = (string) file_get_contents(base_path(OPTIMIZE_PATCH_FILE));

    expect($php)
        ->toContain('getDefaultEnvironmentVariables(state.randomSecret, state.electronApiPort)')
        ->toContain("NATIVEPHP_RUNNING: 'true'")
        ->toContain('NATIVEPHP_STORAGE_PATH: storagePath')
        ->toContain('NATIVEPHP_DATABASE_PATH: databaseFile')
        ->toContain('variables.NATIVEPHP_API_URL')
        ->toContain('variables.NATIVEPHP_SECRET');
});

it('wires the php.js dist patch file through apply.sh', function (): void {
    $apply = (string) file_get_contents(base_path('scripts/nativephp-patches/apply.sh'));

    expect($apply)->toContain('resources/electron/electron-plugin/dist/server/php.js');
});

it('targets a compiled dist file that still exists in the installed NativePHP package', function (): void {
    expect(base_path(NATIVEPHP_DIST_PHP_JS))->toBeReadableFile();
});
