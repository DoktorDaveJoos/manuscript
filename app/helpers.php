<?php

if (! function_exists('cssEscape')) {
    /**
     * Escape a string for safe use inside CSS content: "..." properties.
     */
    function cssEscape(string $value): string
    {
        $value = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

        return preg_replace('/[\r\n]+/', '', $value);
    }
}

if (! function_exists('clear_stale_route_cache')) {
    /**
     * Delete any lingering routes-v7.php left over from prior installs.
     *
     * NativePHP's Electron main process runs `php artisan optimize` on every
     * production launch. Combined with a writable APP_ROUTES_CACHE path, a
     * stale cache file from v0.4.7/v0.4.8 pins users to old routes via
     * Laravel's loadCachedRoutes(). Since this project skips route:cache both
     * at build time and at runtime, the file should never exist — if it does,
     * it's leftover from a prior install and must go. See AppServiceProvider's
     * OptimizeCommand override and Sentry issue 112195649.
     */
    function clear_stale_route_cache(?string $path = null): void
    {
        $path ??= $_SERVER['APP_ROUTES_CACHE']
            ?? $_ENV['APP_ROUTES_CACHE']
            ?? dirname(__DIR__).'/bootstrap/cache/routes-v7.php';

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return;
        }

        @unlink($path);
    }
}
