<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Laravel does not wrap SQLite migrations in transactions, so a migration
 * failing mid-run leaves partial DDL behind with the migration unrecorded —
 * every subsequent launch-time `migrate --force` re-runs it, hits "already
 * exists", and the schema is permanently wedged. The migrator must snapshot
 * the database before a pending run and restore it on failure.
 */
beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/manuscript_migrator_'.uniqid();
    mkdir($this->tempDir);
    mkdir($this->tempDir.'/migrations');

    $this->dbPath = $this->tempDir.'/test.sqlite';
    touch($this->dbPath);

    config(['database.connections.migrator_test' => [
        'driver' => 'sqlite',
        'database' => $this->dbPath,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);

    file_put_contents($this->tempDir.'/migrations/2026_01_01_000001_create_alpha_table.php', <<<'PHP'
    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        public function up(): void
        {
            Schema::create('alpha', function (Blueprint $table) {
                $table->id();
            });
        }
    };
    PHP);
});

afterEach(function () {
    // `migrate --database=...` swaps the resolver's default connection and
    // does NOT restore it when the run throws — put it back before
    // RefreshDatabase resolves which connection to roll back.
    app('db')->setDefaultConnection('sqlite');
    config(['database.default' => 'sqlite']);
    DB::purge('migrator_test');

    foreach (glob($this->tempDir.'/migrations/*') as $f) {
        @unlink($f);
    }
    @rmdir($this->tempDir.'/migrations');
    foreach (glob($this->tempDir.'/{,.}*', GLOB_BRACE) as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
    }
    @rmdir($this->tempDir);
});

test('failed migration run restores the database to its pre-run state', function () {
    file_put_contents($this->tempDir.'/migrations/2026_01_01_000002_add_bravo_then_fail.php', <<<'PHP'
    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        public function up(): void
        {
            Schema::create('bravo', function (Blueprint $table) {
                $table->id();
            });

            throw new RuntimeException('simulated crash after partial DDL');
        }
    };
    PHP);

    $caught = null;

    try {
        Artisan::call('migrate', [
            '--database' => 'migrator_test',
            '--path' => $this->tempDir.'/migrations',
            '--realpath' => true,
            '--force' => true,
        ]);
    } catch (Throwable $e) {
        $caught = $e;
    }

    expect($caught)->not->toBeNull();
    expect($caught->getMessage())->toContain('simulated crash');

    // The whole run must roll back to the snapshot: neither the partial DDL
    // from the failing migration NOR the earlier (recorded) migration of the
    // same run may survive, or the bookkeeping and the schema disagree.
    $pdo = new PDO("sqlite:{$this->dbPath}");
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);

    expect($tables)->not->toContain('bravo');
    expect($tables)->not->toContain('alpha');

    // The snapshot was consumed by the restore — nothing left to leak.
    expect(glob($this->dbPath.'.pre-migrate.*'))->toBeEmpty();
});

test('successful migration run cleans up its snapshot', function () {
    Artisan::call('migrate', [
        '--database' => 'migrator_test',
        '--path' => $this->tempDir.'/migrations',
        '--realpath' => true,
        '--force' => true,
    ]);

    $pdo = new PDO("sqlite:{$this->dbPath}");
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);

    expect($tables)->toContain('alpha');
    expect(glob($this->dbPath.'.pre-migrate.*'))->toBeEmpty();
});
