# Guardrails Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prevent two recurring AI-induced regressions in the Manuscript repo: (1) new `auth()->user()` / authorization checks landing in a no-users desktop app, and (2) migrations getting created without a matching `php artisan native:migrate` run against the NativePHP SQLite database.

**Architecture:**
- **In-repo (shared with CI + teammates):** one Pest Feature test (`tests/Feature/GuardrailsTest.php`) with two cases — one scans `app/` for banned auth patterns with an explicit allowlist, one asserts every controller has a matching Feature test file. Plus a short `## Guardrails` section in `CLAUDE.md` pointing at it.
- **Local to the operator (per-machine):** two Claude Code hooks under `~/.claude/hooks/` — a `PreToolUse` block that rejects `Edit`/`Write` calls introducing banned auth patterns, and a `PostToolUse` nudge that reminds about `native:migrate` the first time a migration-related tool call fires in a session (re-fires after 30min idle).
- The repo-side test is the load-bearing defense. The hooks are fast-feedback. Neither touches the existing 3 grandfathered `$request->user()` sites, `FormRequest::authorize()` no-ops, or `user_id` columns already in the schema.

**Tech Stack:** Pest 4 (feature tests + `Symfony\Component\Finder`), Python 3 (hook scripts, same shape as existing `~/.claude/hooks/protect-manuscript-env.py`), JSON (`~/.claude/settings.json` hook registration).

**Pre-discovered facts (do not re-verify):**
- Grandfathered `$request->user()` call sites: `app/Http/Controllers/AiController.php`, `app/Http/Controllers/EditorialReviewController.php`, `app/Http/Controllers/Concerns/StreamsConversation.php`, `app/Http/Middleware/HandleInertiaRequests.php`.
- Controllers without `FooControllerTest.php` (covered by differently-named tests — grandfathered): `AiConversationController`, `AiDashboardController`, `CanvasController`, `SearchController`, `SettingsController`, `WikiController`, `WikiPanelController`.
- `native:run` does NOT auto-migrate — the NativePHP plugin explicitly logs *"You may migrate manually by running: php artisan native:migrate"*.
- Existing reference hook: `/Users/david/.claude/hooks/protect-manuscript-env.py`. Match its style (JSON stdin, `return 2` for block, stderr message, safe-first-word short-circuit).

---

## Task 1: Add `tests/Feature/GuardrailsTest.php` with the auth-check scanner

**Files:**
- Create: `tests/Feature/GuardrailsTest.php`

- [ ] **Step 1: Write the failing test — auth-check scan**

Create `tests/Feature/GuardrailsTest.php`:

```php
<?php

use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| App Guardrails
|--------------------------------------------------------------------------
|
| This app has NO authentication or authorization. These tests enforce
| two rules that must hold for every change:
|
|   1. No new auth/authz calls land in app/ or routes/ (outside the
|      grandfathered allowlist below).
|   2. Every controller in app/Http/Controllers/ has a matching
|      Feature test file (FooController -> tests/Feature/FooControllerTest.php),
|      except the grandfathered set whose tests are named differently.
|
| If these fail, see CLAUDE.md ## Guardrails.
|
*/

it('has no forbidden auth calls outside the allowlist', function () {
    $bannedPatterns = [
        'auth\(\)->user\('              => 'auth()->user()',
        'auth\(\)->check\('             => 'auth()->check()',
        'auth\(\)->guest\('             => 'auth()->guest()',
        '\bAuth::'                      => 'Auth:: facade',
        "->middleware\(['\"]auth\b"     => "->middleware('auth')",
        "middleware\(\[\s*['\"]auth\b"  => "middleware(['auth', ...])",
        '\bGate::define'                => 'Gate::define',
        '\$this->authorize\('           => '$this->authorize()',
    ];

    $allowlist = [
        'app/Http/Controllers/AiController.php',
        'app/Http/Controllers/EditorialReviewController.php',
        'app/Http/Controllers/Concerns/StreamsConversation.php',
        'app/Http/Middleware/HandleInertiaRequests.php',
    ];

    $violations = [];
    $finder = (new Finder())
        ->files()
        ->in([base_path('app'), base_path('routes')])
        ->name('*.php');

    foreach ($finder as $file) {
        $relative = str_replace(base_path().'/', '', $file->getRealPath());

        if (in_array($relative, $allowlist, true)) {
            continue;
        }

        $isFormRequest = str_starts_with($relative, 'app/Http/Requests/');
        $content = $file->getContents();

        foreach ($bannedPatterns as $pattern => $label) {
            if ($isFormRequest && $label === '$this->authorize()') {
                continue;
            }

            if (preg_match('/'.$pattern.'/', $content)) {
                $violations[] = "{$relative}: {$label}";
            }
        }
    }

    expect($violations)->toBeEmpty(
        "This app has NO users. Do not add auth/authz checks.\n".
        "Scope data by domain FK (book_id, chapter_id), not by user.\n".
        "Violations:\n  ".implode("\n  ", $violations)
    );
});
```

