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
     * Back up the corrupt database file and return a fresh PDO connection.
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

        // Back up corrupt file and its WAL/SHM companions.
        @rename($path, $backupPath);
        @rename("{$path}-wal", "{$backupPath}-wal");
        @rename("{$path}-shm", "{$backupPath}-shm");

        // Create a fresh empty database file.
        touch($path);

        // Store repair info so the booted() callback can attempt data recovery
        // and middleware can notify the frontend.
        app()->instance('database.repaired', [
            'backup' => $backupPath,
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
}
