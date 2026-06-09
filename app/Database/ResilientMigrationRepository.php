<?php

namespace App\Database;

use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * A migration repository that creates its bookkeeping table idempotently.
 *
 * Laravel only calls {@see createRepository()} when {@see repositoryExists()}
 * — a `Schema::hasTable('migrations')` probe — reports the table as absent.
 * On Windows SQLite in WAL mode that probe can under-report a table that
 * physically exists, so the follow-up `CREATE TABLE migrations` throws
 * "table already exists" and aborts the entire boot-time migration that
 * NativePHP runs on launch (`php artisan migrate --force`). See Sentry issue
 * 123909138.
 *
 * Creating the migrations table is meant to be idempotent: a table that
 * already exists is success, not failure. Any other failure still surfaces.
 */
class ResilientMigrationRepository extends DatabaseMigrationRepository
{
    /**
     * Create the migration repository data store, tolerating a pre-existing
     * table that the existence probe failed to report.
     */
    public function createRepository(): void
    {
        try {
            parent::createRepository();
        } catch (QueryException $e) {
            if (! self::isTableAlreadyExistsError($e)) {
                throw $e;
            }
        }
    }

    /**
     * Whether the throwable signals that the target table already exists.
     *
     * SQLite reports this as `table "migrations" already exists`; MySQL and
     * Postgres use the same "already exists" phrasing, so a substring match
     * covers every driver without coupling to a specific SQLSTATE.
     */
    public static function isTableAlreadyExistsError(Throwable $e): bool
    {
        return str_contains(strtolower($e->getMessage()), 'already exists');
    }
}