- [ ] **Step 2: Run the test to confirm it passes against the current tree**

Run: `php artisan test --compact --filter='has no forbidden auth calls'`
Expected: PASS (1 passed). The allowlist covers all current usages.

- [ ] **Step 3: Prove the test actually catches violations (manual probe)**

Temporarily add `auth()->user();` to `app/helpers.php` (or any non-allowlisted file). Then:

Run: `php artisan test --compact --filter='has no forbidden auth calls'`
Expected: FAIL with message listing the file and the `auth()->user()` label.

Revert the probe change. Re-run:
Run: `php artisan test --compact --filter='has no forbidden auth calls'`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/GuardrailsTest.php
git commit -m "test: add auth-check guardrail

This app has no users. Scans app/ and routes/ for banned auth patterns
(auth()->user(), Auth::, ->middleware('auth'), Gate::define,
\$this->authorize()). Allowlists the 4 grandfathered call sites."
```

---

## Task 2: Extend `GuardrailsTest.php` with the controllers-have-tests assertion

**Files:**
- Modify: `tests/Feature/GuardrailsTest.php`

- [ ] **Step 1: Add the second test case**

Append to `tests/Feature/GuardrailsTest.php`:

```php
it('has a Feature test file for every controller', function () {
    // Controllers whose tests exist under a differently-named file
    // (domain-named, not class-named). These are grandfathered; new
    // controllers MUST follow the FooController -> FooControllerTest
    // convention.
    $grandfathered = [
        'AiConversationController',  // covered by AiControllerTest, AiTokenUsageTest
        'AiDashboardController',     // covered by AiDashboardTest
        'CanvasController',          // covered by CanvasPageTest
        'SearchController',          // covered by SearchTest
        'SettingsController',        // book-scoped custom dictionary (not AppSettings)
        'WikiController',            // covered by WikiEntryTest, WikiPageTest, WikiPanelTest
        'WikiPanelController',       // covered by WikiPanelTest
    ];

    $missing = [];
    $finder = (new Finder())
        ->files()
        ->in(base_path('app/Http/Controllers'))
        ->name('*Controller.php')
        ->notName('Controller.php');

    foreach ($finder as $file) {
        $name = $file->getBasename('.php');

        if (in_array($name, $grandfathered, true)) {
            continue;
        }

        $testPath = base_path("tests/Feature/{$name}Test.php");

        if (! file_exists($testPath)) {
            $missing[] = "tests/Feature/{$name}Test.php";
        }
    }

    expect($missing)->toBeEmpty(
        "Every new controller must have a matching Feature test.\n".
        "Convention: App\\Http\\Controllers\\FooController ".
        "=> tests/Feature/FooControllerTest.php.\n".
        "Missing:\n  ".implode("\n  ", $missing)
    );
});
```

- [ ] **Step 2: Run the test to confirm it passes**

Run: `php artisan test --compact --filter='has a Feature test file for every controller'`
Expected: PASS. The grandfathered list covers all 7 known mismatches; every other controller has a matching test.

- [ ] **Step 3: Prove the test catches new missing tests (manual probe)**

Create `app/Http/Controllers/FakeGuardrailController.php` with minimal body:

```php
<?php

