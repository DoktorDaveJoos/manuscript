#!/usr/bin/env bash
#
# Apply Manuscript-specific customizations to the vendored NativePHP desktop package.
#
# Why this exists:
#   The release build uses NativePHP's package directly (vendor fallback in
#   ElectronServiceProvider::electronPath()), so every customization we need has
#   to be re-applied to vendor on every CI run. We do this with whole-file copies
#   instead of unified-diff patches (cweagans/composer-patches) because diffs are
#   line-ending- and context-sensitive: on the Windows runner `git apply` runs
#   under core.autocrlf=true and the patch silently fails to apply, killing the
#   build. A plain `cp` has none of that fragility.
#
# Paths are relative to the package root (vendor/nativephp/desktop) so files both
# inside resources/electron/ and elsewhere in the package (e.g. src/Drivers/...)
# can be patched by the same mechanism.
#
# When to run:
#   - In CI, after `composer install` (which extracts the vendor) and before
#     `php artisan native:publish`. Both the macOS and Windows jobs run it.
#   - Locally only if you want to mirror CI behavior.
#
# Adding/updating a patch:
#   1. Edit the customization where it lives on disk.
#   2. Copy the file into scripts/nativephp-patches/files/<package-relative path>.
#   3. Add the relative path to the `apply` calls below if it's a new file.
#   4. Commit the file alongside any apply.sh change.

set -euo pipefail

PATCHES_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/files"
PACKAGE_DIR="vendor/nativephp/desktop"

if [ ! -d "$PACKAGE_DIR" ]; then
    echo "error: package directory not found: $PACKAGE_DIR" >&2
    echo "       run 'composer install' first." >&2
    exit 1
fi

apply() {
    local relpath="$1"
    local src="$PATCHES_DIR/$relpath"
    local dst="$PACKAGE_DIR/$relpath"

    if [ ! -f "$src" ]; then
        echo "error: patch file missing: $src" >&2
        exit 1
    fi
    if [ ! -f "$dst" ]; then
        echo "error: vendor file missing: $dst" >&2
        echo "       the upstream layout may have changed; regenerate patches." >&2
        exit 1
    fi

    cp "$src" "$dst"
    echo "  patched $relpath"
}

echo "Applying Manuscript NativePHP patches to $PACKAGE_DIR..."
# Renderer spellcheck bridge + printToPDF fix (ship via the compiled dist).
apply "resources/electron/electron-plugin/src/preload/index.mts"
apply "resources/electron/electron-plugin/dist/preload/index.mjs"
apply "resources/electron/electron-plugin/dist/server/api/system.js"
# Startup resilience: single-instance lock + bounded relaunch recovery
# (index.js), strict startup requests (server/utils.js), durable window-load
# tracking (server/api/window.js), user-controlled updater startup checks
# (index.js), and the API port-bind retry (server/api.js).
# These patch the COMPILED dist
# because the publish build runs `electron-vite build`, never `plugin:build`/tsc,
# so the package's src/*.ts is never recompiled — only dist/ is loaded at runtime.
apply "resources/electron/electron-plugin/dist/index.js"
apply "resources/electron/electron-plugin/dist/server/utils.js"
apply "resources/electron/electron-plugin/dist/server/api/window.js"
apply "resources/electron/electron-plugin/dist/server/api.js"
# Child-process startup waits for the wrapped command's real spawn event before
# acknowledging success. The API returns serializable process data (never
# Electron's UtilityProcess object), and PHP rejects explicit startup errors so
# bounded queue-worker retries have a truthful failure signal.
apply "resources/electron/electron-plugin/src/server/childProcess.ts"
apply "resources/electron/electron-plugin/dist/server/childProcess.js"
apply "resources/electron/electron-plugin/src/server/api/childProcess.ts"
apply "resources/electron/electron-plugin/dist/server/api/childProcess.js"
# Launch speed and recovery: version-guard `artisan optimize` on normal starts,
# but on the bounded recovery launch remove only generated Laravel caches and
# force a rebuild. Bootstrap commands receive the complete live NativePHP
# runtime tuple after the Electron control API has selected its port.
apply "resources/electron/electron-plugin/dist/server/php.js"
# Auto-update: disable silent install-on-quit (autoInstallOnAppQuit=false) so a
# downloaded update never stages a non-relaunching Squirrel install-on-quit that
# can loop against a reopened instance ("App Still Running" / ShipIt respawn
# storm). Updates apply only via the explicit Install action, which relaunches.
apply "resources/electron/electron-plugin/dist/server/api/autoUpdater.js"
# Speech input: microphone entitlement for getUserMedia under hardened runtime
# (NSMicrophoneUsageDescription already ships in NativePHP's builder config).
apply "resources/electron/build/entitlements.mac.plist"
# electronPath() published-project detection (PHP, ships as-is — not compiled).
apply "src/Drivers/Electron/ElectronServiceProvider.php"
# Atomically replace every cached per-launch NativePHP value (running flag,
# storage/database paths, API URL, and secret), then gate queue workers behind
# database readiness with bounded retries that never abort the window boot.
apply "src/NativeServiceProvider.php"
# Surface Electron child-process startup errors to PHP instead of hydrating a
# false-success object that prevents the queue retry policy from running.
apply "src/ChildProcess.php"
echo "Done."
