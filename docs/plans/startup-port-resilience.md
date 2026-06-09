# Startup port resilience — diagnosis & fix plan

> Status: **IMPLEMENTED** (composer-patches; macOS single-instance lock + API bind
> retry + startup dialog). See "Implemented" section at the bottom.
> Symptom reported: "the app sometimes never opens at all" on local installs.

## TL;DR

The PHP application-server port (8100–9000) **already self-recovers** — NativePHP scans
the range for a bindable port on every launch. That is *not* the problem.

The real failure is a **port mismatch on the Electron control-API server (4000–5000)**,
caused by **two Electron instances running at once on macOS** because **NativePHP does not
acquire a single-instance lock on macOS**. The second instance ends up telling its PHP
process an API port that has no healthy listener, so every PHP→Electron call (including
`Window::open()`) fails with connection-refused and **the window never opens**. Only a full
quit-and-relaunch clears it.

## Evidence

Runtime log `~/Library/Application Support/Manuscript/storage/logs/laravel-2026-06-08.log` —
three launches today (06:31, 06:37, 06:47), 9× identical:

```
production.ERROR: cURL error 7: Failed to connect to localhost port 4001 after 1 ms:
Could not connect to server for http://localhost:4001/api/child-process/start-php
```

Live process state at time of investigation (the *healthy* instance):

```
Manuscript (PID 22880)  TCP *:4000 (LISTEN)        <- Electron control API
php        (PID 23245)  TCP 127.0.0.1:8100 (LISTEN) <- PHP app server
```

So the working instance is on **4000**; the failed launches were handed **4001**, where
nothing healthy was listening.

## Causal chain (root cause)

1. **No single-instance lock on macOS.**
   `vendor/nativephp/desktop/resources/electron/electron-plugin/src/index.ts:190` gates
   `app.requestSingleInstanceLock()` behind `process.platform !== "darwin"`. On macOS,
   nothing stops a second instance.
2. **macOS keeps the app resident after the window closes.**
   `index.ts:63-67` — `window-all-closed` only quits on non-darwin. A closed-window instance
   keeps its API (4000) + PHP (8100) servers alive in the dock.
3. **A second launch collides on ports.** Spotlight / Finder / dock / macOS relaunch starts
   instance B while A is resident. B's API server does `getPort(portNumbers(4000,5000))`,
   sees 4000 busy, and picks **4001**
   (`.../electron-plugin/src/server/api.ts:35-38`).
4. **The API `listen()` has no error handler.**
   `api.ts:70` — `httpServer.listen(port, () => resolve(...))` has no `.on('error')`.
   `state.electronApiPort` is set from the resolved port (`index.ts:241`) and PHP is launched
   with `NATIVEPHP_API_URL=http://localhost:4001/api/`
   (`.../src/server/php.ts:370`). If 4001 isn't a healthy listener for B's PHP, there is no
   retry and no error surfaced.
5. **Bootstrap is fire-and-forget.** `index.ts:44` calls `bootstrapApp()` with no `await`/
   `try-catch`; `startElectronApi()`/`startPhpApp()` (`:108,:112`) have no failure path.
6. **Result:** B's PHP boots, `NativeAppServiceProvider::boot()` calls `Window::open()`
   (`app/Providers/NativeAppServiceProvider.php:28`) → POST to the dead API port → connection
   refused → **window never opens**, only background errors are logged. A full quit + relaunch
   is the only recovery the user has today.

## Why a pure PHP-side heal won't work here

The app already self-heals NativePHP gremlins from PHP (`healStaleNativePhpSecret()`,
`ResilientMigrationRepository`, `ensureDatabaseSchema()`). The port case is different: when
the partner Electron API is genuinely gone, PHP cannot reach *any* API (the other instance's
API uses a different per-launch `randomSecret`, so cross-talk would 403), and PHP's only
output channel — the window — is opened *through* that dead API. The effective fix must live
in the Electron main process.

## Fix (layered)

1. **[Root cause] Single-instance lock on macOS.** Acquire `app.requestSingleInstanceLock()`
   for all platforms, early, before starting servers. If not acquired → focus the existing
   window (the `second-instance` handler already exists) and quit the new instance. This
   *prevents* the collision → no port drift → no "never opens." Relaunch simply focuses the
   running app = self-recovering by construction.
2. **[Defense in depth] Harden the API bind.** Add `.on('error')` to `api.ts` `listen`; on
   `EADDRINUSE`, retry the next port, and only resolve / set `state.electronApiPort` once a
   listener is actually live.