namespace App\Http\Controllers;

class FakeGuardrailController extends Controller {}
```

Run: `php artisan test --compact --filter='has a Feature test file for every controller'`
Expected: FAIL with `Missing: tests/Feature/FakeGuardrailControllerTest.php`.

Delete the probe file. Re-run:
Run: `php artisan test --compact --filter='has a Feature test file for every controller'`
Expected: PASS.

- [ ] **Step 4: Run the full guardrail file one more time to confirm both cases green**

Run: `php artisan test --compact --filter=GuardrailsTest`
Expected: `Tests:  2 passed`.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/GuardrailsTest.php
git commit -m "test: add controllers-have-tests guardrail

Asserts every App\Http\Controllers\FooController has
tests/Feature/FooControllerTest.php. Grandfathers 7 controllers
whose tests are named by domain (SearchTest, WikiPanelTest, etc.)."
```

---

## Task 3: Add `## Guardrails` section to `CLAUDE.md`

**Files:**
- Modify: `CLAUDE.md` (insert after the existing `## Workflow` section, before `## Design Implementation`)

- [ ] **Step 1: Read the current CLAUDE.md to confirm insertion point**

Run: `grep -n '^## ' CLAUDE.md | head -20`
Expected: lists the existing sections. The new `## Guardrails` goes immediately after `## Workflow` (so it sits near the top and gets seen before the longer later sections).

- [ ] **Step 2: Insert the new section**

Insert this block in `CLAUDE.md` immediately after the `## Workflow` section's content and before `## Design Implementation`:

```markdown
## Guardrails

This app has NO authentication or authorization. Users exist in the schema but are unused. Rules:

- **No auth checks.** Never add `auth()->user()`, `auth()->check()`, `Auth::` facade, `->middleware('auth')`, Policies, `Gate::define`, or `$this->authorize()`. Scope data by domain FK (`book_id`, `chapter_id`), not by user. Grandfathered call sites live in `AiController`, `EditorialReviewController`, `StreamsConversation`, `HandleInertiaRequests` — do not extend to new controllers. Enforced by `tests/Feature/GuardrailsTest.php` and by `~/.claude/hooks/protect-manuscript-authz.py` (blocks `Edit`/`Write`).

- **Every controller has a Feature test.** `App\Http\Controllers\FooController` ⇒ `tests/Feature/FooControllerTest.php`. Enforced by `tests/Feature/GuardrailsTest.php`. 7 existing controllers are grandfathered (see the test for the list) — new controllers follow the convention.

- **Browser test per feature, not per page.** The first page added under a new feature gets a `tests/Browser/<Feature>Test.php`. Subsequent tweaks under the same feature do not need a new browser test, but the existing one must still pass.

- **Bugfixes are red-green.** Commit a failing test that reproduces the bug BEFORE the fix commit — or include `// red-green: see <test name>` in the fix commit message.

- **Migrations run against BOTH databases.** `native:run` does NOT auto-migrate. After `php artisan make:migration`: run `php artisan migrate`, then `DB_DATABASE=database/nativephp.sqlite php artisan migrate --no-interaction` (from the main repo the shorthand is `php artisan native:migrate`). A `PostToolUse` hook at `~/.claude/hooks/remind-native-migrate.py` nudges once per session.

- **PR-time verification.** Any PR touching `app/Http/Controllers/`, `app/Http/Requests/`, or `database/migrations/` must invoke `superpowers:verification-before-completion` with proof that `php artisan test --compact` passes AND that `native:migrate` ran.
```

- [ ] **Step 3: Verify the section reads cleanly**

Run: `grep -n '^## ' CLAUDE.md | head -20`
Expected: `## Guardrails` appears between `## Workflow` and `## Design Implementation`.

- [ ] **Step 4: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: add Guardrails section to CLAUDE.md

