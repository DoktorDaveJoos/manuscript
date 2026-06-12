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
# Startup resilience: single-instance lock + startup-failure dialog (index.js)
# and the API port-bind retry (server/api.js). These patch the COMPILED dist
# because the publish build runs `electron-vite build`, never `plugin:build`/tsc,
# so the package's src/*.ts is never recompiled — only dist/ is loaded at runtime.
apply "resources/electron/electron-plugin/dist/index.js"
apply "resources/electron/electron-plugin/dist/server/api.js"
# Launch speed: version-guard the per-launch `artisan optimize` (server/php.js)
# so it only runs on the first launch after an install/update, like migrate.
# The per-launch config values it used to refresh are healed at request time
# by AppServiceProvider::healStaleNativePhpConfig.
apply "resources/electron/electron-plugin/dist/server/php.js"
# Speech input: microphone entitlement for getUserMedia under hardened runtime
# (NSMicrophoneUsageDescription already ships in NativePHP's builder config).
apply "resources/electron/build/entitlements.mac.plist"
# electronPath() published-project detection (PHP, ships as-is — not compiled).
apply "src/Drivers/Electron/ElectronServiceProvider.php"
echo "Done."
