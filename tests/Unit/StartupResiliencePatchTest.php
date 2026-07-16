<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * Guards the NativePHP startup-resilience patch (docs/plans/startup-port-resilience.md).
 *
 * The Electron startup code lives in NativePHP's package and is overwritten by
 * every `composer install`, so we re-apply our version on each CI run via
 * scripts/nativephp-patches/apply.sh (a whole-file copy — diff-based patching was
 * line-ending-fragile and broke the Windows build). Crucially the patch targets
 * the COMPILED dist/: the publish build runs `electron-vite build`, never tsc, so
 * the package's src/*.ts is never recompiled — only dist/ is loaded at runtime.
 *
 * These tests fail loudly if the patched behaviour is lost or stops being wired,
 * or if a NativePHP bump moves the files we copy onto.
 */
const STARTUP_PATCH_FILES = 'scripts/nativephp-patches/files/resources/electron/electron-plugin';

const NATIVEPHP_ELECTRON_DIST = 'vendor/nativephp/desktop/resources/electron/electron-plugin/dist';

it('keeps the startup-resilience behaviours in the shipped dist patch files', function (): void {
    $index = (string) file_get_contents(base_path(STARTUP_PATCH_FILES.'/dist/index.js'));
    $api = (string) file_get_contents(base_path(STARTUP_PATCH_FILES.'/dist/server/api.js'));
    $utils = (string) file_get_contents(base_path(STARTUP_PATCH_FILES.'/dist/server/utils.js'));
    $windowApi = (string) file_get_contents(base_path(STARTUP_PATCH_FILES.'/dist/server/api/window.js'));
    $childProcessApi = (string) file_get_contents(base_path(STARTUP_PATCH_FILES.'/dist/server/api/childProcess.js'));
    $childProcessRuntime = (string) file_get_contents(base_path(STARTUP_PATCH_FILES.'/dist/server/childProcess.js'));

    // The behaviours that define the fix: a single-instance lock + a visible
    // startup-failure dialog, a bounded one-shot relaunch when Laravel cannot
    // boot a window, and an API port-bind retry.
    expect($index)->toContain('requestSingleInstanceLock')->toContain('showErrorBox')
        ->toContain('notifyLaravelOrThrow')
        ->toContain('reopenLaravelWindow')
        ->toContain('requestLaravelWindow')
        ->toContain('waitForMainWindow')
        ->toContain('Laravel boot completed without opening the main window.')
        ->toContain('MAIN_WINDOW_ID')
        ->toContain('WINDOW_READY_TIMEOUT')
        ->toContain('getWindowLoadPromise')
        ->toContain("window.on('show', onShown)")
        ->toContain("window.once('closed', onClosed)")
        ->toContain('this.bootstrapPromise')
        ->toContain('this.isBootstrapping')
        ->toContain('this.isQuitting')
        ->toContain('if (this.isQuitting || this.isRecovering)')
        ->toContain("nativeAutoUpdater.on('before-quit-for-update'")
        ->toContain('reopenPromise')
        ->toContain('isRecovering')
        ->toContain('if (!this.recoveryLaunch)')
        ->toContain('app.relaunch')
        ->toContain('recoveryLaunch')
        ->toContain('prepareRecoveryLaunch')
        ->and($utils)->toContain('export function notifyLaravelOrThrow')
        ->toContain('timeout = 120000')
        ->and($windowApi)->toContain('export function getWindowLoadPromise')
        ->and($childProcessApi)->toContain('nativephp-child-process-ready')
        ->and($childProcessRuntime)->toContain('nativephp-child-process-ready')
        ->and($api)->toContain('EADDRINUSE');
});