Documents the no-auth / always-tested rules, points at
tests/Feature/GuardrailsTest.php and the ~/.claude/hooks/ scripts
that enforce them."
```

---

## Task 4: Create the PreToolUse authz hook

**Files:**
- Create: `~/.claude/hooks/protect-manuscript-authz.py`

- [ ] **Step 1: Read the reference hook to match style**

Run: `cat ~/.claude/hooks/protect-manuscript-env.py | head -60`
Expected: confirms hook shape — shebang, module docstring, `json.load(sys.stdin)`, fail-open on malformed JSON, `return 2` + stderr for blocks.

- [ ] **Step 2: Write the hook**

Create `~/.claude/hooks/protect-manuscript-authz.py` with mode `0o755`:

```python
#!/usr/bin/env python3
"""
Block Edit/Write tool calls that introduce auth/authz patterns into the
Manuscript repo. This app has no users. Silently landing auth checks
breaks every page for anonymous visitors.

Paired with tests/Feature/GuardrailsTest.php — the test is the durable
defense (runs in CI, survives fresh clones). This hook is fast feedback
so the mistake gets caught at the moment of editing, not at test time.
"""

import json
import re
import sys
from pathlib import Path

MANUSCRIPT_PATH = Path("/Users/david/Workspace/manuscript")

# Grandfathered files — legitimate $request->user() usage, left alone.
GRANDFATHERED_SUFFIXES = (
    "app/Http/Controllers/AiController.php",
    "app/Http/Controllers/EditorialReviewController.php",
    "app/Http/Controllers/Concerns/StreamsConversation.php",
    "app/Http/Middleware/HandleInertiaRequests.php",
)

# (regex, human label). Order matters only for error messages.
BANNED_PATTERNS = [
    (r"auth\(\)->user\(",                "auth()->user()"),
    (r"auth\(\)->check\(",               "auth()->check()"),
    (r"auth\(\)->guest\(",               "auth()->guest()"),
    (r"\bAuth::",                        "Auth:: facade"),
    (r"->middleware\(['\"]auth\b",       "->middleware('auth')"),
    (r"middleware\(\[\s*['\"]auth\b",    "middleware(['auth', ...])"),
    (r"\bGate::define",                  "Gate::define"),
    (r"\$this->authorize\(",             "$this->authorize()"),
]


def is_form_request(relative_path: str) -> bool:
    return relative_path.startswith("app/Http/Requests/")


def main() -> int:
    try:
        data = json.load(sys.stdin)
    except json.JSONDecodeError:
        # Fail-open — CI test is authoritative.
        return 0

    tool = data.get("tool_name")
    if tool not in ("Edit", "Write"):
        return 0

    params = data.get("tool_input", {}) or {}
    file_path_raw = params.get("file_path", "")
    if not file_path_raw:
        return 0

    try:
        file_path = Path(file_path_raw).resolve()
    except (OSError, RuntimeError):
        return 0

    # Only enforce for files inside the Manuscript repo.
    try:
        relative = file_path.relative_to(MANUSCRIPT_PATH)
    except ValueError:
        return 0

    relative_str = str(relative)

    # Scope: PHP files under app/ or routes/.
    if not relative_str.endswith(".php"):
        return 0
    if not (relative_str.startswith("app/") or relative_str.startswith("routes/")):
        return 0

    # Grandfathered files.
    if relative_str in GRANDFATHERED_SUFFIXES:
        return 0

    content = params.get("new_string", "") if tool == "Edit" else params.get("content", "")
    if not content:
        return 0

    for pattern, label in BANNED_PATTERNS:
        # FormRequest::authorize(): bool { return true; } is a framework
        # no-op — Laravel requires the method. Skip this one label for
        # files under app/Http/Requests/.
        if label == "$this->authorize()" and is_form_request(relative_str):
            continue

        if re.search(pattern, content):
            print(
                f"BLOCKED by protect-manuscript-authz hook: "
                f"`{label}` in {relative_str}.\n\n"
                f"This app has NO users. Do not add auth/authz checks. "
                f"Scope data by domain FK (book_id, chapter_id), not by user.\n\n"
                f"Grandfathered call sites (do not extend to new files):\n"
                f"  - app/Http/Controllers/AiController.php\n"
                f"  - app/Http/Controllers/EditorialReviewController.php\n"
                f"  - app/Http/Controllers/Concerns/StreamsConversation.php\n"
                f"  - app/Http/Middleware/HandleInertiaRequests.php\n\n"
                f"See CLAUDE.md ## Guardrails and tests/Feature/GuardrailsTest.php.",
                file=sys.stderr,
            )
            return 2

    return 0


