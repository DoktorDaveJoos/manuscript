<?php

namespace App\Database;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A migrator that snapshots the SQLite database before running pending
 * migrations and restores the snapshot when the run fails.
 *
 * Laravel does not wrap SQLite migrations in transactions (the schema
 * grammar's $transactions is false — SQLite's ALTER TABLE emulation toggles
 * PRAGMA foreign_keys, which is a no-op inside a transaction). A migration
 * failing mid-run therefore leaves partial DDL behind with the migration
 * unrecorded: every subsequent launch-time `migrate --force` re-runs it,
 * hits "already exists", and the schema is permanently wedged.
 *
 * VACUUM INTO produces a fully-checkpointed copy (WAL content included) and
 * costs one O(database size) write ONLY on runs that actually have pending
 * migrations — i.e. the first launch after an app update.
 */
class ResilientMigrator extends Migrator
{
    /**
     * Run an array of migrations, restoring the pre-run snapshot on failure.
     *
     * @param  list<string>  $migrations
     * @param  array{pretend?: bool, step?: bool}  $options
     */
    public function runPending(array $migrations, array $options = [])
    {
        $snapshotPath = ($options['pretend'] ?? false)
            ? null
            : $this->snapshotDatabase($migrations);

        try {
            parent::runPending($migrations, $options);
        } catch (Throwable $e) {
            if ($snapshotPath !== null) {
                $this->restoreSnapshot($snapshotPath);
            }

            throw $e;
        }

        if ($snapshotPath !== null) {
            @unlink($snapshotPath);
        }
    }

    /**
     * Snapshot the SQLite database the pending run will mutate. Best-effort:
     * a failed snapshot logs and returns null rather than blocking migration.
     *
     * @param  list<string>  $migrations
     */
    private function snapshotDatabase(array $migrations): ?string
    {
        if ($migrations === []) {
            return null;
        }

        $connection = $this->resolveConnection(null);

        if ($connection->getDriverName() !== 'sqlite') {
            return null;
        }

        $path = $connection->getConfig('database');

        if (! is_string($path) || $path === '' || $path === ':memory:' || ! is_file($path)) {
            return null;
        }

        $snapshotPath = $path.'.pre-migrate.'.date('Y-m-d_His');

        // VACUUM INTO refuses to overwrite an existing file.
        @unlink($snapshotPath);

        try {
            $pdo = $connection->getPdo();
            $pdo->exec('VACUUM INTO '.$pdo->quote($snapshotPath));
        } catch (Throwable $e) {
            Log::warning('ResilientMigrator: pre-migrate snapshot failed, migrating without one.', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return $snapshotPath;
    }

    /**
     * Replace the live database with the pre-run snapshot. The snapshot is
     * seconds old and from this same process, so no user data is discarded.
     * Best-effort: if the restore itself fails, the snapshot file is left on
     * disk for manual recovery and the original migration error still
     * propagates to the caller.
     */
    private function restoreSnapshot(string $snapshotPath): void
    {
        $connection = $this->resolveConnection(null);
        $path = (string) $connection->getConfig('database');

        try {
            $connection->disconnect();

            @unlink($path);
            @unlink($path.'-wal');
            @unlink($path.'-shm');

            if (! @rename($snapshotPath, $path)) {
                Log::error('ResilientMigrator: could not restore pre-migrate snapshot; it remains on disk.', [
                    'path' => $path,
                    'snapshot' => $snapshotPath,
                ]);

                return;
            }

            Log::error('ResilientMigrator: migration run failed; restored the pre-migrate snapshot.', [
                'path' => $path,
            ]);
        } catch (Throwable $e) {
            Log::error('ResilientMigrator: snapshot restore failed; it remains on disk.', [
                'path' => $path,
                'snapshot' => $snapshotPath,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
