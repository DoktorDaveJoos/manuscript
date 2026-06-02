<?php

use App\Services\BackupEncryptionService;
use App\Services\BackupService;
use App\Services\InvalidPassphraseOrCiphertextException;

/**
 * Build an isolated BackupService over a fresh temp DB. We populate the
 * minimum schema the verifySqliteFile() check requires (a `books` table)
 * so import validation succeeds without dragging in the real migrations.
 */
function makeIsolatedBackupService(): array
{
    $workDir = sys_get_temp_dir().'/manuscript-backup-svc-'.uniqid();
    mkdir($workDir);
    $dbPath = $workDir.'/live.sqlite';

    $pdo = new PDO('sqlite:'.$dbPath);
    $pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY, title TEXT)');
    $pdo->exec("INSERT INTO books (title) VALUES ('original')");
    $pdo = null;

    $service = new BackupService(new BackupEncryptionService, $dbPath);

    return [$service, $workDir, $dbPath];
}

afterEach(function () {
    if (! isset($this->workDir)) {
        return;
    }
    foreach (glob($this->workDir.'/*') ?: [] as $f) {
        @unlink($f);
    }
    @unlink($this->workDir.'/.backup-state.json');
    @rmdir($this->workDir);
});

test('export writes a sqlite snapshot containing the live data when no passphrase is given', function () {
    [$service, $workDir, $dbPath] = makeIsolatedBackupService();
    $this->workDir = $workDir;

    $exportPath = $service->export('');

    expect(file_exists($exportPath))->toBeTrue();
    expect(str_ends_with($exportPath, '.sqlite'))->toBeTrue();

    $pdo = new PDO('sqlite:'.$exportPath);
    expect($pdo->query('SELECT title FROM books')->fetchColumn())->toBe('original');

    @unlink($exportPath);
});

test('export with passphrase produces an MSBK file the encryption service can decrypt back to the live data', function () {
    [$service, $workDir, $dbPath] = makeIsolatedBackupService();
    $this->workDir = $workDir;

    $exportPath = $service->export('strong-passphrase');
    expect(str_ends_with($exportPath, '.msbk'))->toBeTrue();

    $encryption = new BackupEncryptionService;
    expect($encryption->isEncryptedBackup($exportPath))->toBeTrue();

    $roundTripPath = $workDir.'/round.sqlite';
    $encryption->decryptFile($exportPath, $roundTripPath, 'strong-passphrase');

    $pdo = new PDO('sqlite:'.$roundTripPath);
    expect($pdo->query('SELECT title FROM books')->fetchColumn())->toBe('original');

    @unlink($exportPath);
});

test('export captures rows still sitting in the WAL sidecar (the bug we shipped: raw copy missed them)', function () {
    $workDir = sys_get_temp_dir().'/manuscript-backup-svc-'.uniqid();
    mkdir($workDir);
    $dbPath = $workDir.'/live.sqlite';
    $this->workDir = $workDir;

    // Build a live DB in WAL mode and write rows WITHOUT checkpointing —
    // mirrors the production state where SqliteVecConnector enables WAL
    // and the sqlite-wal sidecar holds recent writes.
    $live = new PDO('sqlite:'.$dbPath);
    $live->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $live->exec('PRAGMA journal_mode = WAL');
    $live->exec('CREATE TABLE books (id INTEGER PRIMARY KEY, title TEXT)');
    $live->exec("INSERT INTO books (title) VALUES ('wal-resident-row')");

    expect(file_exists($dbPath.'-wal'))->toBeTrue('precondition: a -wal file must exist for this test to be meaningful');

    // Hold the connection open during export — represents the running app
    // having open writers when the user clicks "Export."
    $service = new BackupService(new BackupEncryptionService, $dbPath);
    $exportPath = $service->export('');

    $live = null;

    $pdo = new PDO('sqlite:'.$exportPath);
    expect($pdo->query('SELECT title FROM books')->fetchColumn())->toBe('wal-resident-row');

    @unlink($exportPath);
});

test('stageImport renames live DB to rollback and stages pending import', function () {
    [$service, $workDir, $dbPath] = makeIsolatedBackupService();
    $this->workDir = $workDir;

    // Build a different valid SQLite file to import.
    $importSource = $workDir.'/incoming.sqlite';
    $pdo = new PDO('sqlite:'.$importSource);
    $pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY, title TEXT)');
    $pdo->exec("INSERT INTO books (title) VALUES ('imported')");
    $pdo = null;

    $service->stageImport($importSource);

    expect(file_exists($service->rollbackPath()))->toBeTrue();
    expect(file_exists($service->pendingImportPath()))->toBeTrue();
    expect(file_exists($dbPath))->toBeFalse();

    $state = $service->state();
    expect($state['has_rollback'])->toBeTrue();
    expect($state['pending_import'])->toBeTrue();
});

