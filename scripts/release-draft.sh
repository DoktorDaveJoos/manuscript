#!/usr/bin/env bash
set -euo pipefail

# Keeps every artifact of a tagged build in exactly ONE draft GitHub release.
#
# Why this exists:
#   electron-builder's GitHub publisher looks the release up (GET /releases)
#   and creates it when the lookup misses. Its PublishManager awaits
#   createPublisher() between the publisher-cache check and the cache write, so
#   artifacts that finish together (a zip and its blockmap, a dmg and a zip)
#   each get their own publisher, each sees no release, and each POSTs a draft.
#   The result is two drafts with adjacent ids, and every later upload lands in
#   whichever of them GitHub happens to list first. Creating the draft before
#   any build means every publisher finds it and none is ever created.
#
#   Each `native:publish mac <arch>` run also writes a latest-mac.yml listing
#   only its own arch, and the x64 upload replaces the arm64 one. The manifest
#   has to be rebuilt from both arches once the x64 build is done.
#
# Usage:
#   scripts/release-draft.sh prepare <tag>
#   scripts/release-draft.sh mac-manifest <dist-dir> <version> <output> <electron-builder latest-mac.yml>...
#   scripts/release-draft.sh verify <tag>

# --- Colors/formatting helpers (mirror scripts/release.sh) ---
RED='\033[0;31m'
GREEN='\033[0;32m'
BOLD='\033[1m'
DIM='\033[2m'
RESET='\033[0m'

check_mark="${GREEN}✓${RESET}"
cross_mark="${RED}✗${RESET}"

info() { echo -e "${BOLD}$1${RESET}"; }
success() { echo -e "${check_mark} $1"; }
fail() { echo -e "${cross_mark} $1"; }
divider() { echo -e "${DIM}──────────────────────────────────────────${RESET}"; }

# --- Release contents ---
# Names come from electron-builder after the version-stripping sed in
# .github/workflows/publish.yml. If you change the strip rules, change these
# too. The .yml manifests drive the auto-updater: without them, existing users
# never see the release.
REQUIRED_ARTIFACTS=(
    "Manuscript-arm64.dmg"
    "Manuscript-x64.dmg"
    "Manuscript-setup.exe"
    "latest-mac.yml"
    "latest.yml"
)

# electron-builder's own dual-arch order: zips before dmgs, x64 before arm64
# (its Arch enum), so the legacy path/sha512 fields name the x64 zip.
MAC_UPDATE_FILES=(
    "Manuscript-x64.zip"
    "Manuscript-arm64.zip"
    "Manuscript-x64.dmg"
    "Manuscript-arm64.dmg"
)

WINDOWS_UPDATE_FILES=(
    "Manuscript-setup.exe"
)

POLL_ATTEMPTS="${RELEASE_DRAFT_POLL_ATTEMPTS:-12}"
POLL_SECONDS="${RELEASE_DRAFT_POLL_SECONDS:-5}"

usage() {
    cat <<EOF
Usage:
  scripts/release-draft.sh prepare <tag>
  scripts/release-draft.sh mac-manifest <dist-dir> <version> <output> <electron-builder latest-mac.yml>...
  scripts/release-draft.sh verify <tag>
EOF
    exit 1
}

