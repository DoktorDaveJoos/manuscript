# Automated Testing Design

**Date:** 2026-03-26
**Goal:** Achieve high confidence that the app works through automated tests, prioritizing critical user journeys end-to-end, then backfilling controller gaps, then adding CI coverage tracking.

## Decisions

- **Journey-first approach:** Browser E2E tests for critical user workflows land first, controller gap backfill second, CI coverage last.
- **AI mocking in browser tests:** Since browser tests run against a separate server process, `Http::fake()` has no effect. Instead, seed completed AI results directly in the database. AI dispatch/integration logic is already covered by existing feature tests.
- **Test volume target:** ~25-35 browser E2E tests (moderate maintenance), ~8-12 feature tests for real controller gaps.
- **Selector strategy:** Use `data-testid` attributes on critical interactive elements to decouple tests from styling.
- **Local-first:** Tests optimized for local development speed; CI is a safety net.

## Browser Test Infrastructure

### Shared Setup

The existing `OnboardingTest.php` uses `AppSetting::set('crash_report_prompted', true)` in `beforeEach` to suppress the crash report dialog. Every browser test needs this. Add a shared `beforeEach` in `tests/Pest.php` for the `Browser` directory:

```php
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        AppSetting::set('crash_report_prompted', true);
    })
    ->in('Browser');
```

This replaces the per-file `beforeEach` in `OnboardingTest.php` (remove the duplicate after adding the global one).

### Selector Strategy

Add `data-testid` attributes to critical interactive elements:
```tsx
<button data-testid="create-book">Create Book</button>
```

Use in tests:
```php
$page->click('[data-testid="create-book"]');
```

Only add `data-testid` where CSS selectors would be brittle. Text-based selectors (`assertSee`) are fine for content verification.

### Assertion Pattern

Every page navigation must include:
```php
$page->assertNoJavaScriptErrors();
```

This catches React crashes, undefined references, and other JS errors that would silently break the app.

### Async Operations & Timing

Playwright has built-in auto-waiting. Use `assertSee()` as the synchronization point for async operations — it waits for text to appear within the default timeout. For operations that trigger server-side processing (import parsing, export preview), rely on Playwright's auto-wait for network idle rather than manual sleeps.

### Rich Text Editor Interaction

The chapter editor uses a rich text editor (contenteditable). Standard `type()` and `fill()` won't work on contenteditable divs. For tests involving the editor:
- Verify the editor **renders with existing content** (assertSee on content)
- For content entry, use `$page->click('.editor-selector')` then `$page->keyboard()->type('text')`
- Scope editor tests to verify the save/reload cycle rather than complex editing operations

## Phase 1: Browser E2E Tests for Critical User Journeys

### File Structure

```
tests/Browser/
├── OnboardingTest.php          (existing — enhance)
├── ImportTest.php              (new)
├── ChapterEditorTest.php       (new)
├── DashboardTest.php           (new)
├── WikiTest.php                (new)
├── EditorialReviewTest.php     (new)
├── ExportTest.php              (new)
└── SettingsTest.php            (new)
```

### Journey 1: Onboarding (existing — enhance)

Already has 8 tests covering: create book, skip import, book library, second book with PRO, title validation, crash dialog dismissal, free tier lock. Enhance with:
- Verify storyline creation after onboarding completes
- Verify redirect to editor after first book creation

### Journey 2: Import a Manuscript (4-5 tests)

**File:** `ImportTest.php`

| Test | What it proves |
|------|---------------|
| Import DOCX with chapters → preview shows detected chapters | Parser + preview UI work |
| Confirm import → chapters created with correct content | Full import pipeline |
| Import markdown file → correct chapter detection | Alternative format works |
| Import file with no headings → single chapter fallback | Edge case handling |
| Cancel import → no data created | Cleanup works |

**Data setup:** Use existing fixtures in `tests/Feature/fixtures/` (chapters.docx, chapters.md, etc.)

### Journey 3: Write & Edit Chapters (4-5 tests)

**File:** `ChapterEditorTest.php`

| Test | What it proves |
|------|---------------|
| Navigate to editor → see chapter list and content | Editor renders with data |
| Create new chapter → appears in sidebar | Chapter creation works |
| Editor renders existing scene content correctly | Content display works |
| Create version snapshot → snapshot appears in history | Versioning works |
| Delete chapter → moves to trash | Soft delete works |

**Data setup:** `createBookWithChapters(3)`

**Note:** Chapter reordering (drag-and-drop) is tested at the feature level, not browser level. Sortable library drag interactions are unreliable in headless Playwright.

### Journey 4: Dashboard & Writing Goals (3-4 tests)

**File:** `DashboardTest.php`

| Test | What it proves |
|------|---------------|
| Dashboard renders with book stats | Page loads without errors |
| Set daily writing goal → goal persists | Goal CRUD works |
| Writing session updates progress display | Progress tracking works |
| Milestone dismissal works | UI interaction works |

**Data setup:** `createBookWithChapters(1)` with `daily_word_count_goal` set on the book + `WritingSession::factory()` for today + `License::factory()->create()` (dashboard features are PRO-gated)

### Journey 5: Wiki & World-Building (3-4 tests)

**File:** `WikiTest.php`

