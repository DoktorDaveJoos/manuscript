<?php

use App\Models\AppSetting;
use App\Services\BackupEncryptionService;
use App\Services\BackupService;
use Illuminate\Http\Testing\File;

/**
 * Bind BackupService to an isolated temp database that does NOT collide with
 * the test runner's database.sqlite. Each test gets its own dir so no cleanup
 * coordination is required between tests.
 */
beforeEach(function () {
    $this->workDir = sys_get_temp_dir().'/manuscript-backup-controller-'.uniqid();
    mkdir($this->workDir);
    $this->backupDb = $this->workDir.'/live.sqlite';

    $pdo = new PDO('sqlite:'.$this->backupDb);
    $pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY, title TEXT)');
    $pdo->exec("INSERT INTO books (title) VALUES ('original')");
    $pdo = null;

    app()->instance(
        BackupService::class,
        new BackupService(new BackupEncryptionService, $this->backupDb),
    );
});

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

test('export endpoint returns plain SQLite copy without passphrase', function () {
    $response = $this->post(route('settings.backup.export'));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))
        ->toContain('.sqlite');

    expect(AppSetting::get('backup.last_export_at'))->not->toBeNull();
});

test('export endpoint returns encrypted MSBK with passphrase', function () {
    $response = $this->post(route('settings.backup.export'), [
        'passphrase' => 'strong-pass',
    ]);

    $response->assertOk();
    expect($response->headers->get('content-disposition'))
        ->toContain('.msbk');
});

test('export rejects passphrase shorter than 8 characters', function () {
    $this->post(route('settings.backup.export'), [
        'passphrase' => 'short',
    ])->assertStatus(302); // validation redirect from Request::validate
});

test('import endpoint stages a valid plain sqlite backup', function () {
    $importSource = $this->workDir.'/incoming.sqlite';
    $pdo = new PDO('sqlite:'.$importSource);
    $pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY, title TEXT)');
    $pdo = null;

    $upload = new File(
        'backup.sqlite',
        fopen($importSource, 'rb'),
    );

    $this->post(route('settings.backup.import'), [
        'backup' => $upload,
    ])->assertOk()
        ->assertJsonPath('requires_restart', true);

    $service = app(BackupService::class);
    expect(file_exists($service->pendingImportPath()))->toBeTrue();
    expect(file_exists($service->rollbackPath()))->toBeTrue();
});

test('import endpoint rejects a corrupt file with 422', function () {
    $junk = $this->workDir.'/junk.bin';
    file_put_contents($junk, str_repeat('garbage', 200));

    $upload = new File(
        'backup.sqlite',
        fopen($junk, 'rb'),
    );

    $this->post(route('settings.backup.import'), [
        'backup' => $upload,
    ])->assertStatus(422);

    $service = app(BackupService::class);
    expect(file_exists($service->rollbackPath()))->toBeFalse();
});

test('import endpoint rejects wrong passphrase with 422 and message', function () {
    $importSource = $this->workDir.'/incoming.sqlite';
    $pdo = new PDO('sqlite:'.$importSource);
    $pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY, title TEXT)');
    $pdo = null;

    $encryptedPath = $this->workDir.'/incoming.msbk';
    (new BackupEncryptionService)->encryptFile($importSource, $encryptedPath, 'right');

    $upload = new File(
        'backup.msbk',
        fopen($encryptedPath, 'rb'),
    );

    $response = $this->post(route('settings.backup.import'), [
        'backup' => $upload,
        'passphrase' => 'wrong-pass',
    ]);

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('passphrase');

    $service = app(BackupService::class);
    expect(file_exists($service->rollbackPath()))->toBeFalse();
});

test('revert endpoint requires an existing rollback file', function () {
    $this->post(route('settings.backup.revert'))->assertStatus(422);
});

test('revert endpoint succeeds when a rollback exists', function () {
    // Stage an import first to create the rollback.
    $importSource = $this->workDir.'/incoming.sqlite';
    $pdo = new PDO('sqlite:'.$importSource);
    $pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY, title TEXT)');
    $pdo = null;

    $service = app(BackupService::class);
    $service->stageImport($importSource);
    $service->applyPending(); // simulate next-boot swap

    $this->post(route('settings.backup.revert'))
        ->assertOk()
        ->assertJsonPath('requires_restart', true);
});

test('settings page exposes backup state to Inertia', function () {
    $this->get(route('settings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/index')
            ->has('backup', fn ($backup) => $backup
                ->where('has_rollback', false)
                ->etc()
            )
        );
});
