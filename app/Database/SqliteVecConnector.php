<?php

namespace App\Database;

use App\Services\SqliteVec\SqliteVecService;
use Illuminate\Database\Connectors\SQLiteConnector;
use Illuminate\Support\Facades\Log;

class SqliteVecConnector extends SQLiteConnector
{
    public function __construct(protected SqliteVecService $sqliteVec) {}

    /**
     * Establish a database connection and load sqlite-vec.
     *
     * @return \PDO
     */
    public function connect(array $config)
    {
        $pdo = parent::connect($config);

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
}