it('waits for the real child process readiness handshake before reporting startup success', function (): void {
    $sourceApi = (string) file_get_contents(base_path(STARTUP_PATCH_FILES.'/src/server/api/childProcess.ts'));
    $distApi = (string) file_get_contents(base_path(STARTUP_PATCH_FILES.'/dist/server/api/childProcess.js'));
    $sourceRuntime = (string) file_get_contents(base_path(STARTUP_PATCH_FILES.'/src/server/childProcess.ts'));
    $distRuntime = (string) file_get_contents(base_path(STARTUP_PATCH_FILES.'/dist/server/childProcess.js'));

    expect($sourceApi)
        ->toContain('const startingProcesses = new Map')
        ->toContain("message?.type !== 'nativephp-child-process-ready'")
        ->toContain("message?.type === 'nativephp-child-process-startup-error'")
        ->toContain("alias.startsWith('queue_') ? 10000 : 30000")
        ->toContain('await startPhpProcess(req.body)')
        ->toContain('res.status(503).json')
        ->and($distApi)
        ->toContain('const startingProcesses = new Map')
        ->toContain('nativephp-child-process-ready')
        ->toContain('nativephp-child-process-startup-error')
        ->toContain('res.status(503).json')
        ->and($sourceRuntime)
        ->toContain("type: 'nativephp-child-process-ready'")
        ->toContain("type: 'nativephp-child-process-startup-error'")
        ->and($distRuntime)
        ->toContain("type: 'nativephp-child-process-ready'")
        ->toContain("type: 'nativephp-child-process-startup-error'");
});

it('prepares recovery and starts the control API before any Laravel bootstrap command', function (): void {
    $index = (string) file_get_contents(base_path(STARTUP_PATCH_FILES.'/dist/index.js'));
    $bootstrapStart = strpos($index, 'runBootstrap(app)');
    $configStart = strpos($index, "\n    loadConfig() {");
    $bootstrapMethod = substr($index, $bootstrapStart, $configStart - $bootstrapStart);
    $recoveryPosition = strpos($bootstrapMethod, 'prepareRecoveryLaunch();');
    $apiPosition = strpos($bootstrapMethod, 'yield this.startElectronApi();');
    $configPosition = strpos($bootstrapMethod, 'const config = yield this.loadConfig();');

    expect($recoveryPosition)->toBeInt()
        ->and($apiPosition)->toBeInt()
        ->and($configPosition)->toBeInt()
        ->and($recoveryPosition)->toBeLessThan($apiPosition)
        ->and($apiPosition)->toBeLessThan($configPosition);
});

it('serializes reopen requests behind initial bootstrap', function (): void {
    $index = (string) file_get_contents(base_path(STARTUP_PATCH_FILES.'/dist/index.js'));
    $reopenStart = strpos($index, 'reopenLaravelWindow(phase)');
    $requestStart = strpos($index, "\n    requestLaravelWindow(phase) {");
    $reopenMethod = substr($index, $reopenStart, $requestStart - $reopenStart);

    expect($reopenStart)->toBeInt()
        ->and($requestStart)->toBeInt()
        ->and($reopenMethod)->toContain('if (this.bootstrapPromise)')
        ->toContain('this.bootstrapPromise')
        ->toContain('this.focusMainWindow()')
        ->toContain('return this.requestLaravelWindow(phase);');
});

it('only accepts a loaded and visible main window', function (): void {
    $index = (string) file_get_contents(base_path(STARTUP_PATCH_FILES.'/dist/index.js'));
    $waitStart = strpos($index, 'waitForMainWindow(timeout = WINDOW_READY_TIMEOUT)');
    $failureStart = strpos($index, 'handleStartupFailure(error, phase)');
    $waitMethod = substr($index, $waitStart, $failureStart - $waitStart);

    expect($waitStart)->toBeInt()
        ->and($failureStart)->toBeInt()
        ->and($waitMethod)->toContain('state.windows[MAIN_WINDOW_ID]')
        ->toContain('getWindowLoadPromise(MAIN_WINDOW_ID)')
        ->toContain('loadPromise')
        ->toContain("window.on('show', onShown)")
        ->toContain("window.once('closed', onClosed)")
        ->toContain('window.isVisible()')
        ->not->toContain('Object.values(state.windows)');
});

it('captures each BrowserWindow load result without delaying the API response', function (): void {
    $windowApi = (string) file_get_contents(base_path(STARTUP_PATCH_FILES.'/dist/server/api/window.js'));
    $loadPosition = strpos($windowApi, 'loadPromise = window.loadURL(url);');
    $responsePosition = strpos($windowApi, 'res.sendStatus(200);', $loadPosition);

    expect($windowApi)
        ->toContain('const windowLoadPromises = new Map();')
        ->toContain('export function getWindowLoadPromise(id)')
        ->toContain('let loadPromise = null;')
        ->toContain('loadPromise = window.loadURL(url);')
        ->toContain('loadPromise.catch(() => {});')
        ->toContain('windowLoadPromises.set(id, loadPromise);')
        ->toContain('if (windowLoadPromises.get(id) === loadPromise)')
        ->toContain('windowLoadPromises.delete(id);')
        ->not->toContain('await window.loadURL(url)')
        ->and($loadPosition)->toBeInt()
        ->and($responsePosition)->toBeInt()
        ->and($loadPosition)->toBeLessThan($responsePosition);
});

