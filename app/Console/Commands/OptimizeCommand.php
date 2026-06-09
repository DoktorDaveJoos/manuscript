<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\OptimizeCommand as FrameworkOptimizeCommand;

/**
 * NativePHP's Electron main process invokes `php artisan optimize` on every
 * production launch. Laravel's default task list chains `route:cache`, which
 * throws a TypeError in the packaged desktop build when a CompiledRouteCollection
 * is in play (see Sentry issue 112195649). Routes for this single-user desktop
 * app are tiny and fast to resolve from source — caching them buys nothing.
 *
 * The remaining bootstrap-cache writers (`config:cache`, `event:cache`) crash
 * the same per-launch `optimize` when their target is read-only: macOS App
 * Translocation runs an un-moved .app from a randomized read-only mount, and a
 * non-secure build doesn't redirect APP_CONFIG_CACHE to the writable userData
 * path, so the write lands inside the read-only bundle (Sentry issue 123582591).
 * The bundle already ships a build-time config cache and views compile on demand
 * into the writable storage path, so we skip every cache task when the bootstrap
 * cache directory can't be written rather than letting the launch crash.
 */
class OptimizeCommand extends FrameworkOptimizeCommand
{
    public function getOptimizeTasks(): array
    {
        $tasks = parent::getOptimizeTasks();

        unset($tasks['routes']);

        if (! $this->bootstrapCacheIsWritable()) {
            return [];
        }

        return $tasks;
    }

    /**
     * Whether the directory holding the bootstrap cache files (config, events,
     * …) can be written. Returns false on a read-only/translocated bundle.
     */
    private function bootstrapCacheIsWritable(): bool
    {
        $directory = dirname(($this->laravel ?? app())->getCachedConfigPath());

        return is_dir($directory) && is_writable($directory);
    }
}
