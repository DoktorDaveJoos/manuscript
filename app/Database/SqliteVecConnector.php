<?php

namespace App\Database;

use App\Services\SqliteVec\SqliteVecService;
use Illuminate\Database\Connectors\SQLiteConnector;
use Illuminate\Support\Facades\Log;

class SqliteVecConnector extends SQLiteConnector
{
    private static bool $integrityChecked = false;

    public function __construct(protected SqliteVecService $sqliteVec) {}

    /**
     * Establish a database connection, check integrity, and load sqlite-vec.
     *
     * @return \PDO
     */
    public function connect(array $config)
    {
        $pdo = parent::connect($config);

        $database = $config['database'] ?? '';

        // Run integrity check on the first real (non-memory) connection.
        // This fires when NativePHP's rewriteDatabase() issues its first
        // DB::statement(), before any middleware or route runs.
        // Must run BEFORE PRAGMAs — PRAGMAs themselves throw on corrupt files.
        if (! self::$integrityChecked && $database !== ':memory:' && $database !== '') {
            self::$integrityChecked = true;

            try {
                $result = $pdo->query('PRAGMA quick_check')->fetchColumn();

                if ($result !== 'ok') {
                    throw new \RuntimeException("PRAGMA quick_check failed: {$result}");
                }
            } catch (\Throwable $e) {
                // Transient errors (locks, IO, permissions) must surface so we
                // don't wipe a healthy DB because of a momentary disk hiccup.
                if (! $this->isCorruptionError($e)) {
                    Log::error('DatabaseIntegrity: non-corruption error during quick_check, surfacing as boot failure.', [
                        'path' => $database,
                        'error' => $e->getMessage(),
                    ]);

                    throw $e;
                }

                $pdo = $this->repairDatabase($config, $e);
            }
        }

        // PRAGMAs are set per PDO instance, not per file. Apply them here so
        // they run once per connection (not once per request) and so the
        // runtime `nativephp` connection gets them too — its config entry
        // doesn't carry the `pragmas` array that the default sqlite config has.
        $pdo->exec(
            'PRAGMA journal_mode = WAL;'
            .'PRAGMA synchronous = NORMAL;'
            .'PRAGMA cache_size = -64000;'
            .'PRAGMA mmap_size = 268435456;'
            .'PRAGMA temp_store = MEMORY;'
            .'PRAGMA busy_timeout = 5000;'
        );

        try {
            $this->sqliteVec->load($pdo);
        } catch (\Throwable $e) {
            Log::warning('sqlite-vec: Failed to load extension.', [
                'error' => $e->getMessage(),
            ]);
        }

        return $pdo;
    }

    /**
     * Return true iff the exception signals actual DB-file corruption (not a
     * transient lock / IO / permission problem). Corruption signatures come
     * from SQLite: either `PRAGMA quick_check` returned non-'ok', or the
     * driver raised SQLITE_CORRUPT / SQLITE_NOTADB.
     */
    private function isCorruptionError(\Throwable $e): bool
    {
        // Our own RuntimeException from a non-'ok' quick_check result.
        if ($e instanceof \RuntimeException && str_starts_with($e->getMessage(), 'PRAGMA quick_check failed:')) {
            return true;
        }

        $message = strtolower($e->getMessage());

        foreach (['malformed', 'database disk image', 'not a database', 'file is encrypted', 'corrupt'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Back up the corrupt database file and return a fresh PDO connection.
     *
     * The main `.sqlite` rename is a hard prerequisite — if it fails (file in
     * use on Windows, permission denied, etc.) we MUST NOT create a fresh file
     * on top of the user's data. We raise, boot fails, user's data is preserved.
     */
    private function repairDatabase(array $config, \Throwable $cause): \PDO
    {
        $path = $config['database'];
        $timestamp = date('Y-m-d_His');
        $backupPath = "{$path}.corrupt.{$timestamp}";

        Log::error('DatabaseIntegrity: corrupt database detected, repairing.', [
            'path' => $path,
            'backup' => $backupPath,
            'error' => $cause->getMessage(),
        ]);

        // Marker read by /repair-status so the loading screen can show an
        // accurate "restoring your data…" state.
        @file_put_contents(self::markerPath($path), (string) json_encode([
            'started_at' => date('c'),
            'trigger' => $cause->getMessage(),
        ]));

        $renamed = @rename($path, $backupPath);

        if (! $renamed) {
            $lastError = error_get_last()['message'] ?? 'unknown error';

            Log::error('DatabaseIntegrity: cannot back up corrupt database, aborting repair.', [
                'path' => $path,
                'backup' => $backupPath,
                'rename_error' => $lastError,
            ]);

            @unlink(self::markerPath($path));

            throw new \RuntimeException(
                "Database corruption detected but backup failed ({$lastError}). "
                ."Original file preserved at {$path}.",
                0,
                $cause,
            );
        }

        // Best-effort: WAL/SHM are recreated automatically on next open.
        @rename("{$path}-wal", "{$backupPath}-wal");
        @rename("{$path}-shm", "{$backupPath}-shm");

        // Create a fresh empty database file.
        touch($path);

        // Store repair info so the booted() callback can attempt data recovery
        // and middleware can notify the frontend.
        app()->instance('database.repaired', [
            'backup' => $backupPath,
            'trigger' => $cause->getMessage(),
            'recovered' => [],
            'failed' => [],
        ]);

        return parent::connect($config);
    }

    /**
     * Reset the integrity-checked flag (for testing only).
     */
    public static function resetIntegrityFlag(): void
    {
        self::$integrityChecked = false;
    }

    /**
     * Path of the repair marker. Derived from the actual database file so it
     * works under NativePHP, which stores the SQLite file in the user-data
     * directory — `database_path()` would point at the bundled project dir
     * and miss the marker written next to the real DB.
     */
    public static function markerPath(?string $databasePath = null): string
    {
        $databasePath ??= (string) config('database.connections.'.config('database.default').'.database');

        return dirname($databasePath).DIRECTORY_SEPARATOR.'.repairing';
    }
}
