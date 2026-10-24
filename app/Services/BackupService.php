<?php

namespace App\Services;

use App\Models\AppSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Owns the local backup workflow: export the live SQLite DB, stage an import,
 * stage a revert, and apply pending swaps at the next launch.
 *
 * The "pending swap" state lives in a JSON sidecar next to the DB file —
 * NOT in the DB itself. Reading the DB to decide whether to replace the DB
 * is a chicken-and-egg problem.
 *
 * Staging never touches the live DB: the running app keeps using it until
 * quit. The swap happens in the next launch's bootstrap commands, before any
 * process has the database open (see the patched NativeServiceProvider).
 */
class BackupService
{
    public const SIDECAR_FILE = '.backup-state.json';

    public const LOCK_FILE = '.backup-state.lock';

    public const ROLLBACK_SUFFIX = '.rollback';

    public const PENDING_IMPORT_SUFFIX = '.pending-import';

    public const DISCARDED_PREFIX = '.discarded-';

    /**
     * How long a .discarded-<ts> copy survives. Both bootstrap commands of a
     * launch run applyPending() within seconds of each other, so the copy a
     * swap sets aside must outlive the second one; any later launch prunes it.
     */
    public const DISCARDED_RETENTION_SECONDS = 600;

    public const LAST_EXPORT_AT_KEY = 'backup.last_export_at';

    public const SNAPSHOT_EXTENSION = '.sqlite';

    public const ENCRYPTED_EXTENSION = '.msbk';

    /**
     * SQLite's per-database sidecar files. SQLite pairs them with their
     * database by file name, so they move with it: a -wal left behind at the
     * live path is replayed into whatever file lands there next.
     */
    private const SQLITE_SIDECAR_SUFFIXES = ['-wal', '-shm', '-journal'];

    public function __construct(
        private BackupEncryptionService $encryption,
        private string $databasePath,
    ) {}

    /**
     * Export the live database to a freshly-created temp file. Returns the
     * temp path. Caller is responsible for sending it as a download and
     * deleting it afterwards (BinaryFileResponse with deleteFileAfterSend).
     *
     * Empty $passphrase → plain SQLite snapshot. Non-empty → MSBK envelope.
     *
     * Uses VACUUM INTO rather than a raw file copy: the live DB runs in WAL
     * journal mode (see config/database.php and SqliteVecConnector), so a
     * raw copy would miss any uncheckpointed writes still sitting in the
     * sqlite-wal sidecar — schema flushes early, row data often does not,
     * which surfaces as "the export looks empty." VACUUM INTO produces a
     * fully-checkpointed, self-contained DB file with no WAL/SHM friends.
     */
    public function export(string $passphrase = ''): string
    {
        $live = $this->databasePath;
        if (! file_exists($live)) {
            throw new RuntimeException("Database file not found at {$live}.");
        }

        $tempReservation = tempnam(sys_get_temp_dir(), 'manuscript-backup-');
        if ($tempReservation === false) {
            throw new RuntimeException('Could not create temp file for backup export.');
        }

        $snapshotPath = $tempReservation.self::SNAPSHOT_EXTENSION;
        // VACUUM INTO refuses to overwrite an existing file, so rename the
        // tempnam reservation to the meaningful extension and clear it.
        if (! @rename($tempReservation, $snapshotPath)) {
            @unlink($tempReservation);
            throw new RuntimeException('Could not prepare temp path for backup export.');
        }
        @unlink($snapshotPath);

        try {
            $pdo = new PDO('sqlite:'.$live);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('VACUUM INTO '.$pdo->quote($snapshotPath));
            $pdo = null;
        } catch (PDOException $e) {
            @unlink($snapshotPath);
            throw new RuntimeException('Could not export database: '.$e->getMessage());
        }

        if ($passphrase === '') {
            AppSetting::set(self::LAST_EXPORT_AT_KEY, CarbonImmutable::now()->toIso8601String());

            return $snapshotPath;
        }

        $encryptedPath = $tempReservation.self::ENCRYPTED_EXTENSION;
        try {
            $this->encryption->encryptFile($snapshotPath, $encryptedPath, $passphrase);
        } catch (\Throwable $e) {
            @unlink($encryptedPath);
            throw $e;
        } finally {
            @unlink($snapshotPath);
        }

        AppSetting::set(self::LAST_EXPORT_AT_KEY, CarbonImmutable::now()->toIso8601String());

        return $encryptedPath;
    }

