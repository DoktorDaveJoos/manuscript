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
 */
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

it('rebinds the command.optimize container alias to the app override', function () {
    // ArtisanServiceProvider registers command.optimize lazily when Artisan
    // boots. Touching Artisan in the test forces the registration.
    $this->artisan('list', ['--raw' => true]);

    expect(app('command.optimize'))->toBeInstanceOf(AppOptimize::class);
});