| Test | What it proves |
|------|---------------|
| Wiki page renders with empty state | Page loads |
| Create character → appears in list | Character CRUD works |
| Create wiki entry → appears in list | Entry CRUD works |
| Delete character → removed from list | Deletion works |

**Data setup:** `createBookWithChapters(1)` + navigate to wiki

### Journey 6: Editorial Review (4-5 tests)

**File:** `EditorialReviewTest.php`

Since browser tests run in a separate server process, `Http::fake()` cannot mock AI responses. Instead, seed completed review data directly in the database and verify the UI renders it correctly. The AI dispatch/job pipeline is already tested in `EditorialReviewControllerTest.php` (27 feature tests).

| Test | What it proves |
|------|---------------|
| Editorial review page renders with seeded review data | Results page works |
| Review sections display with scores and findings | Section rendering works |
| Resolve a finding → finding marked resolved | Finding interaction works |
| Empty state when no review exists | Empty state works |
| Review with chapter notes displays correctly | Chapter notes UI works |

**Data setup:** `createBookWithChapters(3)` + `License::factory()->create()` (required — editorial review routes are behind `license` middleware) + seed `EditorialReview` with `EditorialReviewSection` records via factories. Chapter notes and findings are seeded via direct `create()` calls (no factory exists for `EditorialReviewChapterNote`).

### Journey 7: Export / Publish (3-4 tests)

**File:** `ExportTest.php`

| Test | What it proves |
|------|---------------|
| Publish page renders with book metadata | Page loads with data |
| Update book metadata (ISBN, publisher) → persists | Settings CRUD works |
| Export preview renders without errors | Preview pipeline works |
| Upload cover image → image displays | File upload works |

**Data setup:** Book with chapters + PRO license

### Journey 8: Settings & AI Configuration (3-4 tests)

**File:** `SettingsTest.php`

| Test | What it proves |
|------|---------------|
| Settings page renders all sections | Page loads |
| Update writing style → persists | Style CRUD works |
| Configure AI provider → API key saved | AI settings work |
| Update prose pass rules → persists | Rules CRUD works |

**Data setup:** Minimal — settings page doesn't require book context for global settings

## Phase 2: Controller Gap Backfill

After auditing existing tests, most controllers already have coverage. The real gaps are:

### AiPreparationController (4 tests — no existing coverage)

All routes are behind `license` middleware. Tests need `License::factory()->create()`.

| Test | Method |
|------|--------|
| Start preparation dispatches job (use `Bus::fake()`) | `start()` |
| Start without API key returns error | `start()` |
| Status returns preparation progress | `status()` |
| Status with no preparation returns null state | `status()` |

### NormalizationController (4 tests — service tested, HTTP routes not)

| Test | Method |
|------|--------|
| Preview book normalization | `previewBook()` |
| Apply book normalization | `applyBook()` |
| Preview chapter normalization | `previewChapter()` |
| Apply chapter normalization | `applyChapter()` |

### Controllers Already Covered (no new tests needed)

- **SettingsController** — `AppSettingsControllerTest.php` covers all 6 methods
- **SearchController** — `SearchTest.php` covers search + replaceAll with 9 tests
- **CanvasController** — `CanvasPageTest.php` covers index
- **WikiController** — `WikiPageTest.php` + `WikiEntryCreationTest.php` cover all 7 methods

**Total: ~8 feature tests** for real gaps

## Phase 3: CI Coverage Tracking

### Prerequisite: Fix CI Branch Triggers

The workflow at `.github/workflows/tests.yml` triggers on `develop`, not `dev`. Update to include `dev`:

```yaml
on:
  push:
    branches: [dev, develop, main, master, workos]
  pull_request:
    branches: [dev, develop, main, master, workos]
```

### Prerequisite: Playwright in CI

Browser tests require Playwright browsers. Add to the CI workflow before running tests:

```yaml
- name: Install Playwright browsers
  run: npx playwright install --with-deps chromium
```

Note: The Pest browser plugin auto-starts a Laravel development server for browser tests via its `ServerManager`. No explicit `php artisan serve` step is needed in CI.

### Coverage Tracking

1. Replace `./vendor/bin/pest` with:
   ```bash
   ./vendor/bin/pest --coverage --coverage-clover=coverage.xml
   ```

2. Add Codecov upload step after tests:
   ```yaml
   - name: Upload coverage to Codecov
     uses: codecov/codecov-action@v4
     with:
       files: coverage.xml
       fail_ci_if_error: false
   ```

3. **Do not set `--min` threshold initially.** Run coverage first to establish a baseline, then set the floor at or slightly below the measured value.

### Local Coverage

Add composer script for local use:
```json
"coverage": "@php artisan test --coverage"
```

## Out of Scope

- **Plot board E2E tests** — Complex canvas interactions are hard to test in headless browsers. Better tested at the feature level (which already exists).
- **License activation E2E** — Depends on external Polar service. Tested at feature level with mocked responses (already exists).
- **Visual regression testing** — Pixel-level comparisons are a separate concern. Not part of this effort.
- **Mutation testing** — Valuable but a separate initiative after coverage baseline is established.
- **Performance/load testing** — Desktop app, single user. Not applicable.

## Success Criteria

- All 8 critical user journeys have browser E2E tests that pass
- Real controller gaps (AiPreparation, Normalization) are covered with feature tests
- CI reports coverage percentage on every PR
- Full test suite runs in under 3 minutes locally
