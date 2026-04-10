# Releasing Manuscript

This is the canonical reference for cutting and shipping a release.
The flow is **two scripts with a manual gate in between**: CI builds and
drafts the artifacts, you install them on real hardware, then a second
script flips the draft to published.

## Why a manual gate?

Tests run on Ubuntu against `:memory:` SQLite. Browser tests use Pest 4 +
Playwright but route through Laravel's request cycle — **none of the
automated test suite ever executes the bundled Electron/NativePHP binary**.
The risky surface lives precisely in that gap:

- The runtime `database/nativephp.sqlite` and its sqlite-vec extension
- `opcache.enable_cli=1` / JIT settings inside the bundled CLI PHP
- Vendored NativePHP electron patches (`scripts/nativephp-patches/`)
- `config:cache` / `route:cache` baked at build time
- Code signing, notarization, native binary inclusion
- The auto-updater pointing at the right repo + channel

A 2-minute install-and-click ritual on each OS catches almost everything
the test suite can't. The promote script enforces it.

## Full release flow

```
1. composer release            (local — quality gate, tag, push)
2. publish.yml in CI           (build, sign, notarize, upload to draft)
3. Install + verify draft      (you, on macOS AND Windows hardware)
4. composer release:promote    (local — flips draft to published)
```

### 1. `composer release`

Runs on your machine, on the `dev` branch:

- Pre-flight: clean working tree, on `dev`, up-to-date with origin
- Quality gate: Pint, ESLint, Prettier, TypeScript, Pest, `npm run build`
- Version selection (patch / minor / major / custom)
- Generates a `CHANGELOG.md` section, commits it
- Creates a PR `dev` → `main`, merges it, tags `main` with `v<version>`,
  pushes the tag

The tag push triggers `publish.yml` in GitHub Actions.

### 2. `publish.yml` in CI

Three parallel build steps run on the tag push:

- **macOS arm64** — `php artisan native:publish mac arm64` produces a signed,
  notarized DMG and uploads it to a **draft** release
- **macOS x64** — same for Intel Macs
- **Windows x64** — `php artisan native:publish win x64` produces an installer
  and uploads it to the same draft

`GITHUB_RELEASE_TYPE=draft` is set on every `native:publish` invocation, so
nothing about this step is destructive — the draft is invisible to users.

There is **no auto-publish job**. The release stays a draft until you
explicitly promote it.

Monitor with `gh run watch`.

### 3. Install + verify the draft

Once CI finishes:

```bash
gh release view v<version> --json assets --jq '.assets[].name'
```

You should see at minimum:
- `Manuscript-arm64.dmg`
- `Manuscript-x64.dmg`
- `Manuscript-setup.exe`
- `latest-mac.yml`  (auto-updater manifest for macOS)
- `latest.yml`  (auto-updater manifest for Windows)

Download the DMG matching your Mac's architecture and the Windows installer:

```bash
gh release download v<version> -p 'Manuscript-arm64.dmg'
gh release download v<version> -p 'Manuscript-setup.exe'
```

Install on each OS and walk the **verification checklist** below.

### 4. `composer release:promote`

Once both binaries pass the checklist:

```bash
composer release:promote v<version>
```

Or, if there's exactly one draft release in the recent history:

```bash
composer release:promote
```

The script:
1. Verifies the gh CLI is authenticated
2. Resolves the target tag (auto-detects the only draft, or uses your arg)
3. Sanity-checks all required artifacts are present and non-empty
4. Prints the verification checklist
5. Requires you to type `promote` to confirm
6. Runs `gh release edit <tag> --draft=false --title "Manuscript <tag>"`

Pass `--dry-run` to see exactly what would happen without flipping anything.

## Verification checklist

Run these on **both** macOS (against the matching arch DMG) and Windows
before promoting. If any step fails: **do not promote**, investigate, ship
a follow-up tag.

| Check | What it actually tests |
|---|---|
| **App launches** — window appears within ~5s, no crash dialog | Binary integrity, code signing, notarization, PHP CLI startup, vendor patches |
| **Loading screen completes** — does not get stuck on `/loading` | `config:cache` was baked with the right env, route cache is consistent with the bundled JS |
| **Open or create a book** — dashboard opens (no 500, no blank screen) | Laravel boot, `database/nativephp.sqlite` reachable, `sqlite-vec` extension loaded, schema present |
| **Edit a chapter** — type, autosave settles, reload, content survives | Runtime DB write path works, schema matches what the app expects |
| **Settings → AI provider** — existing config intact, API key still works | `APP_KEY` in the bundle still decrypts the user's stored credentials, nativephp.sqlite migrations ran cleanly on upgrade |
| **Updater check** — no error toast, no console errors on startup mentioning the GitHub releases endpoint | Auto-updater is wired to the right repo + channel, `latest-mac.yml` / `latest.yml` are reachable |

## Rollback

If a problem surfaces in the few minutes after promotion (before users
have updated):

```bash
gh release edit v<version> --draft=true
```

This makes the release invisible to the auto-updater again. Existing users
who already updated still have the bad version installed — but no new
users will get it.

If you also need to retag (e.g. you tagged the wrong commit):

```bash
gh release delete v<version> --yes
git push --delete origin v<version>
git tag -d v<version>
```

…then start over from `composer release`.

## What this gate catches and what it doesn't

**Catches:**
- App won't launch on either OS (signing, notarization, native deps, vendor patches)
- App opens but core flows broken (DB schema, sqlite-vec missing, baked-wrong config)
- The auto-updater is misconfigured (no manifest, wrong repo)
- Truncated / zero-byte uploads (artifact sanity check in the promote script)

**Does NOT catch:**
- Silent regressions in features you didn't test in the 2-minute click-through
- Bugs that only manifest on hardware/OS versions you don't own
- Performance regressions
- Anything timing-sensitive that needs a longer-running session

For deeper safety, click around longer, ask a friend to dogfood the draft,
or wait a day before promoting.
