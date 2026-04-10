#!/usr/bin/env bash
set -euo pipefail

# Promotes a draft GitHub release to published, after the human has verified
# the bundled binaries on macOS and Windows. See docs/releasing.md for the
# full release flow and the canonical verification checklist.
#
# Usage:
#   composer release:promote                # auto-detect latest draft
#   composer release:promote v1.2.3         # promote a specific tag
#   composer release:promote v1.2.3 --dry-run

# --- Colors/formatting helpers (mirror scripts/release.sh) ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BOLD='\033[1m'
DIM='\033[2m'
RESET='\033[0m'

check_mark="${GREEN}✓${RESET}"
cross_mark="${RED}✗${RESET}"

info() { echo -e "${BOLD}$1${RESET}"; }
success() { echo -e "${check_mark} $1"; }
fail() { echo -e "${cross_mark} $1"; }
warn() { echo -e "${YELLOW}⚠ $1${RESET}"; }
divider() { echo -e "${DIM}──────────────────────────────────────────${RESET}"; }

# --- Required artifacts ---
# These names are produced by electron-builder *after* the version-stripping
# sed in .github/workflows/publish.yml. If you change the strip rules, change
# this list too. The .yml manifests are used by the auto-updater — without
# them, existing users' updaters can't find the new release.
REQUIRED_ARTIFACTS=(
    "Manuscript-arm64.dmg"
    "Manuscript-x64.dmg"
    "Manuscript-setup.exe"
    "latest-mac.yml"
    "latest.yml"
)

TAG=""
DRY_RUN=0

# --- Argument parsing ---
parse_args() {
    for arg in "$@"; do
        case "$arg" in
            --dry-run)
                DRY_RUN=1
                ;;
            -h|--help)
                cat <<EOF
Usage: composer release:promote [<tag>] [--dry-run]

Promotes a draft GitHub release to published.

Arguments:
  <tag>       Tag to promote (e.g. v1.2.3). If omitted, auto-detects the
              most recent draft release.
  --dry-run   Print the gh command that would run, but don't execute it.
EOF
                exit 0
                ;;
            -*)
                fail "Unknown flag: ${arg}"
                exit 1
                ;;
            *)
                if [ -n "$TAG" ]; then
                    fail "Multiple tags supplied: '${TAG}' and '${arg}'"
                    exit 1
                fi
                TAG="$arg"
                ;;
        esac
    done
}

# --- Pre-flight ---
preflight_checks() {
    info "Pre-flight checks"
    divider

    if ! command -v gh &> /dev/null; then
        fail "GitHub CLI (gh) is not installed"
        echo "  Install it: https://cli.github.com"
        exit 1
    fi
    success "GitHub CLI available"

    if ! gh auth status &> /dev/null; then
        fail "GitHub CLI is not authenticated"
        echo "  Run: gh auth login"
        exit 1
    fi
    success "GitHub CLI authenticated"

    echo ""
}

# --- Tag resolution ---
resolve_tag() {
    info "Resolve target release"
    divider

    if [ -z "$TAG" ]; then
        # Find drafts in the most recent 20 releases.
        local drafts
        drafts=$(gh release list --limit 20 --json tagName,isDraft \
            --jq '[.[] | select(.isDraft)] | .[].tagName')

        local draft_count
        draft_count=$(echo "$drafts" | grep -c . || true)

        if [ "$draft_count" -eq 0 ]; then
            fail "No draft releases found in the most recent 20 releases"
            echo "  Did the publish workflow finish? Run: gh run list --workflow=publish.yml"
            exit 1
        fi

        if [ "$draft_count" -gt 1 ]; then
            fail "Multiple draft releases found — pass an explicit tag:"
            echo "$drafts" | sed 's/^/    /'
            echo ""
            echo "  Usage: composer release:promote <tag>"
            exit 1
        fi

        TAG="$drafts"
        success "Auto-detected draft: ${BOLD}${TAG}${RESET}"
    else
        success "Target tag: ${BOLD}${TAG}${RESET}"
    fi

    # Verify the release exists and is still a draft.
    local view_json
    if ! view_json=$(gh release view "$TAG" --json isDraft,name,tagName 2>&1); then
        fail "Release ${TAG} not found"
        echo "  ${view_json}"
        exit 1
    fi

    local is_draft
    is_draft=$(echo "$view_json" | jq -r '.isDraft')

    if [ "$is_draft" != "true" ]; then
        fail "Release ${TAG} is already published — nothing to promote"
        exit 1
    fi
    success "Release is still a draft"

    echo ""
}