if __name__ == "__main__":
    sys.exit(main())
```

- [ ] **Step 3: Make it executable**

Run: `chmod +x ~/.claude/hooks/protect-manuscript-authz.py`
Expected: no output, exit 0.

- [ ] **Step 4: Smoke-test the hook manually**

Run:

```bash
echo '{"tool_name":"Write","tool_input":{"file_path":"/Users/david/Workspace/manuscript/app/helpers.php","content":"<?php auth()->user();"}}' \
  | ~/.claude/hooks/protect-manuscript-authz.py
echo "exit=$?"
```

Expected: stderr message containing `BLOCKED by protect-manuscript-authz hook: \`auth()->user()\``, `exit=2`.

Run:

```bash
echo '{"tool_name":"Write","tool_input":{"file_path":"/Users/david/Workspace/manuscript/app/Http/Controllers/AiController.php","content":"<?php auth()->user();"}}' \
  | ~/.claude/hooks/protect-manuscript-authz.py
echo "exit=$?"
```

Expected: no stderr, `exit=0` (grandfathered file passes through).

Run:

```bash
echo '{"tool_name":"Write","tool_input":{"file_path":"/Users/david/Workspace/manuscript/app/Http/Requests/UpdateBookRequest.php","content":"<?php class X { public function authorize(): bool { return true; } }"}}' \
  | ~/.claude/hooks/protect-manuscript-authz.py
echo "exit=$?"
```

Expected: `exit=0` (FormRequest `authorize()` is the skipped label).

Run:

```bash
echo '{"tool_name":"Edit","tool_input":{"file_path":"/Users/david/Workspace/manuscript/resources/js/pages/Foo.tsx","new_string":"Auth::check();"}}' \
  | ~/.claude/hooks/protect-manuscript-authz.py
echo "exit=$?"
```

Expected: `exit=0` (not under `app/` or `routes/`).

---

## Task 5: Create the PostToolUse native-migrate reminder hook

**Files:**
- Create: `~/.claude/hooks/remind-native-migrate.py`

- [ ] **Step 1: Write the hook**

Create `~/.claude/hooks/remind-native-migrate.py` with mode `0o755`:

```python
#!/usr/bin/env python3
"""
PostToolUse nudge: when a migration is created or run, remind the model
to also run `php artisan native:migrate` against the NativePHP SQLite
database. NativePHP does NOT auto-migrate on native:run.

Fires at most once per 30 minutes to avoid spamming the context when a
batch of migrations lands in sequence.
"""

import json
import os
import re
import sys
import time
from pathlib import Path

MANUSCRIPT_PATH = Path("/Users/david/Workspace/manuscript")
STATE_FILE = Path(os.path.expanduser("~/.claude/hooks/.native-migrate-reminded"))
COOLDOWN_SECONDS = 1800  # 30 minutes


def should_remind() -> bool:
    if not STATE_FILE.exists():
        return True
    try:
        last = STATE_FILE.stat().st_mtime
        return (time.time() - last) > COOLDOWN_SECONDS
    except OSError:
        return True


def mark_reminded() -> None:
    STATE_FILE.parent.mkdir(parents=True, exist_ok=True)
    STATE_FILE.touch()


def triggers_from_bash(cmd: str) -> bool:
    # artisan make:migration, or a raw `php artisan migrate` (not migrate:fresh / migrate:reset).
    if re.search(r"\bartisan\s+make:migration\b", cmd):
        return True
    if re.search(r"\bphp\s+artisan\s+migrate\b(?!:)", cmd):
        return True
    return False


def triggers_from_write(path_raw: str) -> bool:
    if not path_raw:
        return False
    try:
        path = Path(path_raw).resolve()
    except (OSError, RuntimeError):
        return False
    try:
        rel = path.relative_to(MANUSCRIPT_PATH)
    except ValueError:
        return False
    return str(rel).startswith("database/migrations/") and str(rel).endswith(".php")


def main() -> int:
    try:
        data = json.load(sys.stdin)
    except json.JSONDecodeError:
        return 0

    tool = data.get("tool_name", "")
    params = data.get("tool_input", {}) or {}

    triggered = False
    if tool == "Bash":
        triggered = triggers_from_bash(params.get("command", "") or "")
    elif tool == "Write":
        triggered = triggers_from_write(params.get("file_path", "") or "")

    if not triggered:
        return 0

    if not should_remind():
        return 0

    print(
        "Migration detected. Before claiming the feature done:\n"
        "  1. php artisan migrate\n"
        "  2. DB_DATABASE=database/nativephp.sqlite php artisan migrate --no-interaction\n"
        "     (or from the main repo: php artisan native:migrate)\n"
        "  3. Write or extend the Feature test that exercises the new column end-to-end.\n"
        "See CLAUDE.md ## Guardrails."
    )
    mark_reminded()
    return 0


if __name__ == "__main__":
    sys.exit(main())
```

- [ ] **Step 2: Make it executable**

Run: `chmod +x ~/.claude/hooks/remind-native-migrate.py`
Expected: no output, exit 0.

- [ ] **Step 3: Smoke-test the hook manually**

Clear the cooldown state file first:

Run: `rm -f ~/.claude/hooks/.native-migrate-reminded`

Run:

```bash
echo '{"tool_name":"Bash","tool_input":{"command":"php artisan make:migration add_foo_to_books_table"}}' \
  | ~/.claude/hooks/remind-native-migrate.py
echo "exit=$?"
```

Expected: stdout contains `Migration detected.` and the 3-step checklist, `exit=0`.

Re-run the same command immediately:

Expected: no stdout (cooldown in effect), `exit=0`.

Run:

```bash
echo '{"tool_name":"Bash","tool_input":{"command":"git status"}}' \
  | ~/.claude/hooks/remind-native-migrate.py
echo "exit=$?"
```

Expected: no stdout, `exit=0` (not a migration command).

Run:

```bash
echo '{"tool_name":"Bash","tool_input":{"command":"php artisan migrate:fresh"}}' \
  | ~/.claude/hooks/remind-native-migrate.py
echo "exit=$?"
```

Expected: no stdout, `exit=0` (migrate:fresh is NOT the plain migrate command).

Clean up cooldown:
Run: `rm -f ~/.claude/hooks/.native-migrate-reminded`

---

## Task 6: Register both hooks in `~/.claude/settings.json`

**Files:**
- Modify: `~/.claude/settings.json`

- [ ] **Step 1: Read the current settings.json**

Run: `cat ~/.claude/settings.json`

Record whether a `hooks` key already exists and what's inside. The existing `~/.claude/hooks/protect-manuscript-env.py` should already be registered — do not remove or alter its entry.

- [ ] **Step 2: Add both new hook entries**

The target shape (merge with whatever exists; do NOT overwrite existing entries):

```json
{
  "hooks": {
    "PreToolUse": [
      {
        "matcher": "Bash",
        "hooks": [
          { "type": "command", "command": "/Users/david/.claude/hooks/protect-manuscript-env.py" }
        ]
      },
      {
        "matcher": "Edit|Write",
        "hooks": [
          { "type": "command", "command": "/Users/david/.claude/hooks/protect-manuscript-authz.py" }
        ]
      }
    ],
    "PostToolUse": [
      {
        "matcher": "Bash|Write",
        "hooks": [
          { "type": "command", "command": "/Users/david/.claude/hooks/remind-native-migrate.py" }
        ]
      }
    ]
  }
}
```