    /**
     * Validate the uploaded file, move it to .pending-import and flag the
     * import. The live DB is left alone — the next launch moves it aside as
     * the rollback once no process has it open.
     *
     * Throws on validation failure WITHOUT touching the live DB or rollback.
     */
    public function stageImport(string $uploadedTempPath, string $passphrase = ''): void
    {
        if (! file_exists($uploadedTempPath)) {
            throw new RuntimeException('Uploaded backup file is missing.');
        }

        // 1. Decrypt if necessary, into a separate validation-staging file.
        $validationPath = $this->databasePath.'.staging-validate';
        $this->deleteDatabaseFiles($validationPath);

        if ($this->encryption->isEncryptedBackup($uploadedTempPath)) {
            $this->encryption->decryptFile($uploadedTempPath, $validationPath, $passphrase);
        } else {
            if (! @copy($uploadedTempPath, $validationPath)) {
                throw new RuntimeException('Could not copy uploaded file for validation.');
            }
        }

        // 2. Sanity-check it is a real SQLite DB with at least the books table.
        try {
            $this->verifySqliteFile($validationPath);
        } catch (RuntimeException $e) {
            @unlink($validationPath);
            throw $e;
        }

        // 3. Stage the validated file, replacing any earlier staged import.
        if (! $this->moveDatabaseFiles($validationPath, $this->pendingImportPath())) {
            $this->deleteDatabaseFiles($validationPath);
            throw new RuntimeException('Could not stage imported database.');
        }

        $state = $this->readSidecar();
        $state['pending_import'] = true;
        $state['pending_revert'] = false;
        $this->writeSidecar($state);
    }

    /**
     * Mark a revert as pending. Verifies the rollback file exists before
     * setting the flag. A revert supersedes an import staged after the last
     * applied one: the rollback is what the user asked to go back to.
     */
    public function stageRevert(): void
    {
        if (! file_exists($this->rollbackPath())) {
            throw new RuntimeException('No rollback file to revert to.');
        }

        $state = $this->readSidecar();
        $state['pending_revert'] = true;
        $state['pending_import'] = false;
        $this->writeSidecar($state);

        $this->deleteDatabaseFiles($this->pendingImportPath());
    }

