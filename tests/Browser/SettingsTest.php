<?php

use App\Models\License;
use App\Services\BackupEncryptionService;
use App\Services\BackupService;

it('renders settings page with all tabs', function () {
    $page = visit('/settings');

    $page->assertNoJavaScriptErrors()
        ->assertSee('General')
        ->assertSee('Editor')
        ->assertSee('Account');
});

it('switches the interface language to German via the lazy-loaded locale', function () {
    $page = visit('/settings');

    // 'Deutsch' lives in a code-split chunk that is only fetched when the
    // locale toggle is clicked — this covers the lazy i18n loading path.
    $page->assertNoJavaScriptErrors()
        ->assertSee('Display Language')
        ->click('Deutsch')
        ->assertSee('Anzeigesprache')
        ->assertNoJavaScriptErrors();
});

it('shows appearance section with theme options', function () {
    $page = visit('/settings');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Appearance')
        ->assertSee('Light')
        ->assertSee('Dark')
        ->assertSee('System');
});

it('shows license section for activating pro', function () {
    $page = visit('/settings');

    $page->assertNoJavaScriptErrors()
        ->assertSee('License')
        ->assertSee('Activate');
});

it('shows active license status when pro is enabled', function () {
    License::factory()->create();

    $page = visit('/settings');

    $page->assertNoJavaScriptErrors()
        ->assertSee('License active')
        ->assertSee('Deactivate');
});

it('imports a backup file through the backup section', function () {
    // Isolate BackupService on a temp database — stageImport renames the
    // live DB aside, which must never touch the test app's database.
    $workDir = sys_get_temp_dir().'/manuscript-backup-browser-'.uniqid();
    mkdir($workDir);

    $liveDb = $workDir.'/live.sqlite';
    $pdo = new PDO('sqlite:'.$liveDb);
    $pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY, title TEXT)');
    $pdo = null;

    $backupFile = $workDir.'/manuscript-backup-restore.sqlite';
    $pdo = new PDO('sqlite:'.$backupFile);
    $pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY, title TEXT)');
    $pdo = null;

    app()->instance(
        BackupService::class,
        new BackupService(new BackupEncryptionService, $liveDb),
    );

    $page = visit('/settings');

    // The import fetch must send the file as real multipart — forcing a JSON
    // content type on the FormData body makes the server drop the upload and
    // answer "The backup field is required."
    $page->assertNoJavaScriptErrors()
        ->click('Backup')
        ->assertSee('Restore your data')
        ->attach('input[type="file"]', $backupFile)
        ->assertSee('manuscript-backup-restore.sqlite')
        ->click('[data-testid="backup-import-submit"]')
        ->assertSee('Quit Manuscript and reopen');

    expect(file_exists($liveDb.'.pending-import'))->toBeTrue();

    foreach (glob($workDir.'/*') ?: [] as $f) {
        @unlink($f);
    }
    @unlink($workDir.'/.backup-state.json');
    @rmdir($workDir);
});
