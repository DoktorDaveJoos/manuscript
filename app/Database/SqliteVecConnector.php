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
