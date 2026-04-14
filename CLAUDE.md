<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v2
- laravel/ai (AI) - v0
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/wayfinder (WAYFINDER) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/react (INERTIA_REACT) - v2
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `ai-sdk-development` — TRIGGER when working with ai-sdk which is Laravel official first-party AI SDK. Activate when building, editing AI agents, chatbots, text generation, image generation, audio/TTS, transcription/STT, embeddings, RAG, vector stores, reranking, structured output, streaming, conversation memory, tools, queueing, broadcasting, and provider failover across OpenAI, Anthropic, Gemini, Azure, Groq, xAI, DeepSeek, Mistral, Ollama, ElevenLabs, Cohere, Jina, and VoyageAI. Invoke when the user references ai-sdk, the `Laravel\Ai\` namespace, or this project's AI features — not for Prism PHP or other AI packages used directly.
- `laravel-best-practices` — Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns.
- `wayfinder-development` — Use this skill for Laravel Wayfinder which auto-generates typed functions for Laravel controllers and routes. ALWAYS use this skill when frontend code needs to call backend routes or controller actions. Trigger when: connecting any React/Vue/Svelte/Inertia frontend to Laravel controllers, routes, building end-to-end features with both frontend and backend, wiring up forms or links to backend endpoints, fixing route-related TypeScript errors, importing from @/actions or @/routes, or running wayfinder:generate. Use Wayfinder route functions instead of hardcoded URLs. Covers: wayfinder() vite plugin, .url()/.get()/.post()/.form(), query params, route model binding, tree-shaking. Do not use for backend-only task
- `pest-testing` — Use this skill for Pest PHP testing in Laravel projects only. Trigger whenever any test is being written, edited, fixed, or refactored — including fixing tests that broke after a code change, adding assertions, converting PHPUnit to Pest, adding datasets, and TDD workflows. Always activate when the user asks how to write something in Pest, mentions test files or directories (tests/Feature, tests/Unit, tests/Browser), or needs browser testing, smoke testing multiple pages for JS errors, or architecture tests. Covers: test()/it()/expect() syntax, datasets, mocking, browser testing (visit/click/fill), smoke testing, arch(), Livewire component tests, RefreshDatabase, and all Pest 4 features. Do not use for factories, seeders, migrations, controllers, models, or non-test PHP code.
- `inertia-react-development` — Develops Inertia.js v2 React client-side applications. Activates when creating React pages, forms, or navigation; using <Link>, <Form>, useForm, or router; working with deferred props, prefetching, or polling; or when user mentions React with Inertia, React pages, React forms, or React navigation.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>

## Workflow

- When asked to implement a feature, always create/update Pencil designs FIRST before writing code, unless explicitly told otherwise.

## Guardrails

This app has NO authentication or authorization. Users exist in the schema but are unused. Rules:

- **No auth checks.** Never add `auth()->user()`, `auth()->check()`, `Auth::` facade, `->middleware('auth')`, Policies, `Gate::define`, or `$this->authorize()`. Scope data by domain FK (`book_id`, `chapter_id`), not by user. Grandfathered call sites live in `AiController`, `EditorialReviewController`, `StreamsConversation`, `HandleInertiaRequests` — do not extend to new controllers. Enforced by `tests/Unit/GuardrailsTest.php` (CI + local) and, for this operator, by `~/.claude/hooks/protect-manuscript-authz.py` (blocks `Edit`/`Write`).

- **Every controller has a Feature test.** `App\Http\Controllers\FooController` ⇒ `tests/Feature/FooControllerTest.php`. Enforced by `tests/Unit/GuardrailsTest.php`. 7 existing controllers are grandfathered (see the test for the list) — new controllers follow the convention.

- **Browser test per feature, not per page.** The first page added under a new feature gets a `tests/Browser/<Feature>Test.php`. Subsequent tweaks under the same feature do not need a new browser test, but the existing one must still pass.

- **Bugfixes are red-green.** Commit a failing test that reproduces the bug BEFORE the fix commit — or include `// red-green: see <test name>` in the fix commit message.

- **Migrations run against BOTH databases.** `native:run` does NOT auto-migrate. After `php artisan make:migration`: run `php artisan migrate`, then `DB_DATABASE=database/nativephp.sqlite php artisan migrate --no-interaction` (from the main repo the shorthand is `php artisan native:migrate`). For this operator, a `PostToolUse` hook at `~/.claude/hooks/remind-native-migrate.py` nudges once per session.

- **PR-time verification.** Any PR touching `app/Http/Controllers/`, `app/Http/Requests/`, or `database/migrations/` must invoke `superpowers:verification-before-completion` with proof that `php artisan test --compact` passes AND that `native:migrate` ran.

## Design Implementation

- When implementing design changes, match Pencil designs pixel-perfectly. Always compare the design screenshot against the code component — don't assume styling from Pencil designs maps 1:1 without verification.

## Output Format

- Always write plans and protocols as markdown files (e.g., `docs/plans/<feature>.md`), never inline in the conversation.

## NativePHP Database

- This is a NativePHP app. At runtime, it uses a dynamically registered `nativephp` database connection pointing to `database/nativephp.sqlite`.
- After creating and running migrations with `php artisan migrate`, also run them against the NativePHP database: `DB_DATABASE=database/nativephp.sqlite php artisan migrate --no-interaction`
- The `nativephp` connection name is NOT available from the CLI — use the `DB_DATABASE` env override instead.

## Cortex

- Cortex folder for this project: `MANUSCRIPT`

## Mockups & Visual Design

- Always use Pencil (.pen files) for mockups and visual design work. Never use browser-based visual companions.

## Git Workflow