    /**
     * Apply any pending file-swap operation. MUST run before any process
     * opens the database file: the patched NativeServiceProvider calls it at
     * the start of the launch bootstrap commands (native:config and
     * native:php-ini), before the web server and queue worker exist.
     * Idempotent: if no flags are set or files are missing, returns silently.
     *
     * Those bootstrap commands run in parallel, so the swap holds an
     * exclusive lock and re-reads the flags under it: the first caller swaps,
     * the others wait and find nothing left to do.
     *
     * Best-effort: an exception here would fail a bootstrap command and stall
     * the launch, so failures are reported, never thrown. A swap that cannot
     * complete keeps its flag and is retried on the next launch.
     */
    public function applyPending(): void
    {
        // Hot path: the vast majority of launches have nothing to do. Skip
        // the lock + sidecar read entirely when there's no sidecar — that's
        // the signal "no backup operation has ever been started."
        if (! file_exists($this->sidecarPath())) {
            return;
        }

        try {
            $lock = $this->acquireLock();

            try {
                $state = $this->readSidecar();

                $this->pruneDiscardedFiles();

                if (! empty($state['pending_revert'])) {
                    $this->doRevert($state);

                    return;
                }

                if (! empty($state['pending_import'])) {
                    $this->doImport($state);
                }
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        } catch (\Throwable $e) {
            // The swap runs while the bootstrap command's providers are still
            // booting; Sentry's reportable is registered in
            // AppServiceProvider::boot(). booted() reports right away once the
            // app is up, and after every provider has booted otherwise.
            app()->booted(fn () => report($e));
        }
    }

    /**
     * Backup state used by the UI — read by the controller index() to decide
     * whether to show the Revert subsection. The pending flags come from the
     * sidecar; has_rollback comes from the rollback file itself.
     *
     * @return array{pending_import:bool, pending_revert:bool, has_rollback:bool}
     */
    public function state(): array
    {
        return [
            ...$this->readSidecar(),
            'has_rollback' => file_exists($this->rollbackPath()),
        ];
    }

    public function databasePath(): string
    {
        return $this->databasePath;
    }

    public function rollbackPath(): string
    {
        return $this->databasePath.self::ROLLBACK_SUFFIX;
    }

    public function pendingImportPath(): string
    {
        return $this->databasePath.self::PENDING_IMPORT_SUFFIX;
    }

    public function sidecarPath(): string
    {
        return dirname($this->databasePath).DIRECTORY_SEPARATOR.self::SIDECAR_FILE;
    }

    public function lockPath(): string
    {
        return dirname($this->databasePath).DIRECTORY_SEPARATOR.self::LOCK_FILE;
    }

    /**
     * @return array{pending_import:bool, pending_revert:bool}
     */
    private function readSidecar(): array
    {
        $default = [
            'pending_import' => false,
            'pending_revert' => false,
        ];

        $path = $this->sidecarPath();
        if (! file_exists($path)) {
            return $default;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return $default;
        }

        $decoded = json_decode($contents, true);
        if (! is_array($decoded)) {
            return $default;
        }

        return array_merge($default, array_intersect_key($decoded, $default));
    }

    /**
     * @param  array{pending_import:bool, pending_revert:bool}  $state
     */
    private function writeSidecar(array $state): void
    {
        $path = $this->sidecarPath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $written = @file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT));
        if ($written === false) {
            throw new RuntimeException("Could not write sidecar at {$path}.");
        }
    }

    /**
     * @param  array{pending_import:bool, pending_revert:bool}  $state
     */
    private function doImport(array $state): void
    {
        $pending = $this->pendingImportPath();
        if (! file_exists($pending)) {
            Log::warning('BackupService: pending_import flag set but staging file missing; clearing flag.', [
                'path' => $pending,
            ]);
            $state['pending_import'] = false;
            $this->writeSidecar($state);

            return;
        }

        $live = $this->databasePath;
        $rollback = $this->rollbackPath();
        $previousRollback = null;

        if (file_exists($live)) {
            if (! $this->releaseDatabase($live)) {
                Log::warning('BackupService: database still open in another process; the import stays pending.', [
                    'path' => $live,
                ]);

                return;
            }

            // Set the previous rollback aside rather than deleting it, so a
            // failed swap can put everything back.
            if (file_exists($rollback)) {
                $previousRollback = $this->discardedPath();

                if (! $this->moveDatabaseFiles($rollback, $previousRollback)) {
                    throw new RuntimeException('Could not set the previous rollback aside; the import stays pending.');
                }
            }

            if (! $this->moveDatabaseFiles($live, $rollback)) {
                if ($previousRollback !== null) {
                    $this->moveDatabaseFiles($previousRollback, $rollback);
                }

                throw new RuntimeException('Could not move the live database aside; the import stays pending.');
            }
        } else {
            // An older build's stageImport already moved the live DB aside.
            $this->adoptOrphanedSidecars($live, $rollback);
        }

        if (! $this->moveDatabaseFiles($pending, $live)) {
            // Put the user's data back where it was.
            if (! file_exists($live) && file_exists($rollback)) {
                $this->moveDatabaseFiles($rollback, $live);
            }

            if ($previousRollback !== null) {
                $this->moveDatabaseFiles($previousRollback, $rollback);
            }

            throw new RuntimeException('Could not move the imported database into place; the import stays pending.');
        }

        $state['pending_import'] = false;
        $this->writeSidecar($state);
    }

    /**
     * @param  array{pending_import:bool, pending_revert:bool}  $state
     */
    private function doRevert(array $state): void
    {
        $rollback = $this->rollbackPath();
        if (! file_exists($rollback)) {
            Log::warning('BackupService: pending_revert flag set but rollback file missing; clearing flag.', [
                'path' => $rollback,
            ]);
            $state['pending_revert'] = false;
            $this->writeSidecar($state);

            return;
        }

        $live = $this->databasePath;
        $discarded = null;

        // Move the current live DB aside as .discarded-<ts> — small safety
        // net in case the rollback turns out to be unwanted.
        if (file_exists($live)) {
            if (! $this->releaseDatabase($live)) {
                Log::warning('BackupService: database still open in another process; the revert stays pending.', [
                    'path' => $live,
                ]);

                return;
            }

            $discarded = $this->discardedPath();

            if (! $this->moveDatabaseFiles($live, $discarded)) {
                throw new RuntimeException('Could not move the live database aside; the revert stays pending.');
            }
        } else {
            $this->adoptOrphanedSidecars($live, $rollback);
        }

        if (! $this->moveDatabaseFiles($rollback, $live)) {
            if ($discarded !== null) {
                $this->moveDatabaseFiles($discarded, $live);
            }

            throw new RuntimeException('Could not move the rollback into place; the revert stays pending.');
        }

        $state['pending_revert'] = false;
        $this->writeSidecar($state);
    }

    /**
     * @return resource
     */
    private function acquireLock()
    {
        $lock = @fopen($this->lockPath(), 'c');
        if ($lock === false) {
            throw new RuntimeException("Could not open backup lock at {$this->lockPath()}.");
        }

        if (! flock($lock, LOCK_EX)) {
            fclose($lock);
            throw new RuntimeException("Could not lock {$this->lockPath()}.");
        }

        return $lock;
    }

    /**
     * Checkpoint the database and close it, folding committed WAL frames —
     * including those a killed process never checkpointed — into the main
     * file. Returns false while another process still has the database open:
     * the last connection to close deletes the -wal, so a -wal that outlives
     * ours belongs to a live process, which would later unlink it by name
     * from under whatever database then sits at this path.
     *
     * A file SQLite cannot checkpoint (e.g. a corrupt one) counts as released
     * and is moved together with its sidecars, which keeps it paired with
     * its WAL.
     */
    private function releaseDatabase(string $path): bool
    {
        try {
            // A peer holding a lock is still using the file: don't wait it out.
            $pdo = new PDO('sqlite:'.$path, options: [PDO::ATTR_TIMEOUT => 1]);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->query('PRAGMA wal_checkpoint(TRUNCATE)')?->fetchAll();
            $pdo = null;
        } catch (PDOException $e) {
            // SQLITE_BUSY / SQLITE_LOCKED: another process holds the file.
            if (in_array($e->errorInfo[1] ?? null, [5, 6], true)) {
                return false;
            }

            Log::warning('BackupService: could not checkpoint database; moving it with its WAL.', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return true;
        }

        clearstatcache(true, $path.'-wal');

        return ! file_exists($path.'-wal');
    }

    /**
     * Move a database file together with its SQLite sidecars, replacing the
     * database set at the destination. Returns false if the main file could
     * not be moved, in which case nothing was moved.
     */
    private function moveDatabaseFiles(string $from, string $to): bool
    {
        $this->deleteDatabaseFiles($to);

        if (! $this->renameFile($from, $to)) {
            return false;
        }

        foreach (self::SQLITE_SIDECAR_SUFFIXES as $suffix) {
            if (file_exists($from.$suffix) && ! $this->renameFile($from.$suffix, $to.$suffix)) {
                // Never leave a sidecar where the next database would adopt it.
                @unlink($from.$suffix);
            }
        }

        return true;
    }

    /**
     * Every rename the swap performs goes through here, so tests can
     * simulate one failing (a full disk, a file locked on Windows).
     */
    protected function renameFile(string $from, string $to): bool
    {
        return @rename($from, $to);
    }

    private function deleteDatabaseFiles(string $path): void
    {
        @unlink($path);

        foreach (self::SQLITE_SIDECAR_SUFFIXES as $suffix) {
            @unlink($path.$suffix);
        }
    }

    /**
     * Builds before the launch-time swap moved only the main file to the
     * rollback when staging an import, leaving its -wal/-shm at the live
     * path. Hand them back to the rollback they belong to, or drop them.
     */
    private function adoptOrphanedSidecars(string $live, string $rollback): void
    {
        foreach (self::SQLITE_SIDECAR_SUFFIXES as $suffix) {
            if (! file_exists($live.$suffix)) {
                continue;
            }

            $adoptable = file_exists($rollback) && ! file_exists($rollback.$suffix);

            if (! $adoptable || ! $this->renameFile($live.$suffix, $rollback.$suffix)) {
                @unlink($live.$suffix);
            }
        }
    }

    private function discardedPath(): string
    {
        return $this->databasePath.self::DISCARDED_PREFIX.time();
    }

    /**
     * Delete .discarded-<ts> copies, with their sidecars, once they are older
     * than DISCARDED_RETENTION_SECONDS. A younger copy belongs to this launch:
     * the other bootstrap command may have set it aside seconds ago.
     */
    private function pruneDiscardedFiles(): void
    {
        $dir = dirname($this->databasePath);
        $base = basename($this->databasePath).self::DISCARDED_PREFIX;
        $files = @glob($dir.DIRECTORY_SEPARATOR.$base.'*') ?: [];
        foreach ($files as $file) {
            $discardedAt = (int) substr(basename($file), strlen($base));

            if (time() - $discardedAt >= self::DISCARDED_RETENTION_SECONDS) {
                @unlink($file);
            }
        }
    }

    /**
     * Verify the file is a SQLite database that looks like ours: at least
     * the `books` table must exist. Migrations run on next boot, so we
     * don't check schema exhaustively — only reject obvious garbage.
     *
     * Uses PRAGMA quick_check rather than integrity_check: quick_check
     * skips the multi-second cross-table consistency walk and is what
     * SqliteVecConnector::connect() uses for the same "is this readable?"
     * question on every connection.
     */
    private function verifySqliteFile(string $path): void
    {
        try {
            $pdo = new PDO('sqlite:'.$path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $check = $pdo->query('PRAGMA quick_check;')?->fetchColumn();
            if ($check !== 'ok') {
                throw new RuntimeException("SQLite quick_check failed: {$check}");
            }

            $hasBooks = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='books'")?->fetchColumn();
            if (! $hasBooks) {
                throw new RuntimeException('File does not contain a Manuscript database (no books table).');
            }
        } catch (PDOException $e) {
            throw new RuntimeException('Uploaded file is not a valid SQLite database: '.$e->getMessage());
        }
    }
}
