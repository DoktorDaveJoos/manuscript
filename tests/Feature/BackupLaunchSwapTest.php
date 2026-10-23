<?php

use App\Services\BackupEncryptionService;
use App\Services\BackupService;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Native\Desktop\NativeServiceProvider;

/**
 * Runs BackupService::applyPending() in a separate, booted Laravel process,
 * the way each launch bootstrap command (native:config, native:php-ini) does.
 * The child reports ready, then waits for the start signal so several
 * children hit the swap at the same moment.
 */
const BACKUP_APPLY_PENDING_CHILD = <<<'PHP'
[, $basePath, $databasePath, $readyPath, $startSignal] = $argv;
require $basePath.'/vendor/autoload.php';
$app = require $basePath.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
touch($readyPath);
$deadline = microtime(true) + 15;
while (! file_exists($startSignal) && microtime(true) < $deadline) {
    usleep(500);
}
(new App\Services\BackupService(new App\Services\BackupEncryptionService, $databasePath))->applyPending();
PHP;

beforeEach(function () {
    $this->workDir = sys_get_temp_dir().'/manuscript-backup-launch-'.uniqid();
    mkdir($this->workDir);
    $this->live = $this->workDir.'/database.sqlite';

    createBackupSwapDatabase($this->live, ['original']);

    $this->backups = new BackupService(new BackupEncryptionService, $this->live);
});

afterEach(function () {
    File::deleteDirectory($this->workDir);
});

/**
 * Create a SQLite file with a `books` table holding the given titles — the
 * minimum shape BackupService accepts as a Manuscript database.
 *
 * @param  list<string>  $titles
 */
function createBackupSwapDatabase(string $path, array $titles): void
{
    $pdo = new PDO('sqlite:'.$path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY, title TEXT)');

    $insert = $pdo->prepare('INSERT INTO books (title) VALUES (?)');
    foreach ($titles as $title) {
        $insert->execute([$title]);
    }
}

/**
 * @param  list<string>  $titles
 */
function stageBackupSwapImport(BackupService $backups, string $workDir, array $titles): void
{
    $incoming = $workDir.'/incoming-'.uniqid().'.sqlite';
    createBackupSwapDatabase($incoming, $titles);

    $backups->stageImport($incoming);
}

/**
 * @return list<string>
 */