Use the `update-config` skill (invoke it explicitly) to perform the merge. It understands the structure of `~/.claude/settings.json` and will not clobber unrelated keys.

If `update-config` is unavailable, edit the file with `jq` to merge rather than overwrite:

```bash
jq '.hooks.PreToolUse += [{"matcher":"Edit|Write","hooks":[{"type":"command","command":"/Users/david/.claude/hooks/protect-manuscript-authz.py"}]}] | .hooks.PostToolUse = ((.hooks.PostToolUse // []) + [{"matcher":"Bash|Write","hooks":[{"type":"command","command":"/Users/david/.claude/hooks/remind-native-migrate.py"}]}])' \
  ~/.claude/settings.json > ~/.claude/settings.json.tmp \
  && mv ~/.claude/settings.json.tmp ~/.claude/settings.json
```

- [ ] **Step 3: Verify the JSON is still valid and contains all three hooks**

Run: `jq '.hooks' ~/.claude/settings.json`
Expected: valid JSON, three command entries total — `protect-manuscript-env.py`, `protect-manuscript-authz.py`, `remind-native-migrate.py`.

Run: `python3 -m json.tool ~/.claude/settings.json > /dev/null && echo OK`
Expected: `OK`.

---

## Task 7: End-to-end verification

**Files:** none modified.

- [ ] **Step 1: Run the full guardrail test suite**

Run: `php artisan test --compact --filter=GuardrailsTest`
Expected: `Tests:  2 passed`.

- [ ] **Step 2: Run the full Feature test suite to confirm nothing regressed**

Run: `php artisan test --compact`
Expected: no new failures introduced. Preexisting failures (if any) are out of scope — record them, don't fix them here.

- [ ] **Step 3: Confirm both hook files exist and are executable**

Run: `ls -la ~/.claude/hooks/protect-manuscript-authz.py ~/.claude/hooks/remind-native-migrate.py`
Expected: both files present with `-rwxr-xr-x` (or similar `+x`) permissions.

- [ ] **Step 4: Sanity-check the hook registration is live**

Exit the current Claude Code session and start a new one so the harness re-reads `~/.claude/settings.json`. In the new session, attempt to write a file containing `auth()->user()` to any `app/` path — the hook should block with the `BLOCKED by protect-manuscript-authz hook:` message.

(This step is informational — not automatable from within the same session the hooks were installed in. If skipping, note in the PR description.)

- [ ] **Step 5: Commit any remaining changes + open the PR**

Run: `git status`
Expected: nothing to commit if Tasks 1–3 were committed cleanly. Hooks and settings.json live under `~/.claude/` and are not in the repo.

Run: `git push origin HEAD`

Open a PR against `dev`:

```bash
gh pr create --base dev --title "feat: guardrails — no auth checks, controllers have tests" --body "$(cat <<'EOF'
## Summary
- Adds `tests/Feature/GuardrailsTest.php` with two enforcement cases:
  - No `auth()->user()` / `Auth::` / `->middleware('auth')` / `Gate::define` / `\$this->authorize()` outside the 4 grandfathered call sites.
  - Every `App\Http\Controllers\FooController` has `tests/Feature/FooControllerTest.php` (7 existing controllers grandfathered).
- Adds `## Guardrails` section to `CLAUDE.md` pointing at the test and the local hooks.

Local-only (not in this PR): `~/.claude/hooks/protect-manuscript-authz.py` (PreToolUse block on auth patterns) and `~/.claude/hooks/remind-native-migrate.py` (PostToolUse nudge after migrations).

## Test plan
- [x] `php artisan test --compact --filter=GuardrailsTest` passes.
- [x] Manually probed both assertions with a temporary violation — each fails loudly before being reverted.
- [x] Hook smoke tests pass for block, allowlist, grandfathered paths, and cooldown.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

Expected: PR URL returned.
