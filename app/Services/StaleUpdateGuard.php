<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Boot-time backstop that stops a stranded Squirrel auto-update from looping.
 *
 * Squirrel's installer, ShipIt, is registered as a KeepAlive launchd job so it
 * can outlive the app it replaces. It refuses to install while any instance of
 * the target app is running ("App Still Running Error"), exits, and launchd
 * respawns it ~8s later. If an update was staged for install but the app is
 * reopened before ShipIt finishes, the job loops forever — burning CPU while the
 * update never applies (the "app won't start" symptom).
 *
 * Disabling autoInstallOnAppQuit removes the path that strands ShipIt going
 * forward (see the autoUpdater.js patch), but this guard heals machines that are
 * ALREADY stuck — and any residual strand (e.g. a crash mid-install) — by
 * stopping the looping launchd job and clearing its stale state on the next
 * launch. The update simply re-stages on the next explicit install.
 *
 * Best-effort and macOS-only: a failure here must never block the app launch.
 */
class StaleUpdateGuard
{
    /**
     * Detect and clear a stranded ShipIt install. Safe to call on every launch —
     * it returns immediately when there is nothing pending.
     */
    public function reconcile(): void
    {
        try {
            // Squirrel/ShipIt is the macOS updater, and we only have the OS access
            // to reconcile it from inside the bundled desktop app. Check the free
            // constant first so non-macOS launches skip the config lookup entirely.
            if (PHP_OS_FAMILY !== 'Darwin' || ! config('nativephp-internal.running')) {
                return;
            }

            $stateFile = $this->shipItStateFile();

            if ($stateFile === null || ! is_file($stateFile)) {
                return; // no pending install — nothing to heal
            }

            // A pending install exists (state file present); heal only if its staged
            // version differs from the version we're running (see shouldHeal).
            $stagedVersion = $this->stagedUpdateVersion($stateFile);
            $runningVersion = (string) config('nativephp.version', '0.0.0');

            if (! $this->shouldHeal($stagedVersion, $runningVersion)) {
                return;
            }

            $this->stopShipIt();

            // Clear the stale state so a fresh ShipIt can't resume the dead install
            // (@unlink no-ops if ShipIt already removed it concurrently).
            @unlink($stateFile);

            Log::warning('StaleUpdateGuard cleared a stranded Squirrel update', [
                'staged_version' => $stagedVersion,
                'running_version' => $runningVersion,
            ]);
        } catch (\Throwable $e) {
            // A heal failure must never block the launch — log and carry on.
            report($e);
        }
    }

    /**
     * Decide whether the pending ShipIt install (its state file already confirmed
     * present by the caller) is genuinely stranded.
     *
     * Stranded ⇔ the staged version differs from the version we are currently
     * running (so the update has NOT been applied yet, yet ShipIt is queued to
     * install while we run). When the versions match — or the staged version can't
     * be read — we never heal, so a legitimate in-flight or just-completed install
     * (whose relaunched app IS the staged version) is never aborted.
     */
    public function shouldHeal(?string $stagedVersion, string $runningVersion): bool
    {
        if ($stagedVersion === null || $stagedVersion === '') {
            return false;
        }

        return $stagedVersion !== $runningVersion;
    }

    /**
     * Path to ShipIt's state file, e.g.
     * ~/Library/Caches/com.manuscriptai.app.ShipIt/ShipItState.plist.
     */
    private function shipItStateFile(): ?string
    {
        $appId = (string) config('nativephp.app_id', '');
        $home = getenv('HOME') ?: '';

        if ($appId === '' || $home === '') {
            return null;
        }

        return $home.'/Library/Caches/'.$appId.'.ShipIt/ShipItState.plist';
    }

    /**
     * Read CFBundleShortVersionString from the staged update bundle. Squirrel
     * stages into an `update.XXXXXX` directory beside the state file.
     */
    private function stagedUpdateVersion(string $stateFile): ?string
    {
        $pattern = dirname($stateFile).'/update.*/*.app/Contents/Info.plist';

        foreach (glob($pattern) ?: [] as $infoPlist) {
            // plutil reads the file directly (no `defaults` preference-cache quirk).
            // Array argv → no shell, so no escaping; a failure yields empty output.
            $version = trim(Process::run([
                '/usr/bin/plutil', '-extract', 'CFBundleShortVersionString', 'raw', '-o', '-', $infoPlist,
            ])->output());

            if ($version !== '') {
                return $version;
            }
        }

        return null;
    }

    /**
     * Remove ShipIt's launchd job so it stops respawning. `bootout` is the modern
     * GUI-domain command; `remove` is the legacy fallback. Both are no-ops if the
     * job isn't loaded. Array argv means no shell is involved, so no escaping.
     */
    private function stopShipIt(): void
    {
        $appId = (string) config('nativephp.app_id', '');

        if ($appId === '') {
            return;
        }

        $label = $appId.'.ShipIt';
        $uid = function_exists('posix_getuid') ? posix_getuid() : getmyuid();

        Process::run(['/bin/launchctl', 'bootout', "gui/{$uid}/{$label}"]);
        Process::run(['/bin/launchctl', 'remove', $label]);
    }
}
