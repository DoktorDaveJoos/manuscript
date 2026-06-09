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
| `resources/electron/electron-plugin/dist/index.js` | Startup resilience: an all-platforms single-instance lock + second-instance focus, and a visible startup-failure dialog. |
| `resources/electron/electron-plugin/dist/server/api.js` | Startup resilience: retries the Electron API server on a fresh port when the chosen port is already bound (`EADDRINUSE`). |
| `src/Drivers/Electron/ElectronServiceProvider.php` | `electronPath()` detects the published project by its root `package.json`, so sub-paths no longer force an incorrect vendor fallback. |

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
