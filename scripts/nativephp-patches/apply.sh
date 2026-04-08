#!/usr/bin/env bash
#
# Apply Manuscript-specific customizations to the vendored NativePHP Electron plugin.
#
# Why this exists:
#   The release build flips between two electron paths in
#   ElectronServiceProvider::electronPath():
#     - Published path:  base_path('nativephp/electron/')
#     - Vendor fallback: vendor/nativephp/desktop/resources/electron/
#   The published path is only used when nativephp/electron/package.json exists.
#
#   We deliberately keep nativephp/electron/ untracked so the vendor fallback is
#   always used in CI. This script copies our three real customizations onto the
#   vendor scaffold so the OS-spellcheck bridge and the printToPDF fix make it
#   into the published builds.
#
# When to run:
#   - In CI, after `composer install` (which extracts the vendor) and before
#     `php artisan native:publish`.
#   - Locally only if you want to mirror CI behavior.
#
# Adding/updating a patch:
#   1. Edit the source file inside nativephp/electron/electron-plugin/...
#      (or wherever the customization lives in the working tree).
#   2. Copy it into scripts/nativephp-patches/files/<same relative path>.
#   3. Add the relative path to the `apply` calls below if it's a new file.
#   4. Commit both the patch file and any apply.sh changes.

set -euo pipefail

PATCHES_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/files"
VENDOR_DIR="vendor/nativephp/desktop/resources/electron"

if [ ! -d "$VENDOR_DIR" ]; then
    echo "error: vendor directory not found: $VENDOR_DIR" >&2
    echo "       run 'composer install' first." >&2
    exit 1
fi

apply() {
    local relpath="$1"
    local src="$PATCHES_DIR/$relpath"
    local dst="$VENDOR_DIR/$relpath"

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

echo "Applying Manuscript NativePHP patches to $VENDOR_DIR..."
apply "electron-plugin/src/preload/index.mts"
apply "electron-plugin/dist/preload/index.mjs"
apply "electron-plugin/dist/server/api/system.js"
echo "Done."