require_tag() {
    if ! [[ "${1:-}" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        fail "Not a release tag: '${1:-}' (expected vMAJOR.MINOR.PATCH)"
        exit 1
    fi
}

count_lines() {
    if [ -z "$1" ]; then
        echo 0
    else
        grep -c . <<< "$1"
    fi
}

human_size() {
    if [ "$1" -ge 1048576 ]; then
        awk "BEGIN {printf \"%.1f MiB\", $1/1048576}"
    else
        echo "$1 B"
    fi
}

# Prints "<id> <draft>" for every release of the tag, across every page.
releases_for_tag() {
    gh api --paginate "repos/{owner}/{repo}/releases?per_page=100" \
        | jq -r --arg tag "$1" '.[] | select(.tag_name == $tag) | "\(.id) \(.draft)"'
}

# electron-builder only reads the first page of GET /releases. A draft it
# can't see there is a draft it will create again.
visible_to_electron_builder() {
    gh api "repos/{owner}/{repo}/releases" \
        | jq -e --arg tag "$1" --argjson id "$2" 'any(.[]; .id == $id and .tag_name == $tag and .draft)' > /dev/null
}

# Prints "<url> <sha512> <size>" for every files: entry of an update manifest.
manifest_entries() {
    awk '
        /^[[:space:]]*- url:/ { url = $NF; sha = ""; next }
        url != "" && /^[[:space:]]*sha512:/ { sha = $NF; next }
        url != "" && /^[[:space:]]*size:/ { print url, sha, $NF; url = "" }
    ' "$1"
}

# --- prepare ---
prepare_release() {
    local tag="$1"
    require_tag "$tag"

    info "Prepare draft release ${tag}"
    divider

    local matches count
    matches=$(releases_for_tag "$tag")
    count=$(count_lines "$matches")

    if [ "$count" -gt 1 ]; then
        fail "${tag} already has ${count} releases — keep one draft, delete the rest:"
        echo "$matches" | sed 's/^/    /'
        exit 1
    fi

    if [ "$count" -eq 1 ] && [ "${matches##* }" != "true" ]; then
        fail "Release ${tag} is already published — refusing to upload over it"
        exit 1
    fi

    if [ "$count" -eq 0 ]; then
        gh release create "$tag" --draft --verify-tag --title "Manuscript ${tag}" --notes "" > /dev/null
        success "Created draft release ${tag}"
    else
        success "Reusing the existing draft for ${tag}"
    fi

    local attempt
    for ((attempt = 1; attempt <= POLL_ATTEMPTS; attempt++)); do
        matches=$(releases_for_tag "$tag")
        count=$(count_lines "$matches")

        if [ "$count" -gt 1 ]; then
            fail "${tag} now has ${count} releases — something else created one:"
            echo "$matches" | sed 's/^/    /'
            exit 1
        fi

        if [ "$count" -eq 1 ] && [ "${matches##* }" = "true" ] && visible_to_electron_builder "$tag" "${matches%% *}"; then
            success "Draft ${matches%% *} is the only release for ${tag}"
            echo ""
            return 0
        fi

        if [ "$attempt" -lt "$POLL_ATTEMPTS" ]; then
            sleep "$POLL_SECONDS"
        fi
    done

    fail "The draft for ${tag} never showed up on the first page of GET /releases"
    echo "  electron-builder would not find it and would create its own draft."
    exit 1
}

# --- mac-manifest ---
write_mac_manifest() {
    local dist="$1" version="$2" output="$3"
    shift 3

    info "macOS update manifest"
    divider

    local name file sha size
    local computed=""
    for name in "${MAC_UPDATE_FILES[@]}"; do
        file="${dist}/${name}"
        if [ ! -f "$file" ]; then
            fail "Missing build output: ${file}"
            exit 1
        fi

        sha=$(openssl dgst -sha512 -binary "$file" | openssl base64 -A)
        size=$(wc -c < "$file" | tr -d '[:space:]')
        computed+="${name} ${sha} ${size}"$'\n'
    done

    # Every entry electron-builder published must describe exactly the file we
    # hashed, and between them its per-arch manifests must cover all four.
    local manifest entry
    local covered=""
    for manifest in "$@"; do
        if [ ! -f "$manifest" ]; then
            fail "Missing electron-builder manifest: ${manifest}"
            exit 1
        fi

        while IFS= read -r entry; do
            if ! grep -Fxq "$entry" <<< "$computed"; then
                fail "${manifest} lists ${entry%% *} with a sha512/size that doesn't match ${dist}"
                exit 1
            fi
            covered+="${entry%% *}"$'\n'
        done < <(manifest_entries "$manifest")
    done

    for name in "${MAC_UPDATE_FILES[@]}"; do
        if ! grep -Fxq "$name" <<< "$covered"; then
            fail "No electron-builder manifest lists ${name} — did both arches build?"
            exit 1
        fi
    done

    local first_name first_sha
    read -r first_name first_sha _ <<< "$computed"

    {
        echo "version: ${version}"
        echo "files:"
        while read -r name sha size; do
            [ -n "$name" ] || continue
            echo "  - url: ${name}"
            echo "    sha512: ${sha}"
            echo "    size: ${size}"
        done <<< "$computed"
        echo "path: ${first_name}"
        echo "sha512: ${first_sha}"
        echo "releaseDate: '$(date -u +%Y-%m-%dT%H:%M:%S.000Z)'"
    } > "$output"

    success "Wrote ${output} covering ${MAC_UPDATE_FILES[*]}"
    echo ""
}

# --- verify ---
# Checks that every file listed for this manifest is in it with the size of
# the uploaded asset, and that the manifest is for this version.
check_update_manifest() {
    local version="$1" assets="$2" manifest_name="$3"
    shift 3

    local asset_id content
    asset_id=$(awk -v n="$manifest_name" '$2 == n { print $1 }' <<< "$assets")
    content=$(gh api -H "Accept: application/octet-stream" "repos/{owner}/{repo}/releases/assets/${asset_id}")

    if ! grep -Fxq "version: ${version}" <<< "$content"; then
        fail "${manifest_name} is not for version ${version}"
        return 1
    fi

    local entries name listed_size asset_size
    local problems=0
    entries=$(manifest_entries /dev/stdin <<< "$content")
    for name in "$@"; do
        listed_size=$(awk -v n="$name" '$1 == n { print $3 }' <<< "$entries")
        asset_size=$(awk -v n="$name" '$2 == n { print $4 }' <<< "$assets")

        if [ -z "$listed_size" ]; then
            fail "${manifest_name} does not list ${name} — the auto-updater can't offer it"
            problems=$((problems + 1))
        elif [ "$listed_size" != "$asset_size" ]; then
            fail "${manifest_name} lists ${name} at ${listed_size} bytes, the uploaded asset is ${asset_size:-missing}"
            problems=$((problems + 1))
        else
            success "${manifest_name} covers ${name}"
        fi
    done

    [ "$problems" -eq 0 ]
}

verify_release() {
    local tag="$1"
    require_tag "$tag"

    info "Verify draft release ${tag}"
    divider

    local matches count
    matches=$(releases_for_tag "$tag")
    count=$(count_lines "$matches")

    if [ "$count" -ne 1 ]; then
        fail "Expected exactly one release for ${tag}, found ${count}"
        if [ "$count" -gt 1 ]; then
            echo "$matches" | sed 's/^/    /'
            echo "  Move every asset into one draft and delete the others."
        fi
        exit 1
    fi

    local release_id="${matches%% *}"
    if [ "${matches##* }" != "true" ]; then
        fail "Release ${tag} is already published"
        exit 1
    fi
    success "One release for ${tag}: draft ${release_id}"

    local assets
    assets=$(gh api --paginate "repos/{owner}/{repo}/releases/${release_id}/assets?per_page=100" \
        | jq -r '.[] | "\(.id) \(.name) \(.state) \(.size)"')

    local problems=0 name line state size
    for name in "${REQUIRED_ARTIFACTS[@]}"; do
        line=$(awk -v n="$name" '$2 == n' <<< "$assets")
        if [ -z "$line" ]; then
            fail "Missing: ${name}"
            problems=$((problems + 1))
            continue
        fi

        read -r _ _ state size <<< "$line"
        if [ "$size" -eq 0 ]; then
            fail "Zero-byte: ${name}"
            problems=$((problems + 1))
            continue
        fi

        success "${name}  ${DIM}($(human_size "$size"))${RESET}"
    done

    # GitHub keeps an interrupted upload as a "starter" asset: hidden from the
    # releases list, yet it blocks re-uploading that name.
    while read -r _ name state _; do
        if [ -n "$name" ] && [ "$state" != "uploaded" ]; then
            fail "Not fully uploaded (${state}): ${name}"
            problems=$((problems + 1))
        fi
    done <<< "$assets"

    if [ "$problems" -gt 0 ]; then
        echo ""
        fail "${problems} problem(s) with the draft's artifacts"
        exit 1
    fi

    local version="${tag#v}"
    if ! check_update_manifest "$version" "$assets" "latest-mac.yml" "${MAC_UPDATE_FILES[@]}" \
        || ! check_update_manifest "$version" "$assets" "latest.yml" "${WINDOWS_UPDATE_FILES[@]}"; then
        echo ""
        fail "Update manifests don't cover every platform"
        exit 1
    fi

    echo ""
}

main() {
    local command="${1:-}"
    shift || true

    case "$command" in
        prepare)
            [ "$#" -eq 1 ] || usage
            prepare_release "$1"
            ;;
        mac-manifest)
            [ "$#" -ge 4 ] || usage
            write_mac_manifest "$@"
            ;;
        verify)
            [ "$#" -eq 1 ] || usage
            verify_release "$1"
            ;;
        *)
            usage
            ;;
    esac
}

if [[ "${BASH_SOURCE[0]-$0}" == "$0" ]]; then
    main "$@"
fi
