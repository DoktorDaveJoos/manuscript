<?php

namespace App\Services;

use App\Models\AppSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Owns the local backup workflow: export the live SQLite DB, stage an import
 * (with rollback creation), stage a revert, and apply pending swaps at boot.
 *
 * The "pending swap" state lives in a JSON sidecar next to the DB file —
 * NOT in the DB itself. Reading the DB to decide whether to replace the DB
 * is a chicken-and-egg problem.
 */
class BackupService
{
    public const SIDECAR_FILE = '.backup-state.json';

    public const ROLLBACK_SUFFIX = '.rollback';

    public const PENDING_IMPORT_SUFFIX = '.pending-import';

    public const DISCARDED_PREFIX = '.discarded-';

    public function __construct(
        private BackupEncryptionService $encryption,
        private string $databasePath,
    ) {}

    /**
     * Export the live database to a freshly-created temp file. Returns the
     * temp path. Caller is responsible for sending it as a download and
     * deleting it afterwards (BinaryFileResponse with deleteFileAfterSend).
     *
     * Empty $passphrase → raw SQLite copy. Non-empty → MSBK envelope.
     */
    public function export(string $passphrase = ''): string
    {
        $live = $this->databasePath;
        if (! file_exists($live)) {
            throw new RuntimeException("Database file not found at {$live}.");
        }

        $tempDir = sys_get_temp_dir();
        $suffix = $passphrase === '' ? '.sqlite' : '.msbk';
        $tempPath = tempnam($tempDir, 'manuscript-backup-');
        if ($tempPath === false) {
            throw new RuntimeException('Could not create temp file for backup export.');
        }
        $tempPathWithSuffix = $tempPath.$suffix;
        // tempnam creates the file; rename to add a meaningful extension so
        // the Content-Disposition filename can drive correct OS handling.
        if (! @rename($tempPath, $tempPathWithSuffix)) {
            @unlink($tempPath);
            throw new RuntimeException('Could not rename temp file for backup export.');
        }

        if ($passphrase === '') {
            if (! @copy($live, $tempPathWithSuffix)) {
                @unlink($tempPathWithSuffix);
                throw new RuntimeException('Could not copy database for export.');
            }
        } else {
            $this->encryption->encryptFile($live, $tempPathWithSuffix, $passphrase);
        }

        AppSetting::set('backup.last_export_at', CarbonImmutable::now()->toIso8601String());

        return $tempPathWithSuffix;
    }

    /**
     * Validate the uploaded file, then atomically:
     *   1. rename live DB → .rollback (overwriting any previous rollback)
     *   2. move validated file → .pending-import
     *   3. flip sidecar flags
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
        @unlink($validationPath);

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
        } catch (\Throwable $e) {
            @unlink($validationPath);
            throw $e;
        }

        // 3. Atomic-ish swap: move live → rollback, validated → pending-import.
        $rollbackPath = $this->rollbackPath();
        $pendingPath = $this->pendingImportPath();

        if (file_exists($rollbackPath)) {
            @unlink($rollbackPath);
        }
        if (file_exists($pendingPath)) {
            @unlink($pendingPath);
        }

        if (! @rename($this->databasePath, $rollbackPath)) {
            @unlink($validationPath);
            throw new RuntimeException('Could not move live database aside.');
        }

        if (! @rename($validationPath, $pendingPath)) {
            // Try to put the live DB back so the user is not stranded.
            @rename($rollbackPath, $this->databasePath);
            throw new RuntimeException('Could not stage imported database.');
        }

        $this->writeSidecar([
            'pending_import' => true,
            'pending_revert' => false,
            'has_rollback' => true,
        ]);
    }

    /**
     * Mark a revert as pending. Verifies the rollback file exists before
     * setting the flag.
     */
    public function stageRevert(): void
    {
        if (! file_exists($this->rollbackPath())) {
            throw new RuntimeException('No rollback file to revert to.');
        }

        $state = $this->readSidecar();
        $state['pending_revert'] = true;
        $this->writeSidecar($state);
    }