### Branch Rules
- The working branch is `dev`. All work happens on `dev` or feature branches off `dev`.
- PRs MUST target `dev`, never `main`.
- Feature branches MUST be created from `dev`.

### NEVER Switch Branches
- NEVER run `git checkout`, `git switch`, or any command that changes the current branch without explicit user permission. This is a hard rule — no exceptions for "just checking something".
- To inspect another branch's files: use `git show other-branch:path/to/file`.
- To compare branches: use `git diff main...dev` (three-dot syntax) or `git log --oneline --graph`.
- To check branch topology: use `git log --oneline --all --graph`.

### After Merging PRs
- After any merge (PR merge, rebase, or manual merge), ALWAYS verify the result:
  1. Run `git diff dev~1 --stat` to see what changed.
  2. For each file that was part of the merged work, verify the expected changes are present (grep for key identifiers, not just file existence).
  3. If any changes were silently dropped by the merge, restore them immediately.
- Common causes of silent merge loss: squash-merge flattening intermediate fixes, auto-conflict resolution picking the wrong side, stale base branches.

### Before Starting Work
- Confirm the current branch with `git branch --show-current`.
- If not on `dev` or a feature branch off `dev`, ask the user before proceeding.

## Workflow Preferences

- When the user asks for implementation, prioritize code changes over planning documents. Only produce a plan if explicitly asked for one.
- Try the simplest approach first. Before committing to a complex solution, briefly state the approach and wait for confirmation if there are multiple options.

## Worktree Bootstrap (CRITICAL)

The main repo's `.env` is sacred — its `APP_KEY` decrypts the user's runtime AI provider credentials in `database/nativephp.sqlite`. If overwritten, the data is **unrecoverable**. A `~/.claude/hooks/protect-manuscript-env.py` PreToolUse hook now blocks the most dangerous patterns, but you must ALSO follow these rules so the hook is a backstop, not the only line of defense:

- Bash tool calls do **NOT** inherit `cwd` from a previous Bash call. Each invocation starts in the parent shell's default cwd (the main repo). Any worktree-targeting bootstrap command MUST start with `cd <full-worktree-path> && ...` — chained in a single Bash call.
- Bootstrap commands that REQUIRE a leading `cd <worktree>`:
  - `cp .env.example .env`
  - `php artisan key:generate`
  - `php artisan migrate:fresh`, `migrate:reset`, `db:wipe`
  - Any redirect/tee/sed targeting `.env`
- Before any bootstrap, verify: `pwd && [ -f .env ] && echo "EXISTS — DO NOT OVERWRITE" || echo "ok to bootstrap"`.
- If a worktree already has a `.env`, do not overwrite it. Reuse it.
- The `protect-manuscript-env.py` hook will block these patterns when cwd is the main repo. If it blocks you, that means you forgot the `cd` — fix the command, do not bypass the hook.

## Batch Workflow (`/batch`) Rules

When orchestrating parallel agents via `/batch`:

### Decomposition
- **Foundation-first**: Identify any shared infrastructure (test selectors like `data-*` attributes, type changes, factory additions, shared imports) BEFORE splitting work. Land it as Unit 0 in a single PR. Sibling units depend on Unit 0 having merged. Never let two agents add the same line to the same file — git's auto-merge will silently produce duplicates.
- **Slice by file or by module, never by line**. Two units touching different functions in `editor.tsx` is fine; two units touching the same JSX element is not.

### Concurrency
- **Cap parallel agents at 3**. API 529 overload during the multi-panel batch killed 4/7 agents in flight. Three at a time roughly doubles wall time but the recovery path is much shorter when one dies.
- **Stagger launches** if you must exceed 3 — give each subagent a few seconds head start before launching the next.

### Verification (mandatory after each agent notification)
The `<task-notification>` "completed" status only means the agent process exited. It does NOT mean the work landed. After every notification, before marking a unit "done":
1. `gh pr view <num> --json state,mergeable` — must return a real PR (not empty/error)
2. `git -C <worktree-path> status -s` — must be clean if the agent claims a PR
3. `git -C <worktree-path> log --oneline -1` — must show a unit-specific commit, not just the base
4. If ANY check fails → unit is "incomplete," not "done." Either retry or salvage carefully (see Salvage Protocol below).
5. **Never trust the agent's `PR: <url>` text alone** — verify with `gh pr view`.

### Salvage Protocol (failed worktree pickup)

When an agent dies mid-task with uncommitted work in its worktree, picking up the work is risky. Auto-merge from rebases and post-Edit format hooks can introduce duplicate lines, broken imports, or unmerged conflict markers that the diff alone won't catch. Mandatory steps:

1. `git -C <worktree> status -s` — list every uncommitted file
2. **Read every uncommitted file fully with the Read tool** — do not trust the diff or auto-merge output. Look for: duplicate lines, unmerged `<<<<<<<` markers, format-hook artifacts (e.g., duplicate imports, repeated attributes).
3. Run the unit's tests in the worktree before committing
4. Commit + push + `gh pr create`
5. Verify with `gh pr view <num>`
6. If the worktree was bootstrapped by the failed agent, **never re-run bootstrap** — trust the existing `.env` / `vendor` / `node_modules`. Re-bootstrap risks the cwd-confusion class of bug.

### Conflict resolution after sibling-PR merges

When rebasing a sibling PR onto a freshly merged base:
1. Run `git rebase origin/dev` — it will fail with conflicts
2. For EACH conflicted file: `Read` it fully (not just the conflict region), resolve, save with `Write`
3. After `git add` + `git rebase --continue`, **`Read` the rebased file again** to check for auto-merge artifacts (duplicate lines from line-adjacent edits)
4. Run the affected tests before force-pushing
5. Force-push with `--force-with-lease` (never `--force`)
