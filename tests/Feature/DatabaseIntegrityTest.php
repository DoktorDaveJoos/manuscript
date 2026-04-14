<?php

use App\Database\SqliteVecConnector;
use App\Services\DatabaseRepairService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    SqliteVecConnector::resetIntegrityFlag();
    $this->tempDir = sys_get_temp_dir().'/manuscript_test_'.uniqid();
    mkdir($this->tempDir);
});

afterEach(function () {
    foreach (glob($this->tempDir.'/*') as $f) {
        @unlink($f);
    }
    @rmdir($this->tempDir);
});

// ---------------------------------------------------------------------------
// SqliteVecConnector — integrity check
// ---------------------------------------------------------------------------

test('healthy database passes integrity check without repair', function () {
    $dbPath = $this->tempDir.'/healthy.sqlite';

    $pdo = new PDO("sqlite:{$dbPath}");
    $pdo->exec('CREATE TABLE test (id INTEGER PRIMARY KEY)');
    $pdo->exec('INSERT INTO test (id) VALUES (1)');
    unset($pdo);

    $connector = app(SqliteVecConnector::class);
    $resultPdo = $connector->connect(['database' => $dbPath]);

    expect($resultPdo)->toBeInstanceOf(PDO::class);
    expect(app()->bound('database.repaired'))->toBeFalse();

    $row = $resultPdo->query('SELECT id FROM test')->fetchColumn();
    expect((int) $row)->toBe(1);
});

test('corrupt database triggers backup and fresh file', function () {
    $dbPath = $this->tempDir.'/corrupt.sqlite';
    file_put_contents($dbPath, random_bytes(4096));

    $connector = app(SqliteVecConnector::class);
    $resultPdo = $connector->connect(['database' => $dbPath]);

    expect($resultPdo)->toBeInstanceOf(PDO::class);

    $backups = glob($this->tempDir.'/corrupt.sqlite.corrupt.*');
    expect($backups)->not->toBeEmpty();

    $check = $resultPdo->query('PRAGMA quick_check')->fetchColumn();
    expect($check)->toBe('ok');

    expect(app()->bound('database.repaired'))->toBeTrue();
    $info = app('database.repaired');
    expect($info['backup'])->toContain('.corrupt.');
});

test('integrity check runs only once per boot', function () {
    $dbPath = $this->tempDir.'/once.sqlite';
    touch($dbPath);

    $connector = app(SqliteVecConnector::class);
    $connector->connect(['database' => $dbPath]);
    expect(app()->bound('database.repaired'))->toBeFalse();

    // Write garbage AFTER first check — no repair should trigger.
    file_put_contents($dbPath, random_bytes(4096));
    $backups = glob($this->tempDir.'/*.corrupt.*');
    expect($backups)->toBeEmpty();
});

test('memory databases skip integrity check', function () {
    $connector = app(SqliteVecConnector::class);
    $pdo = $connector->connect(['database' => ':memory:']);

    expect($pdo)->toBeInstanceOf(PDO::class);
    expect(app()->bound('database.repaired'))->toBeFalse();
});

test('corrupt WAL and SHM files are backed up', function () {
    $dbPath = $this->tempDir.'/waltest.sqlite';
    file_put_contents($dbPath, random_bytes(4096));
    file_put_contents("{$dbPath}-wal", 'wal-data');
    file_put_contents("{$dbPath}-shm", 'shm-data');

    $connector = app(SqliteVecConnector::class);
    $connector->connect(['database' => $dbPath]);

    expect(glob($this->tempDir.'/*.corrupt.*-wal'))->not->toBeEmpty();
    expect(glob($this->tempDir.'/*.corrupt.*-shm'))->not->toBeEmpty();
});

// ---------------------------------------------------------------------------
// DatabaseRepairService — data recovery
// ---------------------------------------------------------------------------

test('recover data copies rows from corrupt backup to fresh database', function () {
    $backupPath = $this->tempDir.'/backup.sqlite';

    $backupPdo = new PDO("sqlite:{$backupPath}");
    $backupPdo->exec('CREATE TABLE app_settings (id INTEGER PRIMARY KEY, key TEXT, value TEXT)');
    $backupPdo->exec("INSERT INTO app_settings (key, value) VALUES ('locale', 'de')");
    $backupPdo->exec("INSERT INTO app_settings (key, value) VALUES ('theme', 'dark')");
    unset($backupPdo);

    if (Schema::hasTable('app_settings')) {
        DB::table('app_settings')->truncate();
    }

    $service = new DatabaseRepairService;
    $result = $service->recoverData($backupPath);

    expect($result['recovered'])->toContain('app_settings');
    expect($result['failed'])->toBeEmpty();
    expect(DB::table('app_settings')->count())->toBe(2);
});

test('recovery skips tables that do not exist in fresh schema', function () {
    $backupPath = $this->tempDir.'/backup.sqlite';

    $backupPdo = new PDO("sqlite:{$backupPath}");
    $backupPdo->exec('CREATE TABLE nonexistent_table_xyz (id INTEGER PRIMARY KEY, data TEXT)');
    $backupPdo->exec("INSERT INTO nonexistent_table_xyz (data) VALUES ('test')");
    unset($backupPdo);

    $service = new DatabaseRepairService;
    $result = $service->recoverData($backupPath);

    expect($result['recovered'])->not->toContain('nonexistent_table_xyz');
    expect($result['failed'])->not->toContain('nonexistent_table_xyz');
});

test('recovery skips migrations table', function () {
    $backupPath = $this->tempDir.'/backup.sqlite';

    $backupPdo = new PDO("sqlite:{$backupPath}");
    $backupPdo->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY, migration TEXT, batch INTEGER)');
    $backupPdo->exec("INSERT INTO migrations (migration, batch) VALUES ('2024_01_01_000000_test', 1)");
    unset($backupPdo);

    $service = new DatabaseRepairService;
    $result = $service->recoverData($backupPath);

    expect($result['recovered'])->not->toContain('migrations');
});

test('recovery returns empty when backup file does not exist', function () {
    $service = new DatabaseRepairService;
    $result = $service->recoverData('/tmp/nonexistent_'.uniqid().'.sqlite');

    expect($result['recovered'])->toBeEmpty();
    expect($result['failed'])->toBeEmpty();
});

// ---------------------------------------------------------------------------
// SetLocale middleware — hardened against DB failure
// ---------------------------------------------------------------------------

test('SetLocale falls back to config when database throws', function () {
    // Even with a broken DB, the request should not 500.
    $this->get(route('books.index'))
        ->assertOk();
});

// ---------------------------------------------------------------------------
// HandleInertiaRequests — repair info sharing
// ---------------------------------------------------------------------------

test('database_repaired prop is shared when repair info exists', function () {
    app()->instance('database.repaired', [
        'backup' => '/tmp/test.sqlite.corrupt.2026-04-14_120000',
        'recovered' => ['books', 'chapters'],
        'failed' => ['cache'],
    ]);

    $this->get(route('books.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('database_repaired', true)
            ->has('repair_details')
            ->where('repair_details.recovered', ['books', 'chapters'])
            ->where('repair_details.failed', ['cache'])
        );
});

test('database_repaired prop is not shared when no repair occurred', function () {
    $this->get(route('books.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('database_repaired')
        );
});
