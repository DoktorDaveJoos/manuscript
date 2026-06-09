<?php

use App\Console\Commands\OptimizeCommand as AppOptimize;
use Illuminate\Foundation\Console\OptimizeCommand as FrameworkOptimize;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Regression test for Sentry issue 112195649 — v0.4.8 users crashed on launch
 * because NativePHP calls `php artisan optimize` on every production launch
 * and Laravel's default OptimizeCommand chains `route:cache`, which throws a
 * TypeError when a stale CompiledRouteCollection is in play.
 *
 * Excluding `route:cache` from the task list is the targeted fix.
 *
 * Also covers Sentry issue 123582591 — the same per-launch `optimize` crashed
 * when the bootstrap cache directory is read-only (macOS App Translocation runs
 * an un-moved .app from a read-only mount; non-secure builds don't redirect
 * APP_CONFIG_CACHE to the writable userData path). config:cache/event:cache
 * then fail with "Read-only file system", so every cache task is dropped when
 * the target directory can't be written.
 */

/**
 * Drive Application::getCachedConfigPath() by overriding APP_CONFIG_CACHE — the
 * same env NativePHP sets for secure builds. normalizeCachePath() returns an
 * absolute value verbatim, so the command resolves exactly this path.
 */
function usingConfigCachePath(string $path, Closure $assertions): void
{
    $previous = $_SERVER['APP_CONFIG_CACHE'] ?? null;

    $_SERVER['APP_CONFIG_CACHE'] = $path;

    try {
        $assertions();
    } finally {
        if ($previous === null) {
            unset($_SERVER['APP_CONFIG_CACHE']);
        } else {
            $_SERVER['APP_CONFIG_CACHE'] = $previous;
        }
    }
}

it('extends the framework OptimizeCommand', function () {
    expect(new AppOptimize)->toBeInstanceOf(FrameworkOptimize::class);
});

it('does not include route:cache in the optimize task list', function () {
    $tasks = (new AppOptimize)->getOptimizeTasks();

    expect($tasks)
        ->not->toHaveKey('routes')
        ->and(array_values($tasks))->not->toContain('route:cache');
});

it('keeps config, events, and views tasks intact', function () {
    $tasks = (new AppOptimize)->getOptimizeTasks();

    expect($tasks)
        ->toHaveKey('config', 'config:cache')
        ->toHaveKey('events', 'event:cache')
        ->toHaveKey('views', 'view:cache');
});

it('drops every cache task when the bootstrap cache directory is read-only', function () {
    // A missing/unwritable directory mirrors a translocated, read-only bundle:
    // config:cache and event:cache would throw, so nothing should be attempted.
    usingConfigCachePath('/nonexistent-'.uniqid().'/bootstrap/cache/config.php', function () {
        expect((new AppOptimize)->getOptimizeTasks())->toBe([]);
    });
});

it('keeps cache tasks when the bootstrap cache directory is writable', function () {
    $directory = sys_get_temp_dir().'/manuscript-optimize-'.uniqid();
    mkdir($directory, 0775, true);

    usingConfigCachePath($directory.'/config.php', function () {
        expect((new AppOptimize)->getOptimizeTasks())
            ->toHaveKey('config', 'config:cache')
            ->toHaveKey('events', 'event:cache')
            ->not->toHaveKey('routes');
    });

    rmdir($directory);
});

it('rebinds the command.optimize container alias to the app override', function () {
    // ArtisanServiceProvider registers command.optimize lazily when Artisan
    // boots. Touching Artisan in the test forces the registration.
    $this->artisan('list', ['--raw' => true]);

    expect(app('command.optimize'))->toBeInstanceOf(AppOptimize::class);
});
