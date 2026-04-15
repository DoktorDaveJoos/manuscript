<?php

namespace App\Providers;

use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\AutoUpdater;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        // In the bundled release the framework boot + first JS bundle parse
        // costs a few hundred ms, so we open at a tiny static loading page
        // first and let it redirect to '/'. In dev that round-trip just
        // causes Vite to re-resolve every module, which actually feels
        // slower — so dev opens at '/' directly.
        $pending = Window::open()
            ->title('Manuscript')
            ->backgroundColor('#161616')
            ->width(1440)
            ->height(900)
            ->minWidth(1024)
            ->minHeight(680)
            ->webPreferences(['spellcheck' => true]);

        if (app()->isProduction()) {
            // Must be an absolute URL — NativePHP forwards the string straight
            // to Electron's window.loadURL(), which rejects bare paths. The
            // Window constructor's default uses url('/') for the same reason.
            $pending->url(url('/loading'));
        }

        // PendingOpenWindow uses __destruct to actually open the window;
        // unset to fire it now rather than at end of method, so the order
        // matches the original (window first, then updater check).
        unset($pending);

        AutoUpdater::checkForUpdates();
    }

    /**
     * Tuned for a single-user desktop app handling large manuscripts
     * (1000+ pages / 300k+ words). PHP's defaults are conservative for
     * shared hosting — a desktop app can be much more aggressive.
     *
     * Critical: NativePHP runs PHP in CLI mode, where OPcache is disabled
     * by default. Without `opcache.enable_cli=1` every other opcache.* tuning
     * below (JIT, memory, interned strings) is silently ignored and every
     * request re-parses every PHP file from disk.
     */
    public function phpIni(): array
    {
        $ini = [
            'memory_limit' => '1G',
            // CLI default. Per-job ceilings come from Laravel's queue --timeout
            // (SIGALRM, fails the job and keeps the worker alive) instead of
            // PHP's hard limit (FatalError, kills the worker).
            'max_execution_time' => '0',
            'post_max_size' => '100M',
            'upload_max_filesize' => '100M',
            'pcre.backtrack_limit' => '5000000',
            'opcache.enable' => '1',
            'opcache.enable_cli' => '1',
            'opcache.memory_consumption' => '256',
            'opcache.interned_strings_buffer' => '32',
            'opcache.max_accelerated_files' => '20000',
            'opcache.jit' => 'tracing',
            'opcache.jit_buffer_size' => '128M',
            'opcache.save_comments' => '1',
            'opcache.enable_file_override' => '1',
            'realpath_cache_size' => '4096K',
            'realpath_cache_ttl' => '600',
        ];

        // Bundled-release files are immutable — skip per-request mtime checks
        // for a large speedup. In dev keep the default so PHP edits hot-reload.
        if (app()->isProduction()) {
            $ini['opcache.validate_timestamps'] = '0';
        }

        return $ini;
    }
}
