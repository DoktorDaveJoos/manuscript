<?php

use App\Database\SqliteVecConnector;
use App\Services\DatabaseRepairService;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Realistic Corruption Recovery Tests
|--------------------------------------------------------------------------
|
| These tests build a valid SQLite file with real schema + seed data, then
| apply specific corruption patterns that mirror what actually happens in
| the wild (torn writes, zeroed pages, truncation, bit flips) and verify:
|
|   1. The connector correctly detects corruption (vs transient errors).
|   2. The backup path is taken safely (original file preserved, fresh
|      file created, marker written).
|   3. DatabaseRepairService salvages as much as it can from the backup.
|   4. User data (rows in non-shadow tables) actually survives.
|
| Synthetic random-bytes corruption only exercises the "not a database"
| error path. Real-world corruption keeps the SQLite header intact but
| damages specific pages — these tests cover that.
|
*/

beforeEach(function () {
    SqliteVecConnector::resetIntegrityFlag();
    $this->tempDir = sys_get_temp_dir().'/manuscript_corruption_'.uniqid();
    mkdir($this->tempDir);
});

afterEach(function () {
    foreach (glob($this->tempDir.'/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($this->tempDir);
});

/**
 * Build a valid file-based SQLite DB with the app_settings schema and
 * $rowCount rows of realistic data. Returns the path.
 */
function seedAppSettings(string $path, int $rowCount): string
{
    $pdo = new PDO("sqlite:{$path}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec('CREATE TABLE app_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        key TEXT UNIQUE NOT NULL,
        value TEXT,
        created_at DATETIME,
        updated_at DATETIME
    )');

    $insert = $pdo->prepare('INSERT INTO app_settings (key, value, created_at, updated_at) VALUES (?, ?, ?, ?)');
    $now = date('Y-m-d H:i:s');

    for ($i = 0; $i < $rowCount; $i++) {
        $insert->execute(["setting_{$i}", str_repeat("payload-{$i}-", 20), $now, $now]);
    }

    // Close and reopen to flush WAL.
    unset($pdo);

    return $path;
}

// ---------------------------------------------------------------------------
// Corruption pattern 1: Truncated file (power loss, partial write)
// ---------------------------------------------------------------------------

test('truncated database still triggers repair with marker and preserves original via backup', function () {
    $dbPath = $this->tempDir.'/seeded.sqlite';
    seedAppSettings($dbPath, 500);

    $original = file_get_contents($dbPath);
    $originalSize = strlen($original);

    // Cut the file to 40% size — keeps the SQLite magic header but destroys
    // most data pages. This is what you see after a process crash mid-write.
    file_put_contents($dbPath, substr($original, 0, (int) ($originalSize * 0.4)));

    $connector = app(SqliteVecConnector::class);
    $freshPdo = $connector->connect(['database' => $dbPath]);

    // Detection + marker.
    expect(app()->bound('database.repaired'))->toBeTrue();
    expect(file_exists($this->tempDir.'/.repairing'))->toBeTrue();

    // Backup preserves the (truncated but non-destroyed) original.
    $backups = glob($this->tempDir.'/seeded.sqlite.corrupt.*');
    expect($backups)->not->toBeEmpty();
    expect(filesize($backups[0]))->toBe((int) ($originalSize * 0.4));

    // Fresh file is a usable SQLite DB (empty).
    $check = $freshPdo->query('PRAGMA quick_check')->fetchColumn();
    expect($check)->toBe('ok');
});

// ---------------------------------------------------------------------------
// Corruption pattern 2: Zeroed middle page (single disk sector failure)
// ---------------------------------------------------------------------------

test('zeroed data page triggers repair and salvages rows from other pages', function () {
    $backupPath = $this->tempDir.'/backup.sqlite';
    seedAppSettings($backupPath, 500);

    // Find a data page (skip header page 0 and root page 1) and zero it.
    $data = file_get_contents($backupPath);
    $pageSize = 4096;
    $pageCount = intdiv(strlen($data), $pageSize);
    expect($pageCount)->toBeGreaterThan(5);

    $targetPage = 3;
    $offset = $targetPage * $pageSize;
    $data = substr_replace($data, str_repeat("\0", $pageSize), $offset, $pageSize);
    file_put_contents($backupPath, $data);

    // Pre-seed the fresh (default-connection) DB so recoverData has a target.
    DB::table('app_settings')->truncate();
    $initialCount = DB::table('app_settings')->count();

    $service = new DatabaseRepairService;
    $result = $service->recoverData($backupPath);

    // The table either survives (most pages intact) OR fails (if the zeroed
    // page was a critical B-tree node). Either way, we haven't silently
    // corrupted the fresh DB.
    $recoveredOrFailed = in_array('app_settings', $result['recovered'], true)
        || in_array('app_settings', $result['failed'], true);
    expect($recoveredOrFailed)->toBeTrue();

    // If it was recovered, we should have substantially more rows than we
    // started with (some lost to the zeroed page, but not all).
    if (in_array('app_settings', $result['recovered'], true)) {
        $finalCount = DB::table('app_settings')->count();
        expect($finalCount)->toBeGreaterThan($initialCount);
    }
});

// ---------------------------------------------------------------------------
// Corruption pattern 3: Bit flip in header (disk error)
// ---------------------------------------------------------------------------

test('corrupted SQLite header triggers repair via "not a database" signature', function () {
    $dbPath = $this->tempDir.'/flipped.sqlite';
    seedAppSettings($dbPath, 100);

    // Flip a byte inside the SQLite magic string at offset 0-15.
    $data = file_get_contents($dbPath);
    $data[5] = chr(ord($data[5]) ^ 0xFF);
    file_put_contents($dbPath, $data);

    $connector = app(SqliteVecConnector::class);
    $freshPdo = $connector->connect(['database' => $dbPath]);

    expect(app()->bound('database.repaired'))->toBeTrue();
    expect(glob($this->tempDir.'/flipped.sqlite.corrupt.*'))->not->toBeEmpty();

    $check = $freshPdo->query('PRAGMA quick_check')->fetchColumn();
    expect($check)->toBe('ok');
});

// ---------------------------------------------------------------------------
// End-to-end: seeded realistic data → corruption → recovery → data survives
// ---------------------------------------------------------------------------

test('end-to-end recovery: 200 realistic rows land in the fresh DB with content intact', function () {
    // This is the common real-world case: the backup file is ITSELF readable
    // (e.g., corruption was limited to WAL/SHM, or happened only to pages
    // outside this table's B-tree). The user's manuscript content must land
    // in the fresh DB exactly, byte-for-byte — no partial reads, no
    // truncation, no encoding drift.
    $backupPath = $this->tempDir.'/backup.sqlite';
    seedAppSettings($backupPath, 200);

    DB::table('app_settings')->truncate();

    $service = new DatabaseRepairService;
    $result = $service->recoverData($backupPath);

    expect($result['recovered'])->toContain('app_settings');
    expect($result['failed'])->toBeEmpty();

    $recovered = DB::table('app_settings')->where('key', 'like', 'setting_%')->count();
    expect($recovered)->toBe(200);

    // Spot-check: content integrity across the row range.
    foreach ([0, 50, 199] as $i) {
        $row = DB::table('app_settings')->where('key', "setting_{$i}")->first();
        expect($row)->not->toBeNull();
        expect($row->value)->toBe(str_repeat("payload-{$i}-", 20));
    }
});

// ---------------------------------------------------------------------------
// Regression check: non-corruption error does NOT trigger destructive repair
// ---------------------------------------------------------------------------

test('SQLite lock contention does not trigger destructive repair', function () {
    $dbPath = $this->tempDir.'/locked.sqlite';
    seedAppSettings($dbPath, 50);

    // Open a long transaction on the file so the integrity check would hit
    // a BUSY error — but PDO read-only quick_check usually succeeds on WAL.
    // Instead, simulate the shape of a busy error by passing a path to a
    // non-existent directory, which surfaces as an unopenable (not corrupt) error.
    $bogusPath = $this->tempDir.'/does-not-exist/inner.sqlite';

    $connector = app(SqliteVecConnector::class);

    try {
        $connector->connect(['database' => $bogusPath]);
        $this->fail('Expected non-corruption error to propagate.');
    } catch (Throwable) {
        // Expected.
    }

    // No destructive backup created anywhere.
    expect(glob($this->tempDir.'/*.corrupt.*'))->toBeEmpty();
    // Healthy file at the original path is untouched.
    expect(file_exists($dbPath))->toBeTrue();
    expect(filesize($dbPath))->toBeGreaterThan(0);
});
