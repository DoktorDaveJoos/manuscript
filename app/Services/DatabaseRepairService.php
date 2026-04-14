<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DatabaseRepairService
{
    /**
     * Tables to skip during recovery (framework-managed, virtual, or
     * extension-dependent tables that can't be read with a raw PDO).
     */
    private const SKIP_TABLES = [
        'migrations',
        'sqlite_sequence',
        'chunk_embeddings',
        'chunks_fts',
    ];

    /**
     * Attempt to recover data from a corrupt database backup into the current
     * (fresh, migrated) database. Best-effort: tables with corrupted pages are
     * skipped and logged. Never throws — a failed recovery must not crash boot.
     *
     * @return array{recovered: list<string>, failed: list<string>}
     */
    public function recoverData(string $corruptPath): array
    {
        $recovered = [];
        $failed = [];

        if (! file_exists($corruptPath)) {
            Log::warning('DatabaseRepair: backup file not found, skipping recovery.', [
                'path' => $corruptPath,
            ]);

            return compact('recovered', 'failed');
        }

        try {
            $corruptPdo = new \PDO("sqlite:{$corruptPath}");
            $corruptPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $corruptPdo->setAttribute(\PDO::ATTR_TIMEOUT, 5);
        } catch (\Throwable $e) {
            Log::warning('DatabaseRepair: cannot open corrupt backup.', [
                'path' => $corruptPath,
                'error' => $e->getMessage(),
            ]);

            return compact('recovered', 'failed');
        }

        // Discover tables in the corrupt database.
        try {
            $tables = $corruptPdo->query(
                "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
            )->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            Log::warning('DatabaseRepair: cannot read sqlite_master from backup.', [
                'error' => $e->getMessage(),
            ]);

            return compact('recovered', 'failed');
        }

        // Disable foreign keys during import to avoid ordering issues.
        DB::statement('PRAGMA foreign_keys = OFF');

        try {
            foreach ($tables as $table) {
                if (in_array($table, self::SKIP_TABLES, true)) {
                    continue;
                }

                // Only import into tables that exist in the fresh schema.
                if (! Schema::hasTable($table)) {
                    continue;
                }

                try {
                    $this->recoverTable($corruptPdo, $table);
                    $recovered[] = $table;
                } catch (\Throwable $e) {
                    $failed[] = $table;
                    Log::warning("DatabaseRepair: failed to recover table [{$table}].", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }

        Log::info('DatabaseRepair: recovery complete.', [
            'recovered' => $recovered,
            'failed' => $failed,
        ]);

        return compact('recovered', 'failed');
    }

    /**
     * Copy all readable rows from a single table in the corrupt database.
     * Wrapped in a transaction for performance (single fsync per table).
     * Uses insertOrIgnore so recovery is idempotent if called twice.
     */
    private function recoverTable(\PDO $corruptPdo, string $table): void
    {
        $stmt = $corruptPdo->query("SELECT * FROM \"{$table}\"");

        DB::transaction(function () use ($stmt, $table) {
            $batch = [];

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $batch[] = $row;

                if (count($batch) >= 500) {
                    DB::table($table)->insertOrIgnore($batch);
                    $batch = [];
                }
            }

            if (! empty($batch)) {
                DB::table($table)->insertOrIgnore($batch);
            }
        });
    }
}
