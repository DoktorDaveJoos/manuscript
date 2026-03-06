<?php

namespace App\Services\SqliteVec;

use Illuminate\Support\Facades\Log;
use PDO;
use Pdo\Sqlite;

class SqliteVecService
{
    /**
     * Load the sqlite-vec extension into the given PDO connection.
     */
    public function load(PDO $pdo): bool
    {
        if ($this->isLoaded($pdo)) {
            return true;
        }

        if (! ($pdo instanceof Sqlite)) {
            Log::warning('sqlite-vec: PDO connection is not Pdo\Sqlite, cannot load extension.');

            return false;
        }

        if (! method_exists($pdo, 'loadExtension')) {
            Log::warning('sqlite-vec: Pdo\Sqlite::loadExtension() is not available.');

            return false;
        }

        $path = $this->resolveBinaryPath();

        if ($path === null) {
            Log::warning('sqlite-vec: No binary found for this platform.');

            return false;
        }

        $pdo->loadExtension($path);

        return $this->isLoaded($pdo);
    }

    /**
     * Check if sqlite-vec is already loaded on the connection.
     */
    public function isLoaded(PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT vec_version()');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get the sqlite-vec version from the connection.
     */
    public function version(PDO $pdo): ?string
    {
        try {
            return $pdo->query('SELECT vec_version()')->fetchColumn() ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resolve the full path to the sqlite-vec binary for the current platform.
     *
     * Returns the absolute path including file extension, as required by Pdo\Sqlite::loadExtension().
     */
    public function resolveBinaryPath(): ?string
    {
        $platform = $this->detectPlatform();

        if ($platform === null) {
            return null;
        }

        $basePath = base_path("bin/sqlite-vec/{$platform}/vec0");

        $extensions = ['dylib', 'so', 'dll'];
        foreach ($extensions as $ext) {
            if (file_exists("{$basePath}.{$ext}")) {
                return "{$basePath}.{$ext}";
            }
        }

        return null;
    }

    /**
     * Detect the current platform directory name.
     */
    protected function detectPlatform(): ?string
    {
        $os = PHP_OS_FAMILY;
        $arch = php_uname('m');

        return match (true) {
            $os === 'Darwin' && in_array($arch, ['arm64', 'aarch64']) => 'macos-arm64',
            $os === 'Darwin' && $arch === 'x86_64' => 'macos-x64',
            $os === 'Linux' && $arch === 'x86_64' => 'linux-x64',
            $os === 'Linux' && in_array($arch, ['aarch64', 'arm64']) => 'linux-arm64',
            $os === 'Windows' && in_array($arch, ['AMD64', 'x86_64']) => 'win-x64',
            default => null,
        };
    }
}