it('keeps startup-critical Laravel requests observable', function (): void {
    $utils = (string) file_get_contents(base_path(STARTUP_PATCH_FILES.'/dist/server/utils.js'));
    $strictStart = strpos($utils, 'export function notifyLaravelOrThrow');
    $strictEnd = strpos($utils, 'export function broadcastToWindows');
    $strictNotifier = substr($utils, $strictStart, $strictEnd - $strictStart);

    expect($strictStart)->toBeInt()
        ->and($strictEnd)->toBeInt()
        ->and($strictNotifier)->toContain('yield postToLaravel(endpoint, payload, timeout);')
        ->not->toContain('catch');
});

it('wires all startup dist patch files through apply.sh', function (): void {
    $apply = (string) file_get_contents(base_path('scripts/nativephp-patches/apply.sh'));

    expect($apply)
        ->toContain('resources/electron/electron-plugin/dist/index.js')
        ->toContain('resources/electron/electron-plugin/dist/server/utils.js')
        ->toContain('resources/electron/electron-plugin/dist/server/api/window.js')
        ->toContain('resources/electron/electron-plugin/src/server/childProcess.ts')
        ->toContain('resources/electron/electron-plugin/dist/server/childProcess.js')
        ->toContain('resources/electron/electron-plugin/dist/server/api.js');
});

it('targets compiled dist files that still exist in the installed NativePHP package', function (): void {
    // If a NativePHP bump renames/restructures these, apply.sh fails loudly with
    // "vendor file missing" at publish time — guard the same paths here.
    expect(base_path(NATIVEPHP_ELECTRON_DIST.'/index.js'))->toBeReadableFile()
        ->and(base_path(NATIVEPHP_ELECTRON_DIST.'/server/utils.js'))->toBeReadableFile()
        ->and(base_path(NATIVEPHP_ELECTRON_DIST.'/server/api/window.js'))->toBeReadableFile()
        ->and(base_path(NATIVEPHP_ELECTRON_DIST.'/server/childProcess.js'))->toBeReadableFile()
        ->and(base_path(NATIVEPHP_ELECTRON_DIST.'/server/api.js'))->toBeReadableFile();
});

it('verifies the recovery code inside packaged macOS app bundles', function (): void {
    $workflow = (string) file_get_contents(base_path('.github/workflows/publish.yml'));

    expect($workflow)
        ->toContain('- name: Verify packaged startup recovery')
        ->toContain('node "$ASAR_BIN" extract')
        ->toContain('ASAR_BIN="vendor/nativephp/desktop/resources/electron/node_modules/@electron/asar/bin/asar.js"')
        ->toContain('nativephp-recovery prepareRecoveryLaunch notifyLaravelOrThrow waitForMainWindow getWindowLoadPromise nativephp-child-process-ready before-quit-for-update isQuitting bootstrapPromise serializeProcess EADDRINUSE')
        ->toContain("grep -Fq 'reconcileRuntimeConfig'")
        ->toContain("grep -Fq 'NATIVEPHP_DATABASE_PATH'")
        ->toContain("grep -Fq 'NativePHP child process failed to start:'")
        ->toContain("grep -Fq 'catch (\\Throwable \$exception)'")
        ->toContain('ARM64_APP="$DIST/mac-arm64/Manuscript.app"')
        ->toContain('X64_APP="$DIST/mac/Manuscript.app"')
        ->toContain('for APP in "$ARM64_APP" "$X64_APP"; do')
        ->toContain('for DMG in "$DIST/Manuscript-arm64.dmg" "$DIST/Manuscript-x64.dmg"; do');
});

it('no longer applies these via the line-ending-fragile composer-patches mechanism', function (): void {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    // extra.patches on Windows runs `git apply` under core.autocrlf=true, which
    // silently fails to apply and kills the build. We must not regress to it.
    expect($composer['extra'] ?? [])->not->toHaveKey('patches')
        ->and($composer['extra'] ?? [])->not->toHaveKey('composer-exit-on-patch-failure');
});
