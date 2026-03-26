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
        Window::open()
            ->title('Manuscript')
            ->width(1440)
            ->height(900)
            ->minWidth(1024)
            ->minHeight(680);

        AutoUpdater::checkForUpdates();
    }

    /**
     * Tuned for a single-user desktop app handling large manuscripts
     * (1000+ pages / 300k+ words). PHP's defaults are conservative for
     * shared hosting — a desktop app can be much more aggressive.
     */
    public function phpIni(): array
    {
        return [
            'memory_limit' => '1G',
            'max_execution_time' => '300',
            'post_max_size' => '100M',
            'upload_max_filesize' => '100M',
            'pcre.backtrack_limit' => '5000000',
            'opcache.enable' => '1',
            'opcache.memory_consumption' => '256',
            'opcache.interned_strings_buffer' => '32',
            'opcache.max_accelerated_files' => '20000',
            'opcache.jit' => 'tracing',
            'opcache.jit_buffer_size' => '128M',
            'realpath_cache_size' => '4096K',
            'realpath_cache_ttl' => '600',
        ];
    }
}
