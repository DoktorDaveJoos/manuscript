<?php

use App\Services\BackupEncryptionService;
use App\Services\BackupService;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Collection;
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
 * Boot the patched NativeServiceProvider's database rewrite the way the PHP
 * process started with the given argv does inside a live desktop launch.
 *
 * @param  list<string>  $argv
 */
function rewriteNativeDatabaseAs(array $argv, string $databasePath, ?Closure $whileConnected = null): void
{
    $originalArgv = $_SERVER['argv'] ?? null;
    $originalDefault = config('database.default');

    try {
        $_SERVER['argv'] = $argv;
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

/**
 * The .discarded-<ts> database files next to $live, without their sidecars.
 *
 * @return Collection<int, string>
 */
function backupSwapDiscardedFiles(string $live): Collection
{
    return collect(glob($live.BackupService::DISCARDED_PREFIX.'*') ?: [])
        ->reject(fn (string $path) => str_ends_with($path, '-wal') || str_ends_with($path, '-shm'))
        ->values();
}

/**
 * A secure build runs every artisan command through its app bundle.
 */
const SECURE_BUILD_BUNDLE = '/Applications/Manuscript.app/Contents/Resources/app/build/__nativephp_app_bundle';

it('applies a staged import in the launch bootstrap command before that command opens the database', function (array $argv) {
    stageBackupSwapImport($this->backups, $this->workDir, ['imported']);

    rewriteNativeDatabaseAs($argv, $this->live, function () {
        expect(DB::connection('nativephp')->table('books')->pluck('title')->all())->toBe(['imported']);
    });

    expect(file_exists($this->backups->pendingImportPath()))->toBeFalse()
        ->and(backupSwapTitles($this->backups->rollbackPath()))->toBe(['original'])
        ->and($this->backups->state())->toMatchArray([
            'pending_import' => false,
            'has_rollback' => true,
        ]);
})->with([
    'native:config' => [['artisan', 'native:config']],
    'native:php-ini' => [['artisan', 'native:php-ini']],
    'native:config in a secure build' => [[SECURE_BUILD_BUNDLE, 'artisan', 'native:config']],
    'native:config with a leading option' => [['artisan', '--no-ansi', 'native:config']],
]);

it('never swaps the database under a process that runs while the app is open', function (array $argv) {
    stageBackupSwapImport($this->backups, $this->workDir, ['imported']);

    rewriteNativeDatabaseAs($argv, $this->live);

    expect(file_exists($this->backups->pendingImportPath()))->toBeTrue()
        ->and(backupSwapTitles($this->live))->toBe(['original'])
        ->and($this->backups->state()['pending_import'])->toBeTrue();
})->with([
    'queue:work' => [['artisan', 'queue:work']],
    'schedule:run' => [['artisan', 'schedule:run']],
    'migrate' => [['artisan', 'migrate', '--force']],
    'queue:work in a secure build' => [[SECURE_BUILD_BUNDLE, 'artisan', 'queue:work']],
]);

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

    $discarded = backupSwapDiscardedFiles($this->live);

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

it('keeps the discarded copy when the parallel bootstrap command runs after a revert', function () {
    stageBackupSwapImport($this->backups, $this->workDir, ['imported']);
    $this->backups->applyPending();
    $this->backups->stageRevert();

    // native:config reverts; native:php-ini takes the lock right after it.
    $this->backups->applyPending();
    $this->backups->applyPending();

    $discarded = backupSwapDiscardedFiles($this->live);

    expect($discarded)->toHaveCount(1)
        ->and(backupSwapTitles($discarded->first()))->toBe(['imported']);
});

it('prunes discarded copies left behind by earlier launches', function () {
    $stale = $this->live.BackupService::DISCARDED_PREFIX.(time() - 3600);
    copy($this->live, $stale);
    touch($stale.'-wal');
    file_put_contents($this->backups->sidecarPath(), json_encode([
        'pending_import' => false,
        'pending_revert' => false,
    ]));

    $this->backups->applyPending();

    expect(file_exists($stale))->toBeFalse()
        ->and(file_exists($stale.'-wal'))->toBeFalse();
});

it('leaves the swap pending while another process still has the database open', function () {
    // A queue worker that outlived the previous app: Electron sends SIGTERM
    // and quits, the worker finishes its current job with the file open.
    $survivingWorker = new PDO('sqlite:'.$this->live);
    $survivingWorker->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $survivingWorker->exec('PRAGMA journal_mode = WAL');
    $survivingWorker->query('SELECT count(*) FROM books')->fetchAll();

    stageBackupSwapImport($this->backups, $this->workDir, ['imported']);
    $this->backups->applyPending();

    expect(file_exists($this->backups->pendingImportPath()))->toBeTrue()
        ->and(file_exists($this->backups->rollbackPath()))->toBeFalse()
        ->and(backupSwapTitles($this->live))->toBe(['original'])
        ->and($this->backups->state()['pending_import'])->toBeTrue();

    // The next launch, once the worker has exited, applies it.
    $survivingWorker = null;
    $this->backups->applyPending();

    expect(backupSwapTitles($this->live))->toBe(['imported'])
        ->and(backupSwapTitles($this->backups->rollbackPath()))->toBe(['original']);
});

it('reports a failed swap only once the launch has booted and Sentry is wired up', function () {
    // A revert whose sidecar cannot be rewritten fails after moving files.
    copy($this->live, $this->backups->rollbackPath());
    file_put_contents($this->backups->sidecarPath(), json_encode([
        'pending_import' => false,
        'pending_revert' => true,
    ]));
    chmod($this->backups->sidecarPath(), 0444);

    // The swap runs while the bootstrap command's providers are still
    // booting — before AppServiceProvider::boot() registers Sentry.
    $handler = Mockery::spy(ExceptionHandler::class);
    $launch = new Application(base_path());
    $launch->instance(ExceptionHandler::class, $handler);

    try {
        $this->backups->applyPending();

        $handler->shouldNotHaveReceived('report');

        $launch->boot();

        $handler->shouldHaveReceived('report')->once();
    } finally {
        chmod($this->backups->sidecarPath(), 0644);
        Container::setInstance($this->app);
    }
});

it('keeps the import pending and the user\'s data intact when the import cannot be moved into place', function () {
    stageBackupSwapImport($this->backups, $this->workDir, ['first']);
    $this->backups->applyPending();
    stageBackupSwapImport($this->backups, $this->workDir, ['second']);

    $failingBackups = new class(new BackupEncryptionService, $this->live) extends BackupService
    {
        protected function renameFile(string $from, string $to): bool
        {
            if (str_ends_with($from, BackupService::PENDING_IMPORT_SUFFIX)) {
                return false;
            }

            return parent::renameFile($from, $to);
        }
    };

    $failingBackups->applyPending();

    expect(backupSwapTitles($this->live))->toBe(['first'])
        ->and(backupSwapTitles($this->backups->rollbackPath()))->toBe(['original'])
        ->and(file_exists($this->backups->pendingImportPath()))->toBeTrue()
        ->and($this->backups->state()['pending_import'])->toBeTrue();

    // The next launch retries the import.
    $this->backups->applyPending();

    expect(backupSwapTitles($this->live))->toBe(['second'])
        ->and(backupSwapTitles($this->backups->rollbackPath()))->toBe(['first']);
});

it('derives has_rollback from the rollback file', function () {
    copy($this->live, $this->backups->rollbackPath());
    file_put_contents($this->backups->sidecarPath(), json_encode([
        'pending_import' => false,
        'pending_revert' => false,
        'has_rollback' => false,
    ]));

    expect($this->backups->state()['has_rollback'])->toBeTrue();

    unlink($this->backups->rollbackPath());

    expect($this->backups->state()['has_rollback'])->toBeFalse();
});
