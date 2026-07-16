# NativePHP vendor patches

`vendor/nativephp/desktop/` is overwritten by every `composer install`, but our
release build uses it directly (vendor fallback in
`Native\Desktop\Drivers\Electron\ElectronServiceProvider::electronPath()`), so
any customization we need has to be re-applied to vendor on every CI run.

We do this with **whole-file copies** (`apply.sh`), not unified-diff patches.
Diff-based patching (cweagans/composer-patches) is line-ending- and
context-sensitive: on the Windows runner `git apply` runs under
`core.autocrlf=true`, the patch silently fails to apply, and
`composer-exit-on-patch-failure` kills the build. A plain `cp` has none of that
fragility.

## What lives here

`files/` mirrors the path layout under `vendor/nativephp/desktop/` (the package
root — so files both inside `resources/electron/` and elsewhere, e.g.
`src/Drivers/...`, use the same mechanism). Each file in `files/` replaces the
corresponding vendor file via `apply.sh`.

Current patches:

| Path (under the package root) | Why we patch it |
|------|-----------------|
| `resources/electron/electron-plugin/src/preload/index.mts` | Adds the OS-level Spellcheck bridge that exposes `webFrame.isWordMisspelled`, `getWordSuggestions`, `setSpellCheckerLanguages`, `addWordToSpellCheckerDictionary` to the renderer. |
| `resources/electron/electron-plugin/dist/preload/index.mjs` | Compiled output of the above; ships in the build. |
| `resources/electron/electron-plugin/dist/server/api/system.js` | Fixes `printToPDF` to wait for `did-finish-load` before grabbing the PDF, instead of awaiting `loadURL` (which races on data URLs). |
| `resources/electron/electron-plugin/dist/index.js` | Startup resilience: an all-platforms single-instance lock, bootstrap/reopen serialization, loaded-and-visible main-window verification, intentional-quit suppression, and a bounded one-shot recovery launch before showing a startup-failure dialog. The control API starts before Laravel bootstrap commands so every PHP process receives the current port and secret. |
| `resources/electron/electron-plugin/dist/server/php.js` | Keeps normal startup optimization version-guarded, while the one-shot recovery launch removes only generated bootstrap, compiled-view, and PHP OPcache files and forces a clean rebuild without touching the SQLite database or user documents. |
| `resources/electron/electron-plugin/dist/server/utils.js` | Adds a strict, timeout-bounded Laravel notification path for startup/reopen operations while keeping ordinary native events best-effort. |
| `resources/electron/electron-plugin/dist/server/api/window.js` | Retains each `BrowserWindow.loadURL()` promise so startup can observe an exact load success/failure after Laravel releases its single-threaded `/booted` request, without deadlocking the window route. |
| `resources/electron/electron-plugin/dist/server/api.js` | Startup resilience: retries the Electron API server on a fresh port when the chosen port is already bound (`EADDRINUSE`). |
| `resources/electron/electron-plugin/src/server/childProcess.ts` | Sends a readiness/error handshake only after the wrapped PHP/Node command itself spawns. |
| `resources/electron/electron-plugin/dist/server/childProcess.js` | Shipped output of the real child-process readiness handshake. |
| `resources/electron/electron-plugin/src/server/api/childProcess.ts` | Keeps source parity for serialized child-process responses, pending-start deduplication, bounded startup timeout, and HTTP failure responses. |
| `resources/electron/electron-plugin/dist/server/api/childProcess.js` | Waits for the real wrapped process acknowledgment before reporting success, preserves persistent watchdog restarts, and returns observable startup failures. |
| `src/Drivers/Electron/ElectronServiceProvider.php` | `electronPath()` detects the published project by its root `package.json`, so sub-paths no longer force an incorrect vendor fallback. |
| `src/NativeServiceProvider.php` | Atomically replaces cached NativePHP running state, storage/database paths, Electron API URL, and secret before any desktop service consumes them. Queue startup is explicitly gated behind database readiness and retried with bounded backoff while remaining fail-open for the window. |
| `src/ChildProcess.php` | Rejects Electron child-process error/invalid responses so queue startup retries can observe real failures instead of hydrating false-success objects. |

### Why the startup-resilience patches target `dist/`, not `src/`

The publish build runs `electron-vite build`, never `plugin:build`/`tsc`, so the
package's `electron-plugin/src/*.ts` is **never recompiled** — only the prebuilt
`electron-plugin/dist/` is loaded at runtime (`package.json` `exports`/`#plugin`
→ `dist/index.js`). Patching `src` would have no effect on the shipped app, so we
edit the compiled `dist` files directly. `ElectronServiceProvider.php` is PHP and
ships as-is, so it is copied verbatim.

## When patches run

- **CI** — `.github/workflows/publish.yml` invokes `bash scripts/nativephp-patches/apply.sh`
  after `composer install` and before `php artisan native:publish`. Both the
  macOS and Windows jobs run the step.
- **Local dev** — you usually don't need to. Your local
  `nativephp/electron/` is the published path and already contains the
  customizations. Run the script only if you want to test the vendor fallback
  the way CI does.

## Updating a patch

1. Edit the customization where it lives on disk. For the compiled `dist` files,
   edit the `dist` JS directly (the build does not recompile `src`).
2. Copy the updated file to the matching location under `files/`.
3. If you're adding a brand-new file, also add an `apply` call to `apply.sh`.
4. Commit the file alongside any `apply.sh` change.

The patched behaviours are guarded by `tests/Unit/StartupResiliencePatchTest.php`
and `tests/Unit/ElectronPathResolutionPatchTest.php` — they fail loudly if a
behaviour is lost, stops being wired through `apply.sh`, or if a NativePHP bump
moves a file we copy onto.

## When upstream changes

If a NativePHP upgrade renames or restructures any of the patched files,
`apply.sh` will fail loudly with `vendor file missing`. Regenerate the patches
against the new vendor layout and update the path list in `apply.sh`.
