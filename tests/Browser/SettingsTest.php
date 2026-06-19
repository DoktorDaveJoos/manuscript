<?php

use App\Models\License;

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

it('persists the anonymous usage statistics preference', function () {
    $toggle = '[data-testid="send-analytics-setting"] button[role="switch"]';
    $page = visit('/settings');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Share anonymous usage statistics')
        ->assertDataAttribute($toggle, 'state', 'checked')
        ->click($toggle)
        ->wait(1)
        ->assertDataAttribute($toggle, 'state', 'unchecked')
        ->navigate('/settings')
        ->assertDataAttribute($toggle, 'state', 'unchecked')
        ->assertNoJavaScriptErrors();
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

it('sends the backup import as plain multipart without a forced content type', function () {
    // The browser-test bridge cannot deliver multipart file bodies to the
    // in-process app (same limitation as ImportTest), so this asserts the
    // request shape instead: the import fetch must NOT set a Content-Type
    // header — fetch has to derive the multipart boundary from the FormData
    // body. When a JSON content type was forced, PHP dropped the upload and
    // every import failed with "The backup field is required."
    $workDir = sys_get_temp_dir().'/manuscript-backup-browser-'.uniqid();
    mkdir($workDir);
    $backupFile = $workDir.'/manuscript-backup-restore.sqlite';
    $pdo = new PDO('sqlite:'.$backupFile);
    $pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY, title TEXT)');
    $pdo = null;

    $page = visit('/settings');

    $page->assertNoJavaScriptErrors()
        ->click('Backup')
        ->assertSee('Restore your data')
        ->attach('input[type="file"]', $backupFile)
        ->assertSee('manuscript-backup-restore.sqlite');

    $page->script(<<<'JS'
        window.__importRequest = null;
        const originalFetch = window.fetch;
        window.fetch = (...args) => {
            window.__importRequest = {
                headers: (args[1] && args[1].headers) || {},
                isFormData: args[1]?.body instanceof FormData,
            };
            return originalFetch(...args);
        };
    JS);

    $page->click('[data-testid="backup-import-submit"]')->wait(1);

    $captured = $page->script('window.__importRequest');

    expect($captured)->not->toBeNull('the import button did not trigger a request');
    expect($captured['isFormData'])->toBeTrue();
    $headerNames = array_map('strtolower', array_keys($captured['headers']));
    expect($headerNames)->not->toContain('content-type');

    @unlink($backupFile);
    @rmdir($workDir);
});