test('stageImport rejects a non-sqlite file without disturbing the live DB', function () {
    [$service, $workDir, $dbPath] = makeIsolatedBackupService();
    $this->workDir = $workDir;

    $junk = $workDir.'/junk.bin';
    file_put_contents($junk, str_repeat('garbage', 200));

    expect(fn () => $service->stageImport($junk))->toThrow(RuntimeException::class);

    expect(file_exists($dbPath))->toBeTrue();
    expect(file_exists($service->rollbackPath()))->toBeFalse();
});

test('stageImport rejects wrong passphrase without disturbing the live DB', function () {
    [$service, $workDir, $dbPath] = makeIsolatedBackupService();
    $this->workDir = $workDir;

    $importSource = $workDir.'/incoming.sqlite';
    $pdo = new PDO('sqlite:'.$importSource);
    $pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY, title TEXT)');
    $pdo = null;

    $encryptedPath = $workDir.'/incoming.msbk';
    (new BackupEncryptionService)->encryptFile($importSource, $encryptedPath, 'right');

    expect(fn () => $service->stageImport($encryptedPath, 'wrong'))
        ->toThrow(InvalidPassphraseOrCiphertextException::class);

    expect(file_exists($dbPath))->toBeTrue();
    expect(file_exists($service->rollbackPath()))->toBeFalse();
});

test('applyPending swaps pending-import file into place', function () {
    [$service, $workDir, $dbPath] = makeIsolatedBackupService();
    $this->workDir = $workDir;

    // Build an importable file and stage it.
    $importSource = $workDir.'/incoming.sqlite';
    $pdo = new PDO('sqlite:'.$importSource);
    $pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY, title TEXT)');
    $pdo->exec("INSERT INTO books (title) VALUES ('imported')");
    $pdo = null;

    $service->stageImport($importSource);

    // Simulate the next-boot apply.
    $service->applyPending();

    expect(file_exists($dbPath))->toBeTrue();
    expect(file_exists($service->pendingImportPath()))->toBeFalse();

    $pdo = new PDO('sqlite:'.$dbPath);
    $row = $pdo->query('SELECT title FROM books')->fetchColumn();
    expect($row)->toBe('imported');

    // After import, rollback file should still exist (until reverted).
    expect(file_exists($service->rollbackPath()))->toBeTrue();
});

test('stageRevert + applyPending restores the rollback file as live DB', function () {
    [$service, $workDir, $dbPath] = makeIsolatedBackupService();
    $this->workDir = $workDir;

    // Stage and apply an import first.
    $importSource = $workDir.'/incoming.sqlite';
    $pdo = new PDO('sqlite:'.$importSource);
    $pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY, title TEXT)');
    $pdo->exec("INSERT INTO books (title) VALUES ('imported')");
    $pdo = null;
    $service->stageImport($importSource);
    $service->applyPending();

    // Now revert.
    $service->stageRevert();
    $service->applyPending();

    expect(file_exists($dbPath))->toBeTrue();
    expect(file_exists($service->rollbackPath()))->toBeFalse();

    $pdo = new PDO('sqlite:'.$dbPath);
    $row = $pdo->query('SELECT title FROM books')->fetchColumn();
    expect($row)->toBe('original');

    expect($service->state()['has_rollback'])->toBeFalse();
});

test('stageRevert without a rollback file throws', function () {
    [$service, $workDir] = makeIsolatedBackupService();
    $this->workDir = $workDir;

    expect(fn () => $service->stageRevert())->toThrow(RuntimeException::class);
});

test('state self-heals when has_rollback flag is set but file is missing', function () {
    [$service, $workDir] = makeIsolatedBackupService();
    $this->workDir = $workDir;

    file_put_contents(
        $service->sidecarPath(),
        json_encode([
            'pending_import' => false,
            'pending_revert' => false,
            'has_rollback' => true,
        ]),
    );

    $state = $service->state();
    expect($state['has_rollback'])->toBeFalse();
});

test('the BackupService singleton tracks database.default, not the sqlite connection (the bug we shipped: empty seed DB exported in NativePHP)', function () {
    $originalDefault = config('database.default');
    $originalSqliteDb = config('database.connections.sqlite.database');

    try {
        config()->set('database.default', 'fake-runtime');
        config()->set('database.connections.fake-runtime.database', '/tmp/should-be-this-one.sqlite');
        config()->set('database.connections.sqlite.database', '/tmp/seed-must-not-be-this-one.sqlite');

        app()->forgetInstance(BackupService::class);
        $service = app(BackupService::class);

        expect($service->databasePath())->toBe('/tmp/should-be-this-one.sqlite');
    } finally {
        config()->set('database.default', $originalDefault);
        config()->set('database.connections.sqlite.database', $originalSqliteDb);
        config()->offsetUnset('database.connections.fake-runtime');
        app()->forgetInstance(BackupService::class);
    }
});

test('applyPending is a no-op when no flags are set', function () {
    [$service, $workDir, $dbPath] = makeIsolatedBackupService();
    $this->workDir = $workDir;

    $original = file_get_contents($dbPath);
    $service->applyPending();
    expect(file_get_contents($dbPath))->toBe($original);
});
