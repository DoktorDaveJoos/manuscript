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
     * Prefixes identifying FTS5 / vec0 shadow tables. These tables appear in
     * `sqlite_master` as real tables and pass `Schema::hasTable()`, but direct
     * INSERT into them corrupts the virtual-table indices — they are managed
     * internally by the FTS/vec extensions and must be rebuilt, not copied.
     */
    private const SKIP_TABLE_PREFIXES = [
        'chunks_fts_',
        'chunk_embeddings_',
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
                if ($this->shouldSkipTable($table)) {
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

    private function shouldSkipTable(string $table): bool
    {
        if (in_array($table, self::SKIP_TABLES, true)) {
            return true;
        }

        foreach (self::SKIP_TABLE_PREFIXES as $prefix) {
            if (str_starts_with($table, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Copy all readable rows from a single table in the corrupt database.
     *
     * If the backup table has ANY rows, the fresh table is truncated first so
     * the user's customized values (e.g., app_settings.key='show_ai_features'
     * set to 'false') win over the seed defaults from migrations. `insertOrIgnore`
     * silently drops conflicting backup rows and would reset every user
     * preference on every recovery.
     *
     * If the backup table is empty, we leave the seed rows alone — an empty
     * backup of a seeded table would otherwise wipe it to nothing.
     *
     * All in a single transaction: if any insert fails, the truncate rolls
     * back too and the table stays in its seed state.
     *
     * Columns are filtered to the fresh schema so rows from a pre-migration
     * backup (which may carry dropped/renamed columns) still insert cleanly.
     */
    private function recoverTable(\PDO $corruptPdo, string $table): void
    {
        $freshColumns = array_flip(Schema::getColumnListing($table));
        $stmt = $corruptPdo->query("SELECT * FROM \"{$table}\"");

        DB::transaction(function () use ($stmt, $table, $freshColumns) {
            $truncated = false;
            $batch = [];

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                if (! $truncated) {
                    DB::table($table)->truncate();
                    $truncated = true;
                }

                $batch[] = array_intersect_key($row, $freshColumns);

                if (count($batch) >= 500) {
                    DB::table($table)->insert($batch);
                    $batch = [];
                }
            }

            if (! empty($batch)) {
                DB::table($table)->insert($batch);
            }
        });
    }
}
