# NativePHP vendor patches

`vendor/nativephp/desktop/resources/electron/` is the upstream Electron
scaffold. Our release build uses it directly (vendor fallback in
`Native\Desktop\Drivers\Electron\ElectronServiceProvider::electronPath()`),
so any customization we need has to be re-applied to vendor on every CI run.

## What lives here

`files/` mirrors the path layout under `vendor/nativephp/desktop/resources/electron/`.
Each file in `files/` replaces the corresponding vendor file via `apply.sh`.

Current patches:

| Path | Why we patch it |
|------|-----------------|
| `electron-plugin/src/preload/index.mts` | Adds the OS-level Spellcheck bridge that exposes `webFrame.isWordMisspelled`, `getWordSuggestions`, `setSpellCheckerLanguages`, `addWordToSpellCheckerDictionary` to the renderer. |
| `electron-plugin/dist/preload/index.mjs` | Compiled output of the above; ships in the build. |
| `electron-plugin/dist/server/api/system.js` | Fixes `printToPDF` to wait for `did-finish-load` before grabbing the PDF, instead of awaiting `loadURL` (which races on data URLs). |

## When patches run

- **CI** — `.github/workflows/publish.yml` invokes `bash scripts/nativephp-patches/apply.sh`
  after `composer install` and before `php artisan native:publish`. Both the
  macOS and Windows jobs run the step.
- **Local dev** — you usually don't need to. Your local
  `nativephp/electron/` is the published path and already contains the
  customizations. Run the script only if you want to test the vendor fallback
  the way CI does.

## Updating a patch

1. Edit the customization where it lives on disk (e.g.
   `nativephp/electron/electron-plugin/src/preload/index.mts`).
2. Copy the updated file to the matching location under `files/`.
3. If you're adding a brand-new file, also add an `apply` call to `apply.sh`.
4. Commit the patch file alongside any `apply.sh` change.

To check that the local working tree matches the patches:

```bash
diff -q scripts/nativephp-patches/files/electron-plugin/src/preload/index.mts \
        nativephp/electron/electron-plugin/src/preload/index.mts
```

## When upstream changes

If a NativePHP upgrade renames or restructures any of the patched files,
`apply.sh` will fail loudly with `vendor file missing`. Regenerate the patches
against the new vendor layout and update the path list in `apply.sh`.
