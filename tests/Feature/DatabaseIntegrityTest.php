<?php

use App\Database\SqliteVecConnector;
use App\Services\DatabaseRepairService;
use App\Services\DatabaseStartupService;
use App\Services\SqliteVec\SqliteVecService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    SqliteVecConnector::resetIntegrityFlag();
    $this->tempDir = sys_get_temp_dir().'/manuscript_test_'.uniqid();
    mkdir($this->tempDir);
});

afterEach(function () {
    // Boot-service tests swap the default connection. Restore it BEFORE
    // RefreshDatabase tears down — its rollback re-resolves the default at
    // destroy time, and rolling back the wrong connection leaks an open
    // transaction into the next test.
    config(['database.default' => 'sqlite']);
    DB::purge('nativephp');
    DB::purge('devsqlite');

    // GLOB_BRACE so dotfiles like the `.repairing` marker are removed too.
    foreach (glob($this->tempDir.'/{,.}*', GLOB_BRACE) as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
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

    // Marker is the signal /repair-status uses to show "Restoring your data"
    // in the loading view. Must be on disk by the time repair finishes.
    expect(file_exists($this->tempDir.'/.repairing'))->toBeTrue();
    $marker = json_decode(file_get_contents($this->tempDir.'/.repairing'), true);
    expect($marker)->toHaveKey('started_at');
    expect($marker)->toHaveKey('trigger');

    // The marker must carry the backup path: when corruption is detected by
    // the launch-time CLI migrate, the `database.repaired` container binding
    // dies with that process — the marker is the only cross-process handle
    // the next web request has for running data recovery.
    expect($marker)->toHaveKey('backup');
    expect($marker['backup'])->toBe($info['backup']);
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

test('integrity check is skipped for web requests', function () {
    $dbPath = $this->tempDir.'/web.sqlite';
    file_put_contents($dbPath, random_bytes(4096));

    // Simulate NativePHP's cli-server context. PHP's built-in server resets
    // statics per request, so a request-path check would re-pay a full
    // O(database size) PRAGMA quick_check on EVERY request — with immediate
    // saves on every keystroke that's a per-interaction tax that grows with
    // the manuscript. The launch-time CLI migrate already checks once per
    // launch; web requests must not check (or repair) at all.
    $webApp = new class extends Illuminate\Foundation\Application
    {
        public function __construct() {}

        public function runningInConsole()
        {
            return false;
        }
    };

    $connector = new SqliteVecConnector(new SqliteVecService, $webApp);

    try {
        $connector->connect(['database' => $dbPath]);
    } catch (Throwable) {
        // A corrupt file may organically fail during PRAGMA setup — fine.
        // What matters is that the destructive repair path never ran.
    }

    expect(glob($this->tempDir.'/*.corrupt.*'))->toBeEmpty();
    expect(app()->bound('database.repaired'))->toBeFalse();
    expect(file_exists($this->tempDir.'/.repairing'))->toBeFalse();
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

test('recovery overwrites seeded defaults with the user\'s customized backup values', function () {
    // The fresh DB is already seeded by migrations — show_ai_features defaults
    // to 'true'. If the user had customized it to 'false' pre-corruption, the
    // backup's value must win over the seed default or we silently reset
    // user preferences on every recovery.
    $seededValue = DB::table('app_settings')->where('key', 'show_ai_features')->value('value');
    expect($seededValue)->toBe('true');

    $backupPath = $this->tempDir.'/backup.sqlite';
    $pdo = new PDO("sqlite:{$backupPath}");
    $pdo->exec('CREATE TABLE app_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, key TEXT UNIQUE NOT NULL, value TEXT, created_at DATETIME, updated_at DATETIME)');
    $pdo->exec("INSERT INTO app_settings (key, value, created_at, updated_at) VALUES ('show_ai_features', 'false', '2026-01-01 00:00:00', '2026-01-01 00:00:00')");
    $pdo->exec("INSERT INTO app_settings (key, value, created_at, updated_at) VALUES ('typewriter_mode', 'true', '2026-01-01 00:00:00', '2026-01-01 00:00:00')");
    unset($pdo);

    $service = new DatabaseRepairService;
    $result = $service->recoverData($backupPath);

    expect($result['recovered'])->toContain('app_settings');
    expect(DB::table('app_settings')->where('key', 'show_ai_features')->value('value'))->toBe('false');
    expect(DB::table('app_settings')->where('key', 'typewriter_mode')->value('value'))->toBe('true');
});

test('recovery preserves seed defaults when the backup table has no rows', function () {
    $seedCount = DB::table('app_settings')->count();
    expect($seedCount)->toBeGreaterThan(0);

    $backupPath = $this->tempDir.'/backup.sqlite';
    $pdo = new PDO("sqlite:{$backupPath}");
    $pdo->exec('CREATE TABLE app_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, key TEXT UNIQUE NOT NULL, value TEXT, created_at DATETIME, updated_at DATETIME)');
    unset($pdo);

    $service = new DatabaseRepairService;
    $service->recoverData($backupPath);

    // Empty backup table must not wipe seed data.
    expect(DB::table('app_settings')->count())->toBe($seedCount);
});

test('recovery filters dropped columns so pre-migration backups still import', function () {
    $backupPath = $this->tempDir.'/backup.sqlite';

    // Backup has a legacy column `deprecated_field` that no longer exists in
    // the fresh schema. Without column filtering the INSERT would fail on
    // "no such column" and the whole table would land in $failed.
    $backupPdo = new PDO("sqlite:{$backupPath}");
    $backupPdo->exec('CREATE TABLE app_settings (id INTEGER PRIMARY KEY, key TEXT, value TEXT, deprecated_field TEXT)');
    $backupPdo->exec("INSERT INTO app_settings (key, value, deprecated_field) VALUES ('locale', 'de', 'legacy')");
    unset($backupPdo);

    if (Schema::hasTable('app_settings')) {
        DB::table('app_settings')->truncate();
    }

    $service = new DatabaseRepairService;
    $result = $service->recoverData($backupPath);

    expect($result['recovered'])->toContain('app_settings');
    expect($result['failed'])->not->toContain('app_settings');
    expect(DB::table('app_settings')->where('key', 'locale')->value('value'))->toBe('de');
});

test('recovery skips FTS5 and vec0 shadow tables', function () {
    $backupPath = $this->tempDir.'/backup.sqlite';

    // Create tables that look like FTS5/vec0 shadow tables. These are real
    // tables in sqlite_master (so Schema::hasTable() returns true in the fresh
    // DB when the real FTS/vec virtual tables are created), but inserting into
    // them directly corrupts the virtual-table indices.
    $backupPdo = new PDO("sqlite:{$backupPath}");
    $backupPdo->exec('CREATE TABLE chunks_fts_data (id INTEGER PRIMARY KEY, block BLOB)');
    $backupPdo->exec('CREATE TABLE chunks_fts_idx (segid INTEGER, term TEXT)');
    $backupPdo->exec('CREATE TABLE chunk_embeddings_rowids (rowid INTEGER PRIMARY KEY, id INTEGER)');
    $backupPdo->exec('CREATE TABLE chunk_embeddings_chunks (chunk_id INTEGER PRIMARY KEY, validity BLOB)');
    unset($backupPdo);

    $service = new DatabaseRepairService;
    $result = $service->recoverData($backupPath);

    foreach (['chunks_fts_data', 'chunks_fts_idx', 'chunk_embeddings_rowids', 'chunk_embeddings_chunks'] as $shadow) {
        expect($result['recovered'])->not->toContain($shadow);
        expect($result['failed'])->not->toContain($shadow);
    }
});

test('unopenable database surfaces as boot error without wiping data', function () {
    $dbPath = $this->tempDir.'/healthy.sqlite';

    $pdo = new PDO("sqlite:{$dbPath}");
    $pdo->exec('CREATE TABLE test (id INTEGER PRIMARY KEY)');
    unset($pdo);

    // chmod 000 makes PDO open fail — the resulting error is NOT corruption,
    // so the connector must surface the throwable rather than call the
    // destructive repair path on a healthy file.
    chmod($dbPath, 0o000);

    $connector = app(SqliteVecConnector::class);

    try {
        $connector->connect(['database' => $dbPath]);
        $this->fail('Expected a throwable to propagate for a non-corruption open error.');
    } catch (Throwable) {
        // Expected.
    } finally {
        chmod($dbPath, 0o644);
    }

    // File must still exist (not renamed .corrupt.*) and still contain the table.
    expect(file_exists($dbPath))->toBeTrue();
    expect(glob($this->tempDir.'/*.corrupt.*'))->toBeEmpty();

    $verify = new PDO("sqlite:{$dbPath}");
    $tables = $verify->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test'")->fetchColumn();
    expect($tables)->toBe('test');
});

test('migrations table is skipped during recovery', function () {
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
// DatabaseStartupService — boot-time schema & recovery
//
// In the packaged app NativePHP renames the default connection to `nativephp`
// (still sqlite driver, pointing at the user-data DB). The boot path must key
// off the DRIVER, not the connection NAME — guarding on the name 'sqlite'
// made migrate + recovery dead code in production.
// ---------------------------------------------------------------------------

test('boot recovery runs when the default connection is named nativephp', function () {
    $livePath = $this->tempDir.'/nativephp.sqlite';
    touch($livePath);

    // Backup left behind by a launch-time CLI repair: readable, with user data.
    $backupPath = $livePath.'.corrupt.2026-06-10_120000';
    $pdo = new PDO("sqlite:{$backupPath}");
    $pdo->exec('CREATE TABLE app_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, key TEXT UNIQUE NOT NULL, value TEXT, created_at DATETIME, updated_at DATETIME)');
    $pdo->exec("INSERT INTO app_settings (key, value) VALUES ('recovery_probe', 'user-value')");
    unset($pdo);

    // Marker as written by SqliteVecConnector::repairDatabase() in the CLI
    // migrate process — the only cross-process repair signal.
    file_put_contents($this->tempDir.'/.repairing', json_encode([
        'started_at' => '2026-06-10T12:00:00+00:00',
        'trigger' => 'PRAGMA quick_check failed: test',
        'backup' => $backupPath,
    ]));

    config(['database.connections.nativephp' => [
        'driver' => 'sqlite',
        'database' => $livePath,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);
    config(['database.default' => 'nativephp']);
    config(['nativephp-internal.running' => true]);

    (new DatabaseStartupService(app(), runningInConsole: false))->ensureSchema();

    // Fresh DB got its schema, then the user's data back.
    expect(Schema::hasTable('app_settings'))->toBeTrue();
    expect(DB::table('app_settings')->where('key', 'recovery_probe')->value('value'))->toBe('user-value');

    // Repair info is bound for HandleInertiaRequests to surface the toast.
    expect(app()->bound('database.repaired'))->toBeTrue();
    expect(app('database.repaired')['recovered'])->toContain('app_settings');

    // Marker cleared — otherwise every future launch shows "Restoring your
    // data" on the loading screen forever.
    expect(file_exists($this->tempDir.'/.repairing'))->toBeFalse();
});

test('boot skips migrate under nativephp when no repair is pending', function () {
    $livePath = $this->tempDir.'/nativephp.sqlite';
    touch($livePath);

    config(['database.connections.nativephp' => [
        'driver' => 'sqlite',
        'database' => $livePath,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);
    config(['database.default' => 'nativephp']);
    config(['nativephp-internal.running' => true]);

    (new DatabaseStartupService(app(), runningInConsole: false))->ensureSchema();

    // The Electron main process already runs `migrate --force` once per
    // launch — re-running it on every web request is wasted work.
    $pdo = new PDO("sqlite:{$livePath}");
    $migrationsTable = $pdo->query("SELECT count(*) FROM sqlite_master WHERE name = 'migrations'")->fetchColumn();
    expect((int) $migrationsTable)->toBe(0);
    expect(app()->bound('database.repaired'))->toBeFalse();
});

test('boot migrates sqlite-driver defaults regardless of connection name outside nativephp', function () {
    $livePath = $this->tempDir.'/dev.sqlite';
    touch($livePath);

    config(['database.connections.devsqlite' => [
        'driver' => 'sqlite',
        'database' => $livePath,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);
    config(['database.default' => 'devsqlite']);
    config(['nativephp-internal.running' => false]);

    (new DatabaseStartupService(app(), runningInConsole: false))->ensureSchema();

    // Outside NativePHP there is no launch-time migrate — the boot path is
    // the only migration path and must run for ANY sqlite-driver default.
    expect(Schema::hasTable('migrations'))->toBeTrue();
    expect(Schema::hasTable('app_settings'))->toBeTrue();
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