3. **[User-facing safety net] Startup error dialog.** Wrap `bootstrapApp()` in try/catch and
   register `process.on('uncaughtException' | 'unhandledRejection')` in Electron main. On a
   fatal startup failure, `dialog.showErrorBox(...)` with plain guidance:
   *"Manuscript couldn't start its desktop services. Quit Manuscript completely
   (right-click the dock icon → Quit) and reopen it. If it keeps happening, restart your
   Mac."* Then quit. (Directly answers the "make sure the user knows / restart?" request.)
4. **[Optional UX] Resident-instance cleanup.** Either quit on `window-all-closed` for macOS,
   or make `before-quit` reliably reap child PHP/API processes so nothing lingers. Needs a UX
   call — left out of the default scope.

## Durability mechanism — DECISION NEEDED

All the code above lives in NativePHP's Electron layer: `nativephp/electron/` is **gitignored
and regenerated by `native:publish`**, and `vendor/nativephp/desktop` is overwritten by
`composer install`. A hand edit will not survive. Options:

- **A. `cweagans/composer-patches`** (most robust): patch vendor; the patch flows into the
  published copy on `native:publish` and re-applies on every `composer install` incl. CI.
  Cost: one new dev dependency → requires approval (CLAUDE.md).
- **B. `prebuild` hook** (no new dependency): a script wired into `config/nativephp.php`
  `prebuild` that applies the change to `nativephp/electron/electron-plugin/src/...` after the
  publish-copy, before `npm run build`. No dep, but more fragile (must match upstream source).
- **Plus:** upstream the macOS single-instance-lock fix to NativePHP regardless.

## Testing

- `plugin:test` (vitest, in `nativephp/electron`) can cover the api.ts retry + single-instance
  decision if we go the patch route.
- Manual verification: launch the app twice → second focuses the first (no 2nd instance);
  hold the API port range → dialog appears + clean quit.
- App-side: add a guard/test only if we add any PHP-side detection (not in default scope).

## Implemented

Delivery: **cweagans/composer-patches** (chosen for robustness — re-applies on every
`composer install`, including CI, and flows into the built artifact via `native:publish`).

Tracked changes:
- `composer.json` — `cweagans/composer-patches` (require-dev), `allow-plugins` entry,
  `extra.patches["nativephp/desktop"]`, `composer-exit-on-patch-failure: true`.
- `composer.lock` — locks the plugin at 1.7.3.
- `patches/nativephp-desktop-startup-resilience.patch` — the actual fix.
- `tests/Unit/StartupResiliencePatchTest.php` — guards the wiring AND that the patch is
  actually applied to the installed NativePHP source (catches a silent re-apply failure
  after a future NativePHP bump).

What the patch changes in `vendor/nativephp/desktop/.../electron-plugin/src`:
- `index.ts` — acquire `requestSingleInstanceLock()` on **all** platforms early in
  `bootstrap()`; a `second-instance` launch focuses the existing window (from
  `state.windows`) or asks Laravel to open one. Wrap `bootstrapApp()` in try/catch →
  `handleStartupFailure()` shows a `dialog.showErrorBox` ("quit completely and reopen; if it
  persists, restart your computer") then quits, instead of a silent dead app.
- `server/api.ts` — add an `.on("error")` handler to the API server `listen()`; on
  `EADDRINUSE`, retry on a fresh port (up to 20×) so PHP is never handed a dead API port.

Verification performed:
- `composer install` re-extracted `nativephp/desktop` from pristine and applied the patch
  cleanly (durability proof).
- `npm run plugin:build` (tsc) compiled with exit 0; compiled `dist/index.js` + `dist/server/
  api.js` contain the fix.
- `php artisan test --filter=StartupResiliencePatch` → 3 passed.

How it reaches a build:
- **CI** (`publish.yml`): `composer install` (patches vendor) → `native:publish` (copies
  patched vendor into the app) → build. Automatic, no extra steps.
- **Local**: the published copy `nativephp/electron/electron-plugin/src` was patched in place
  too, so a local rebuild picks it up immediately; a `native:publish` re-sync also works.

Manual verification (left to operator): rebuild/run the app, launch it a second time → the
running instance should focus instead of a second instance spawning. The error dialog only
appears on a genuine fatal startup failure.

Follow-up: upstream the macOS single-instance-lock fix to NativePHP so the patch can
eventually be dropped.