function backupSwapTitles(string $path): array
{
    $pdo = new PDO('sqlite:'.$path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $pdo->query('SELECT title FROM books ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Leave $path the way a killed process leaves a WAL database: the rows are
 * committed to its -wal but never checkpointed into the main file. The files
 * are snapshotted while the writer still holds them, then put back after the
 * writer's clean close has checkpointed and deleted its own -wal.
 *
 * @param  list<string>  $titles
 */
function crashWithUncheckpointedRows(string $path, array $titles): void
{
    $writer = new PDO('sqlite:'.$path);
    $writer->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $writer->exec('PRAGMA journal_mode = WAL');
    $writer->exec('PRAGMA wal_autocheckpoint = 0');

    $insert = $writer->prepare('INSERT INTO books (title) VALUES (?)');
    foreach ($titles as $title) {
        $insert->execute([$title]);
    }

    foreach (['', '-wal', '-shm'] as $suffix) {
        copy($path.$suffix, $path.$suffix.'.crashed');
    }

    $insert = null;
    $writer = null;

    foreach (['', '-wal', '-shm'] as $suffix) {
        rename($path.$suffix.'.crashed', $path.$suffix);
    }

    expect(filesize($path.'-wal'))->toBeGreaterThan(0, 'precondition: the rows must only exist in the -wal');
}

/**
 * Boot the patched NativeServiceProvider's database rewrite the way the given
 * artisan command does inside a live desktop launch.
 */
function rewriteNativeDatabaseAs(string $command, string $databasePath, ?Closure $whileConnected = null): void
{
    $originalArgv = $_SERVER['argv'] ?? null;
    $originalDefault = config('database.default');

    try {
        $_SERVER['argv'] = ['artisan', $command];
        config([
            'app.debug' => false,
            'nativephp-internal.database_path' => $databasePath,
        ]);

        (new NativeServiceProvider(app()))->rewriteDatabase();

        if ($whileConnected) {
            $whileConnected();
        }
    } finally {
        $_SERVER['argv'] = $originalArgv;
        config(['database.default' => $originalDefault]);
        DB::purge('nativephp');
    }
}

/**
 * @return array{process: InvokedProcess, ready: string}
 */
function startBackupApplyPendingChild(string $databasePath, string $workDir, string $startSignal): array
{
    $ready = $workDir.'/ready-'.uniqid();

    $process = Process::start([
        PHP_BINARY,
        '-r',
        BACKUP_APPLY_PENDING_CHILD,
        base_path(),
        $databasePath,
        $ready,
        $startSignal,
    ]);

    return ['process' => $process, 'ready' => $ready];
}

function waitForBackupSwapFiles(array $paths): void
{
    $deadline = microtime(true) + 15;

    while (microtime(true) < $deadline) {
        if (collect($paths)->every(fn (string $path) => file_exists($path))) {
            return;
        }

        usleep(1000);
    }

    throw new RuntimeException('Timed out waiting for child processes to start.');
}

it('applies a staged import in the launch bootstrap command before that command opens the database', function (string $command) {
    stageBackupSwapImport($this->backups, $this->workDir, ['imported']);

    rewriteNativeDatabaseAs($command, $this->live, function () {
        expect(DB::connection('nativephp')->table('books')->pluck('title')->all())->toBe(['imported']);
    });

    expect(file_exists($this->backups->pendingImportPath()))->toBeFalse()
        ->and(backupSwapTitles($this->backups->rollbackPath()))->toBe(['original'])
        ->and($this->backups->state())->toMatchArray([
            'pending_import' => false,
            'has_rollback' => true,
        ]);
})->with(['native:config', 'native:php-ini']);

it('never swaps the database under a process that runs while the app is open', function (string $command) {
    stageBackupSwapImport($this->backups, $this->workDir, ['imported']);

    rewriteNativeDatabaseAs($command, $this->live);

    expect(file_exists($this->backups->pendingImportPath()))->toBeTrue()
        ->and(backupSwapTitles($this->live))->toBe(['original'])
        ->and($this->backups->state()['pending_import'])->toBeTrue();
})->with(['queue:work', 'schedule:run', 'migrate']);

it('keeps the live database usable until the next launch after an import is staged', function () {
    stageBackupSwapImport($this->backups, $this->workDir, ['imported']);

    expect(backupSwapTitles($this->live))->toBe(['original'])
        ->and(file_exists($this->backups->pendingImportPath()))->toBeTrue()
        ->and(file_exists($this->backups->rollbackPath()))->toBeFalse();
});

it('keeps a killed session\'s uncheckpointed rows with the rollback and never replays them into the import', function () {
    crashWithUncheckpointedRows($this->live, ['written-before-quit']);

    stageBackupSwapImport($this->backups, $this->workDir, ['imported']);
    $this->backups->applyPending();

    // Nothing of the replaced database may be left at the live path: SQLite
    // pairs a -wal with its database by file name and would replay it into
    // the imported file.
    expect(file_exists($this->live.'-wal'))->toBeFalse()
        ->and(file_exists($this->live.'-shm'))->toBeFalse()
        ->and(backupSwapTitles($this->live))->toBe(['imported'])
        ->and(backupSwapTitles($this->backups->rollbackPath()))->toBe(['original', 'written-before-quit']);
});

it('moves a killed session\'s WAL away with the discarded database on revert', function () {
    stageBackupSwapImport($this->backups, $this->workDir, ['imported']);
    $this->backups->applyPending();

    crashWithUncheckpointedRows($this->live, ['edited-after-import']);

    $this->backups->stageRevert();
    $this->backups->applyPending();

    $discarded = collect(glob($this->live.BackupService::DISCARDED_PREFIX.'*') ?: [])
        ->reject(fn (string $path) => str_ends_with($path, '-wal') || str_ends_with($path, '-shm'))
        ->values();

    expect(file_exists($this->live.'-wal'))->toBeFalse()
        ->and(file_exists($this->live.'-shm'))->toBeFalse()
        ->and(backupSwapTitles($this->live))->toBe(['original'])
        ->and($discarded)->toHaveCount(1)
        ->and(backupSwapTitles($discarded->first()))->toBe(['imported', 'edited-after-import']);
});

it('recovers the rollback\'s WAL when an older build staged the import', function () {
    // Older builds renamed only the main file aside at staging time and left
    // its -wal/-shm at the live path.
    crashWithUncheckpointedRows($this->live, ['written-before-quit']);
    rename($this->live, $this->backups->rollbackPath());

    $incoming = $this->workDir.'/incoming.sqlite';
    createBackupSwapDatabase($incoming, ['imported']);
    rename($incoming, $this->backups->pendingImportPath());
    file_put_contents($this->backups->sidecarPath(), json_encode([
        'pending_import' => true,
        'pending_revert' => false,
        'has_rollback' => true,
    ]));

    $this->backups->applyPending();

    expect(file_exists($this->live.'-wal'))->toBeFalse()
        ->and(backupSwapTitles($this->live))->toBe(['imported'])
        ->and(backupSwapTitles($this->backups->rollbackPath()))->toBe(['original', 'written-before-quit']);
});

it('applies a staged import exactly once when the launch bootstrap commands race', function () {
    stageBackupSwapImport($this->backups, $this->workDir, ['imported']);

    $startSignal = $this->workDir.'/start';
    $children = collect(range(1, 6))
        ->map(fn () => startBackupApplyPendingChild($this->live, $this->workDir, $startSignal));

    waitForBackupSwapFiles($children->pluck('ready')->all());
    touch($startSignal);

    $children->each(function (array $child) {
        $result = $child['process']->wait();

        expect($result->successful())->toBeTrue($result->errorOutput().$result->output());
    });

    expect(backupSwapTitles($this->live))->toBe(['imported'])
        ->and(backupSwapTitles($this->backups->rollbackPath()))->toBe(['original'])
        ->and(file_exists($this->backups->pendingImportPath()))->toBeFalse()
        ->and($this->backups->state())->toMatchArray([
            'pending_import' => false,
            'pending_revert' => false,
            'has_rollback' => true,
        ]);
});

it('waits for a launch command that is mid-swap instead of racing it', function () {
    stageBackupSwapImport($this->backups, $this->workDir, ['imported']);

    $lock = fopen($this->backups->lockPath(), 'c');
    flock($lock, LOCK_EX);

    try {
        $startSignal = $this->workDir.'/start';
        touch($startSignal);
        $child = startBackupApplyPendingChild($this->live, $this->workDir, $startSignal);

        waitForBackupSwapFiles([$child['ready']]);
        usleep(300_000);

        expect($child['process']->running())->toBeTrue()
            ->and(file_exists($this->backups->pendingImportPath()))->toBeTrue()
            ->and(backupSwapTitles($this->live))->toBe(['original']);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    $result = $child['process']->wait();

    expect($result->successful())->toBeTrue($result->errorOutput().$result->output())
        ->and(backupSwapTitles($this->live))->toBe(['imported']);
});

it('drops a staged import when a revert is staged after it', function () {
    stageBackupSwapImport($this->backups, $this->workDir, ['first']);
    $this->backups->applyPending();

    stageBackupSwapImport($this->backups, $this->workDir, ['second']);
    $this->backups->stageRevert();
    $this->backups->applyPending();

    expect(backupSwapTitles($this->live))->toBe(['original'])
        ->and(file_exists($this->backups->pendingImportPath()))->toBeFalse()
        ->and($this->backups->state())->toMatchArray([
            'pending_import' => false,
            'pending_revert' => false,
            'has_rollback' => false,
        ]);

    // The next launch must not resurrect the superseded import.
    $this->backups->applyPending();

    expect(backupSwapTitles($this->live))->toBe(['original']);
});