# --- Artifact sanity check ---
check_artifacts() {
    info "Verify draft artifacts"
    divider

    local assets_json
    assets_json=$(gh release view "$TAG" --json assets)

    local missing=()
    for required in "${REQUIRED_ARTIFACTS[@]}"; do
        local size
        size=$(echo "$assets_json" | jq -r --arg n "$required" \
            '.assets[] | select(.name == $n) | .size')

        if [ -z "$size" ]; then
            fail "Missing: ${required}"
            missing+=("$required")
            continue
        fi

        if [ "$size" -eq 0 ]; then
            fail "Zero-byte: ${required}"
            missing+=("$required")
            continue
        fi

        # Human-readable size: bytes → MiB for anything over a meg, else bytes.
        local human
        if [ "$size" -ge 1048576 ]; then
            human=$(awk "BEGIN {printf \"%.1f MiB\", ${size}/1048576}")
        else
            human="${size} B"
        fi
        success "${required}  ${DIM}(${human})${RESET}"
    done

    if [ "${#missing[@]}" -gt 0 ]; then
        echo ""
        fail "${#missing[@]} required artifact(s) missing or empty"
        echo "  The publish workflow may still be running, or it may have failed."
        echo "  Check: gh run list --workflow=publish.yml"
        exit 1
    fi

    echo ""
}

# --- Verification checklist ---
print_checklist() {
    info "Verification checklist"
    divider
    cat <<'EOF'
  Before promoting, install the draft DMG / .exe and confirm on BOTH macOS
  and Windows that:

    [ ] App launches — window appears within ~5s, no crash dialog
    [ ] Loading screen completes — does not get stuck on /loading
    [ ] Open or create a book — dashboard opens (no 500, no blank screen)
    [ ] Edit a chapter — autosave settles; reload; content survives
    [ ] Settings → AI provider — existing config intact, API key still works
    [ ] Updater check — no error toast / no console errors on startup

  See docs/releasing.md for the canonical version of this checklist and
  what each step is actually testing.

  If any step fails: DO NOT PROMOTE. Investigate, ship a follow-up tag.
EOF
    echo ""
}

# --- Confirmation gate ---
confirm_promotion() {
    info "Confirm"
    divider
    echo "  About to promote ${BOLD}${TAG}${RESET} from draft to PUBLISHED."
    echo "  Existing users on the auto-updater will pick this up on next launch."
    echo ""
    local response
    read -rp "  Type 'promote' to publish: " response
    if [ "$response" != "promote" ]; then
        warn "Cancelled — release left as draft."
        exit 0
    fi
    echo ""
}

# --- Promote ---
promote_release() {
    info "Promote"
    divider

    local cmd=(gh release edit "$TAG" --draft=false --title "Manuscript ${TAG}")

    if [ "$DRY_RUN" -eq 1 ]; then
        warn "Dry run — would execute:"
        echo "    ${cmd[*]}"
        echo ""
        return
    fi

    if "${cmd[@]}" &> /dev/null; then
        success "Promoted ${BOLD}${TAG}${RESET} to published"
    else
        fail "gh release edit failed"
        "${cmd[@]}" || true
        exit 1
    fi
    echo ""
}

# --- Summary ---
show_summary() {
    info "Done"
    divider
    if [ "$DRY_RUN" -eq 1 ]; then
        echo "  Dry run complete. Re-run without --dry-run to actually promote."
    else
        echo "  ${TAG} is now live."
        echo ""
        echo "  Rollback (within minutes, before users update):"
        echo "    ${DIM}gh release edit ${TAG} --draft=true${RESET}"
    fi
    echo ""
}

main() {
    parse_args "$@"

    echo ""
    info "🚀 Promote Manuscript release"
    echo ""

    preflight_checks
    resolve_tag
    check_artifacts
    print_checklist
    confirm_promotion
    promote_release
    show_summary
}

main "$@"