    /**
     * Apply any pending file-swap operation. MUST be called before Laravel
     * opens any connection to the database file. Idempotent: if no flags
     * are set or files are missing, returns silently.
     */
    public function applyPending(): void
    {
        $state = $this->readSidecar();

        // Prune any leftover .discarded-* files from a previous boot.
        $this->pruneDiscardedFiles();

        if (! empty($state['pending_revert'])) {
            $this->doRevert($state);

            return;
        }

        if (! empty($state['pending_import'])) {
            $this->doImport($state);
        }
    }

    /**
     * Sidecar shape used by the UI. The sidecar file is the single source
     * of truth — read by the controller index() to decide whether to show
     * the Revert subsection.
     *
     * @return array{pending_import:bool, pending_revert:bool, has_rollback:bool}
     */
    public function state(): array
    {
        $state = $this->readSidecar();

        // Self-heal: if has_rollback is set but the rollback file is gone,
        // clear the flag so the UI doesn't promise an action it can't do.
        if (! empty($state['has_rollback']) && ! file_exists($this->rollbackPath())) {
            $state['has_rollback'] = false;
            $this->writeSidecar($state);
        }

        return $state;
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

    /**
     * @return array{pending_import:bool, pending_revert:bool, has_rollback:bool}
     */
    private function readSidecar(): array
    {
        $default = [
            'pending_import' => false,
            'pending_revert' => false,
            'has_rollback' => false,
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
     * @param  array{pending_import:bool, pending_revert:bool, has_rollback:bool}  $state
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

        if (file_exists($this->databasePath)) {
            // Should not happen — stageImport renamed the live DB to .rollback
            // before flipping the flag — but guard anyway.
            Log::warning('BackupService: live DB still exists at apply-pending time; replacing.', [
                'path' => $this->databasePath,
            ]);
            @unlink($this->databasePath);
        }

        if (! @rename($pending, $this->databasePath)) {
            Log::error('BackupService: could not move pending-import into place; clearing flag.', [
                'pending' => $pending,
                'live' => $this->databasePath,
            ]);
            $state['pending_import'] = false;
            $this->writeSidecar($state);

            return;
        }

        $state['pending_import'] = false;
        $this->writeSidecar($state);
    }

    private function doRevert(array $state): void
    {
        $rollback = $this->rollbackPath();
        if (! file_exists($rollback)) {
            Log::warning('BackupService: pending_revert flag set but rollback file missing; clearing flags.', [
                'path' => $rollback,
            ]);
            $state['pending_revert'] = false;
            $state['has_rollback'] = false;
            $this->writeSidecar($state);

            return;
        }

        // Move the current live DB aside as .discarded-<ts> — small safety
        // net in case the rollback turns out to be unwanted. Pruned next boot.
        if (file_exists($this->databasePath)) {
            $discarded = $this->databasePath.self::DISCARDED_PREFIX.time();
            @rename($this->databasePath, $discarded);
        }

        if (! @rename($rollback, $this->databasePath)) {
            Log::error('BackupService: could not move rollback into place.', [
                'rollback' => $rollback,
                'live' => $this->databasePath,
            ]);
            $state['pending_revert'] = false;
            $this->writeSidecar($state);

            return;
        }

        $state['pending_revert'] = false;
        $state['has_rollback'] = false;
        $this->writeSidecar($state);
    }

    private function pruneDiscardedFiles(): void
    {
        $dir = dirname($this->databasePath);
        $base = basename($this->databasePath).self::DISCARDED_PREFIX;
        $files = @glob($dir.DIRECTORY_SEPARATOR.$base.'*') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    /**
     * Verify the file is a SQLite database that looks like ours: at least
     * the `books` table must exist. We don't check schema correctness
     * exhaustively — migrations will run on next boot — but we do reject
     * obvious garbage.
     */
    private function verifySqliteFile(string $path): void
    {
        try {
            $pdo = new PDO('sqlite:'.$path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $integrity = $pdo->query('PRAGMA integrity_check;')?->fetchColumn();
            if ($integrity !== 'ok') {
                throw new RuntimeException("SQLite integrity_check failed: {$integrity}");
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
