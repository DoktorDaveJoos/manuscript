<?php

namespace App\Providers;

use App\Services\BackupService;
use App\Services\StaleUpdateGuard;
use Illuminate\Support\Facades\File;
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
        // Apply any pending backup import / revert BEFORE the rest of boot
        // touches the DB. The swap renames files on disk and is safe to run
        // unconditionally — when there is nothing pending it returns silently.
        app(BackupService::class)->applyPending();

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
            ->minHeight(680);

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

        // Stop a previously-stranded Squirrel update from looping (App Still
        // Running / ShipIt respawn storm) before we kick off a fresh check.
        // Cheap and no-op when nothing is pending; never throws.
        app(StaleUpdateGuard::class)->reconcile();

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

            // Every launch spawns several short-lived PHP processes
            // (native:php-ini, native:config, optimize/migrate after updates,
            // the queue worker, the cli-server) and OPcache shared memory dies
            // with each one. A file cache persists compiled opcodes across
            // processes AND launches, so only the first launch after an update
            // pays the framework compile. The directory is keyed by app
            // version because validate_timestamps=0 would otherwise serve
            // stale opcodes after an update.
            if ($fileCacheDir = $this->ensureOpcacheFileCacheDirectory()) {
                $ini['opcache.file_cache'] = $fileCacheDir;
            }
        }

        return $ini;
    }

    /**
     * Create (and return) the version-keyed OPcache file-cache directory,
     * pruning directories left behind by previous app versions. Best-effort:
     * returns null on any failure so a filesystem hiccup can never block the
     * launch — PHP simply falls back to per-process in-memory OPcache.
     */
    private function ensureOpcacheFileCacheDirectory(): ?string
    {
        try {
            $version = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) config('nativephp.version', '0.0.0'));
            $base = storage_path('framework/cache/opcache');
            $directory = $base.DIRECTORY_SEPARATOR.$version;

            if (is_dir($base)) {
                foreach (File::directories($base) as $existing) {
                    if (basename($existing) !== $version) {
                        File::deleteDirectory($existing);
                    }
                }
            }

            File::ensureDirectoryExists($directory);

            return is_dir($directory) && is_writable($directory) ? $directory : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
