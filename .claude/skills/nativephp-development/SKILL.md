---
name: nativephp-development
description: Use when creating or running migrations, touching NativePHP config or providers, modifying the publish workflow, adding desktop-gated features, or working with the dual SQLite database setup. Activates on keywords like migration, nativephp, sqlite, prebuild, native:publish, electron, auto-update, desktop-only.
---

# NativePHP Development

This app runs on NativePHP (Electron + Laravel). The critical difference from a normal Laravel app is the **dual-database architecture** and **desktop-specific runtime behavior**.

## Dual-Database Architecture

At runtime, NativePHP uses `database/nativephp.sqlite` — a separate database from the default `database/database.sqlite` used by CLI tools (artisan, tinker, tests).

**The `nativephp` connection name does NOT exist in CLI.** Use the `DB_DATABASE` env override instead.

### Migration Commands (ALWAYS run both)

```bash
# 1. Default database (CLI/tests)
php artisan migrate --no-interaction

# 2. NativePHP runtime database
DB_DATABASE=database/nativephp.sqlite php artisan migrate --no-interaction
```

**Every migration must run against both databases.** Forgetting the NativePHP database causes column drift — the app crashes at runtime while tests pass. This happened before (commit `8908d9f`: repair migration for 7 missing columns).

### Worktree Exception

Git worktrees won't have `nativephp.sqlite`. Run NativePHP migrations from the main repo after merging worktree changes.

### Reading Runtime Data

CLI reads the default database, not the runtime one. To inspect runtime state:

```php
// In tinker, override the path:
$db = new SQLite3('database/nativephp.sqlite');
$result = $db->query('SELECT * FROM books');
```

Or use `DB_DATABASE=database/nativephp.sqlite php artisan tinker`.

## Desktop-Gated Features

Some features only work inside the desktop app. Gate them with:

```php
if (! config('nativephp-internal.running')) {
    return response()->json(['error' => 'Requires the desktop app'], 422);
}
```

### Testing Desktop-Gated Code

Set the config value in the test:

```php
config(['nativephp-internal.running' => true]);
```

This is required for testing PDF export and any future desktop-only features.

## Build & Publish Pipeline

**Workflow:** `.github/workflows/publish.yml` — triggered by `v*` tags.

### Key Build Facts

| Concern | Detail |
|---------|--------|
| Prebuild hook | `npm run build` in `config/nativephp.php` `prebuild` array |
| Artifact naming | No version numbers: `Manuscript-arm64.dmg`, `Manuscript-x64.dmg` |
| Version source | Git tag stripped of `v` prefix, written to `.env` as `NATIVEPHP_APP_VERSION` |
| Architectures | Both `arm64` and `x64` built sequentially on same runner |
| Signing | Apple Developer ID certificate from GitHub Secrets |
| Repo detection fix | Patches `vendor/.../electron/package.json` with actual repo URL |
| Release flow | Builds as draft, publishes after both architectures complete |

### Modifying the Build

- `config/nativephp.php` → `prebuild` / `postbuild` arrays for build hooks
- `cleanup_env_keys` → secrets stripped from `.env` before bundling
- `cleanup_exclude_files` → files/dirs removed before bundling
- Never add secrets to `config/nativephp.php` directly — use env vars

## Auto-Update System

- Backend: `AutoUpdater` facade in `NativeAppServiceProvider::boot()`
- Frontend: `useAutoUpdater` hook listens to `window.Native` events
- Controller: `UpdateController` with check/download/install endpoints
- Events flow: `CheckingForUpdate` → `UpdateAvailable` → `DownloadProgress` → `UpdateDownloaded`

## Quick Checklist

When creating a migration:
- [ ] Run against default database
- [ ] Run against NativePHP database (`DB_DATABASE=database/nativephp.sqlite`)

When adding a desktop-only feature:
- [ ] Gate with `config('nativephp-internal.running')`
- [ ] Set config in tests: `config(['nativephp-internal.running' => true])`

When modifying the build pipeline:
- [ ] Test with `php artisan native:publish mac arm64 --no-interaction` locally if possible
- [ ] Ensure secrets use env vars, not hardcoded values
- [ ] Check `cleanup_env_keys` covers any new secrets
