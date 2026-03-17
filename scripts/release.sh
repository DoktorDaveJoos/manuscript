#!/usr/bin/env bash
set -euo pipefail

# --- Colors/formatting helpers ---
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

# --- Pre-flight checks ---
preflight_checks() {
    info "Pre-flight checks"
    divider

    # 1. Working tree clean
    if [ -n "$(git status --porcelain)" ]; then
        fail "Working tree is not clean"
        echo "  Commit or stash your changes before releasing."
        exit 1
    fi
    success "Working tree is clean"

    # 2. On main branch
    local branch
    branch=$(git branch --show-current)
    if [ "$branch" != "main" ]; then
        fail "Not on main branch (currently on ${branch})"
        echo "  Switch to main before releasing."
        exit 1
    fi
    success "On main branch"

    # 3. Up-to-date with remote
    git fetch origin main --quiet
    local behind
    behind=$(git rev-list HEAD..origin/main --count)
    if [ "$behind" -gt 0 ]; then
        fail "Branch is ${behind} commit(s) behind origin/main"
        echo "  Pull the latest changes before releasing."
        exit 1
    fi
    success "Up-to-date with origin/main"

    echo ""
}

# --- Current state ---
show_current_state() {
    info "Current state"
    divider

    local latest_tag
    latest_tag=$(git describe --tags --abbrev=0 2>/dev/null || echo "")

    if [ -z "$latest_tag" ]; then
        warn "No existing tags found. This will be the first release."
        LATEST_TAG=""
        COMMIT_COUNT=$(git rev-list HEAD --count)
        echo ""
        return
    fi

    LATEST_TAG="$latest_tag"

    local tag_date
    tag_date=$(git log -1 --format=%ai "$latest_tag" | cut -d' ' -f1)

    local commit_count
    commit_count=$(git rev-list "${latest_tag}..HEAD" --count)
    COMMIT_COUNT="$commit_count"

    echo -e "  Latest tag:    ${BOLD}${latest_tag}${RESET} (${tag_date})"
    echo -e "  Commits since: ${BOLD}${commit_count}${RESET}"
    echo ""

    if [ "$commit_count" -gt 0 ]; then
        echo -e "  ${DIM}Recent commits:${RESET}"
        git log "${latest_tag}..HEAD" --oneline --no-decorate | while IFS= read -r line; do
            echo "    $line"
        done
        echo ""
    fi

    if [ "$commit_count" -eq 0 ]; then
        warn "No commits since ${latest_tag}. Nothing to release."
        exit 0
    fi
}

# --- Quality gate ---
run_quality_gate() {
    info "Quality gate"
    divider

    local checks=(
        "composer lint:check|PHP lint (Pint)"
        "npm run lint:check|JS/TS lint (ESLint)"
        "npm run format:check|Format check (Prettier)"
        "npm run types:check|Type check (TypeScript)"
        "php artisan test --compact|Tests (Pest)"
        "npm run build|Build (Vite)"
    )

    for entry in "${checks[@]}"; do
        local cmd="${entry%%|*}"
        local label="${entry##*|}"

        if eval "$cmd" > /dev/null 2>&1; then
            success "$label"
        else
            fail "$label"
            echo ""
            echo "  Quality gate failed. Fix the issue and try again."
            echo "  Run: ${DIM}${cmd}${RESET}"
            exit 1
        fi
    done

    echo ""
}

