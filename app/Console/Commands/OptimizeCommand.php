<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\OptimizeCommand as FrameworkOptimizeCommand;

/**
 * NativePHP's Electron main process invokes `php artisan optimize` on every
 * production launch. Laravel's default task list chains `route:cache`, which
 * throws a TypeError in the packaged desktop build when a CompiledRouteCollection
 * is in play (see Sentry issue 112195649). Routes for this single-user desktop
 * app are tiny and fast to resolve from source — caching them buys nothing.
 */
class OptimizeCommand extends FrameworkOptimizeCommand
{
    public function getOptimizeTasks(): array
    {
        $tasks = parent::getOptimizeTasks();

        unset($tasks['routes']);

        return $tasks;
    }
}
