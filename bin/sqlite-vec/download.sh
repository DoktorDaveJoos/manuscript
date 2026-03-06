#!/usr/bin/env bash
#
# Downloads sqlite-vec loadable extension binaries from GitHub releases.
# Usage: ./download.sh [version]
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VERSION="${1:-$(cat "$SCRIPT_DIR/VERSION" | tr -d '[:space:]')}"
BASE_URL="https://github.com/asg017/sqlite-vec/releases/download/v${VERSION}"

echo "Downloading sqlite-vec v${VERSION}..."

download_platform() {
    local platform="$1"
    local archive="$2"
    local ext="$3"
    local url="${BASE_URL}/${archive}"
    local dir="${SCRIPT_DIR}/${platform}"

    echo "  ${platform}..."
    mkdir -p "$dir"

    curl -sL "$url" | tar xz -C "$dir" "vec0.${ext}"

    if [ -f "$dir/vec0.${ext}" ]; then
        echo "    done: vec0.${ext}"
    else
        echo "    FAILED to extract vec0.${ext}" >&2
        exit 1
    fi
}

download_platform "macos-arm64" "sqlite-vec-${VERSION}-loadable-macos-aarch64.tar.gz" "dylib"
download_platform "macos-x64"   "sqlite-vec-${VERSION}-loadable-macos-x86_64.tar.gz"  "dylib"
download_platform "linux-x64"   "sqlite-vec-${VERSION}-loadable-linux-x86_64.tar.gz"  "so"
download_platform "linux-arm64" "sqlite-vec-${VERSION}-loadable-linux-aarch64.tar.gz"  "so"
download_platform "win-x64"     "sqlite-vec-${VERSION}-loadable-windows-x86_64.tar.gz" "dll"

echo "$VERSION" > "$SCRIPT_DIR/VERSION"
echo "Done. All binaries downloaded to ${SCRIPT_DIR}/"