# --- Version selection ---
select_version() {
    info "Version selection"
    divider

    local current_version
    if [ -n "$LATEST_TAG" ]; then
        current_version="${LATEST_TAG#v}"
    else
        current_version="0.0.0"
    fi

    # Parse semver components
    local major minor patch
    IFS='.' read -r major minor patch <<< "$current_version"

    local bump_patch="${major}.${minor}.$((patch + 1))"
    local bump_minor="${major}.$((minor + 1)).0"
    local bump_major="$((major + 1)).0.0"

    echo "  Current version: ${BOLD}v${current_version}${RESET}"
    echo ""
    echo "  [1] Patch  → v${bump_patch}"
    echo "  [2] Minor  → v${bump_minor}"
    echo "  [3] Major  → v${bump_major}"
    echo "  [4] Custom"
    echo ""

    local choice
    read -rp "  Select version bump [1-4]: " choice

    case "$choice" in
        1) NEW_VERSION="$bump_patch" ;;
        2) NEW_VERSION="$bump_minor" ;;
        3) NEW_VERSION="$bump_major" ;;
        4)
            read -rp "  Enter version (e.g. 1.2.3): " NEW_VERSION
            # Strip v prefix if provided
            NEW_VERSION="${NEW_VERSION#v}"
            ;;
        *)
            fail "Invalid selection"
            exit 1
            ;;
    esac

    # Validate semver format
    if ! [[ "$NEW_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        fail "Invalid semver format: ${NEW_VERSION}"
        echo "  Expected format: MAJOR.MINOR.PATCH (e.g. 1.2.3)"
        exit 1
    fi

    # Check tag doesn't already exist
    if git tag -l "v${NEW_VERSION}" | grep -q .; then
        fail "Tag v${NEW_VERSION} already exists"
        exit 1
    fi

    echo ""
    success "Version set to ${BOLD}v${NEW_VERSION}${RESET}"
    echo ""
}

# --- Changelog generation ---
generate_changelog() {
    info "Changelog"
    divider

    local changelog_file="CHANGELOG.md"
    local today
    today=$(date +%Y-%m-%d)

    # Collect commits grouped by type
    local features="" fixes="" other=""
    local log_range

    if [ -n "$LATEST_TAG" ]; then
        log_range="${LATEST_TAG}..HEAD"
    else
        log_range="HEAD"
    fi

    while IFS= read -r line; do
        local hash="${line%% *}"
        local short_hash="${hash:0:7}"
        local message="${line#* }"

        if [[ "$message" =~ ^feat:\ * ]]; then
            local clean="${message#feat: }"
            clean="${clean#feat:}"
            features="${features}\n- ${clean} (${short_hash})"
        elif [[ "$message" =~ ^fix:\ * ]]; then
            local clean="${message#fix: }"
            clean="${clean#fix:}"
            fixes="${fixes}\n- ${clean} (${short_hash})"
        else
            # Strip any conventional commit prefix for cleaner display
            local clean="$message"
            clean=$(echo "$clean" | sed -E 's/^(chore|docs|refactor|style|perf|test|ci|build|revert)(\(.+\))?: //')
            other="${other}\n- ${clean} (${short_hash})"
        fi
    done < <(git log "$log_range" --oneline --no-decorate)

    # Build the new section
    local section="## v${NEW_VERSION} (${today})"

    if [ -n "$features" ]; then
        section="${section}\n\n### Features\n${features}"
    fi
    if [ -n "$fixes" ]; then
        section="${section}\n\n### Fixes\n${fixes}"
    fi
    if [ -n "$other" ]; then
        section="${section}\n\n### Other Changes\n${other}"
    fi

    # Write to CHANGELOG.md
    if [ -f "$changelog_file" ]; then
        # Prepend after the first line (# Changelog header)
        local existing
        existing=$(cat "$changelog_file")
        local header="# Changelog"
        local rest="${existing#"$header"}"
        echo -e "${header}\n\n${section}\n${rest}" > "$changelog_file"
    else
        echo -e "# Changelog\n\n${section}" > "$changelog_file"
    fi

    success "Updated ${changelog_file}"

    git add "$changelog_file"
    git commit -m "chore: update changelog for v${NEW_VERSION}" --quiet

    success "Committed changelog update"
    echo ""
}

# --- Tag + push ---
create_and_push_tag() {
    info "Create tag"
    divider

    git tag -a "v${NEW_VERSION}" -m "Release v${NEW_VERSION}"
    success "Created tag ${BOLD}v${NEW_VERSION}${RESET}"
    echo ""

    # Summary
    info "Release summary"
    divider
    echo "  Version:  v${NEW_VERSION}"
    echo "  Commits:  ${COMMIT_COUNT}"
    echo "  Checks:   all passed"
    echo ""

    # Confirm push
    local confirm
    read -rp "  Push v${NEW_VERSION} to origin? This will trigger the CI build. [y/N] " confirm

    if [[ "$confirm" =~ ^[Yy]$ ]]; then
        git push origin main --quiet
        git push origin "v${NEW_VERSION}" --quiet
        echo ""
        success "Pushed ${BOLD}v${NEW_VERSION}${RESET} to origin"
    else
        echo ""
        warn "Push skipped. To push manually:"
        echo "    git push origin main && git push origin v${NEW_VERSION}"
    fi

    echo ""
}

# --- Main ---
main() {
    echo ""
    info "🚀 Manuscript Release"
    echo ""

    preflight_checks
    show_current_state
    run_quality_gate
    select_version
    generate_changelog
    create_and_push_tag

    success "Done!"
    echo ""
}

main
