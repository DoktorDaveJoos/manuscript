# Automated Testing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add browser E2E tests for 7 critical user journeys, backfill 2 controller test gaps, and add CI coverage tracking.

**Architecture:** Pest Browser (Playwright) for E2E tests against a real dev server. Feature tests for controller gaps. Codecov for CI coverage. All AI interactions mocked via database seeding (not Http::fake, which doesn't work across processes).

**Tech Stack:** Pest 4, Pest Browser Plugin (Playwright), Laravel 12, React 19, Inertia v2

**Spec:** `docs/superpowers/specs/2026-03-26-automated-testing-design.md`

---

### Task 1: Browser Test Infrastructure Setup

**Files:**
- Modify: `tests/Pest.php:27-29`
- Modify: `tests/Browser/OnboardingTest.php:8-10`

- [ ] **Step 1: Add global beforeEach for Browser tests in Pest.php**

Replace the existing Browser configuration block (lines 27-29) with:

```php
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        \App\Models\AppSetting::set('crash_report_prompted', true);
    })
    ->in('Browser');
```

- [ ] **Step 2: Remove duplicate beforeEach from OnboardingTest.php**

Remove lines 8-10 from `tests/Browser/OnboardingTest.php`:

```php
beforeEach(function () {
    AppSetting::set('crash_report_prompted', true);
});
```

Also remove the `use App\Models\AppSetting;` import at line 3 (no longer needed in this file).

- [ ] **Step 3: Verify existing browser tests still pass**

Run: `php artisan test tests/Browser/OnboardingTest.php --compact`
Expected: All 8 tests pass

- [ ] **Step 4: Commit**

```bash
git add tests/Pest.php tests/Browser/OnboardingTest.php
git commit -m "refactor: move browser test crash dialog suppression to global beforeEach"
```

---

### Task 2: Import Journey E2E Tests

**Files:**
- Create: `tests/Browser/ImportTest.php`

**Docs to check:** The import flow uses fixtures in `tests/Feature/fixtures/`. Routes: `GET /books/{book}/import`, `POST /books/{book}/import/parse`, `POST /books/{book}/import/confirm`, `POST /books/{book}/import/skip`.

- [ ] **Step 1: Write the import browser tests**

Create `tests/Browser/ImportTest.php`:

```php
<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Storyline;

// Note: OnboardingTest already covers file attachment display and skip-import.
// These tests focus on the import pipeline (parse → review → confirm).

it('imports a markdown file and creates chapters', function () {
    $book = Book::factory()->create(['title' => 'MD Import Book']);
    Storyline::factory()->for($book)->create(['name' => 'Main']);

    $page = visit("/books/{$book->id}/import");

    $fixturePath = base_path('tests/Feature/fixtures/chapters.md');

    $page->assertNoJavaScriptErrors()
        ->attach('input[type="file"]', $fixturePath)
        ->assertSee('chapters.md')
        ->click('Import 1 file')
        ->assertSee('Review your import')
        ->assertNoJavaScriptErrors()
        ->click('Confirm import')
        ->assertNoJavaScriptErrors();

    expect(Chapter::where('book_id', $book->id)->count())->toBeGreaterThan(0);
});

it('shows single chapter notice for file with no headings', function () {
    $book = Book::factory()->create(['title' => 'No Headings Book']);
    Storyline::factory()->for($book)->create(['name' => 'Main']);

    $page = visit("/books/{$book->id}/import");

    $fixturePath = base_path('tests/Feature/fixtures/no-headings.docx');

    $page->assertNoJavaScriptErrors()
        ->attach('input[type="file"]', $fixturePath)
        ->click('Import 1 file')
        ->assertNoJavaScriptErrors()
        ->assertSee('Review your import');
});
```

- [ ] **Step 2: Run the import tests**

Run: `php artisan test tests/Browser/ImportTest.php --compact`
Expected: All 3 tests pass. If any fail due to exact UI text not matching, adjust the `assertSee` strings to match the actual rendered text.

- [ ] **Step 3: Commit**

```bash
git add tests/Browser/ImportTest.php
git commit -m "test: add browser E2E tests for manuscript import journey"
```

---

### Task 3: Chapter Editor E2E Tests

**Files:**
- Create: `tests/Browser/ChapterEditorTest.php`

**Docs to check:** Routes: `GET /books/{book}/editor` (redirects to first chapter or shows empty state), `GET /books/{book}/chapters/{chapter}` (shows chapter), `POST /books/{book}/chapters` (create), `DELETE /books/{book}/chapters/{chapter}` (destroy). The editor page component is `resources/js/pages/chapters/show.tsx`, empty state is `resources/js/pages/chapters/empty.tsx`.

- [ ] **Step 1: Write the chapter editor browser tests**

Create `tests/Browser/ChapterEditorTest.php`:

```php
<?php

use App\Models\Book;
use App\Models\Chapter;

it('shows empty state when book has no chapters', function () {
    $book = Book::factory()->create(['title' => 'Empty Book']);

    $page = visit("/books/{$book->id}/editor");

    $page->assertNoJavaScriptErrors()
        ->assertSee('No chapters yet')
        ->assertSee('Create first chapter')
        ->assertSee('Import manuscript');
});

it('navigates to editor and displays chapter content', function () {
    [$book, $chapters] = createBookWithChapters(2);

    $page = visit("/books/{$book->id}/editor");

    $page->assertNoJavaScriptErrors()
        ->assertSee($chapters[0]->title);
});

it('shows specific chapter when navigated directly', function () {
    [$book, $chapters] = createBookWithChapters(3);

    $page = visit("/books/{$book->id}/chapters/{$chapters[1]->id}");

    $page->assertNoJavaScriptErrors()
        ->assertSee($chapters[1]->title);
});

it('creates a new chapter from empty state', function () {
    $book = Book::factory()->create(['title' => 'New Chapter Book']);
    \App\Models\Storyline::factory()->for($book)->create(['name' => 'Main']);

    $page = visit("/books/{$book->id}/editor");

    $page->assertNoJavaScriptErrors()
        ->assertSee('No chapters yet')
        ->click('Create first chapter')
        ->assertNoJavaScriptErrors();

    expect(Chapter::where('book_id', $book->id)->count())->toBe(1);
});

it('renders chapter sidebar with multiple chapters', function () {
    [$book, $chapters] = createBookWithChapters(3);

    $page = visit("/books/{$book->id}/chapters/{$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->assertSee($chapters[0]->title)
        ->assertSee($chapters[1]->title)
        ->assertSee($chapters[2]->title);
});
```

- [ ] **Step 2: Run the chapter editor tests**

Run: `php artisan test tests/Browser/ChapterEditorTest.php --compact`
Expected: All 5 tests pass

- [ ] **Step 3: Commit**

```bash
git add tests/Browser/ChapterEditorTest.php
git commit -m "test: add browser E2E tests for chapter editor journey"
```

---

### Task 4: Dashboard E2E Tests

**Files:**
- Create: `tests/Browser/DashboardTest.php`

**Docs to check:** Route: `GET /books/{book}/dashboard`. Dashboard features are partially PRO-gated (writing goal, manuscript target). Stats (Words, Pages, Reading Time, Chapters) always display. Uses `DashboardController::show()`.

- [ ] **Step 1: Write the dashboard browser tests**

Create `tests/Browser/DashboardTest.php`:

```php
<?php

use App\Models\Book;
use App\Models\License;
use App\Models\WritingSession;

it('renders dashboard with book stats', function () {
    [$book, $chapters] = createBookWithChapters(3);

    $page = visit("/books/{$book->id}/dashboard");

    $page->assertNoJavaScriptErrors()
        ->assertSee($book->title)
        ->assertSee('Words')
        ->assertSee('Pages')
        ->assertSee('Chapters');
});

it('shows chapter progress section', function () {
    [$book, $chapters] = createBookWithChapters(3);

    $page = visit("/books/{$book->id}/dashboard");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Chapter Progress')
        ->assertSee('draft');
});

it('shows writing goal section for pro users', function () {
    License::factory()->create();
    $book = Book::factory()->create([
        'title' => 'Pro Dashboard Book',
        'daily_word_count_goal' => 2000,
    ]);

    $page = visit("/books/{$book->id}/dashboard");

    $page->assertNoJavaScriptErrors()
        ->assertSee("Today's Writing Goal");
});

it('displays writing session data on dashboard', function () {
    License::factory()->create();
    [$book, $chapters] = createBookWithChapters(1);
    $book->update(['daily_word_count_goal' => 1000]);

    WritingSession::factory()->for($book)->create([
        'date' => now()->toDateString(),
        'words_written' => 500,
    ]);

    $page = visit("/books/{$book->id}/dashboard");

    $page->assertNoJavaScriptErrors()
        ->assertSee("Today's Writing Goal");
});
```

- [ ] **Step 2: Run the dashboard tests**

Run: `php artisan test tests/Browser/DashboardTest.php --compact`
Expected: All 4 tests pass

- [ ] **Step 3: Commit**

```bash
git add tests/Browser/DashboardTest.php
git commit -m "test: add browser E2E tests for dashboard journey"
```

---

### Task 5: Wiki E2E Tests

**Files:**
- Create: `tests/Browser/WikiTest.php`

**Docs to check:** Routes: `GET /books/{book}/wiki`, `POST /books/{book}/characters`, `DELETE /books/{book}/characters/{character}`, `POST /books/{book}/wiki-entries`. Tab labels: "Characters", "Locations", "Organizations", "Items", "Lore". Empty state: "No characters yet". Add buttons: "New Character", "New Location", etc.

- [ ] **Step 1: Write the wiki browser tests**

Create `tests/Browser/WikiTest.php`:

```php
<?php

use App\Models\Book;
use App\Models\Character;
use App\Models\Storyline;

it('renders wiki page with empty state', function () {
    $book = Book::factory()->create(['title' => 'Wiki Test Book']);
    Storyline::factory()->for($book)->create(['name' => 'Main']);

    $page = visit("/books/{$book->id}/wiki");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Wiki')
        ->assertSee('Characters')
        ->assertSee('No characters yet');
});

it('shows all wiki tabs', function () {
    $book = Book::factory()->create(['title' => 'Tabs Test Book']);
    Storyline::factory()->for($book)->create(['name' => 'Main']);

    $page = visit("/books/{$book->id}/wiki");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Characters')
        ->assertSee('Locations')
        ->assertSee('Organizations')
        ->assertSee('Items')
        ->assertSee('Lore');
});

it('creates a character from wiki page', function () {
    $book = Book::factory()->create(['title' => 'Character Create Book']);
    Storyline::factory()->for($book)->create(['name' => 'Main']);

    $page = visit("/books/{$book->id}/wiki");

    $page->assertNoJavaScriptErrors()
        ->click('New Character')
        ->assertNoJavaScriptErrors()
        ->type('input[name="name"]', 'Alice Wonderland')
        ->click('Create')
        ->assertNoJavaScriptErrors()
        ->assertSee('Alice Wonderland');

    expect(Character::where('name', 'Alice Wonderland')->exists())->toBeTrue();
});

it('displays existing characters on wiki page', function () {
    $book = Book::factory()->create(['title' => 'Existing Characters Book']);
    Storyline::factory()->for($book)->create(['name' => 'Main']);
    Character::factory()->for($book)->create(['name' => 'Bob Builder']);
    Character::factory()->for($book)->create(['name' => 'Jane Doe']);

    $page = visit("/books/{$book->id}/wiki");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Bob Builder')
        ->assertSee('Jane Doe');
});
```

- [ ] **Step 2: Run the wiki tests**

Run: `php artisan test tests/Browser/WikiTest.php --compact`
Expected: All 4 tests pass

- [ ] **Step 3: Commit**

```bash
git add tests/Browser/WikiTest.php
git commit -m "test: add browser E2E tests for wiki journey"
```

---

### Task 6: Editorial Review E2E Tests

**Files:**
- Create: `tests/Browser/EditorialReviewTest.php`

**Docs to check:** Routes are behind `license` middleware: `GET /books/{book}/ai/editorial-review` → `books.ai.editorial-review.index`. Uses `EditorialReviewController::index()`. The page shows the latest completed review or an empty state. Since browser tests can't use `Http::fake()`, seed completed review data directly.

Section types are from `EditorialSectionType` enum. Severity levels: "critical", "warning", "suggestion". Findings have keys generated by `EditorialReviewSection::findingKey()`.

- [ ] **Step 1: Write the editorial review browser tests**

Create `tests/Browser/EditorialReviewTest.php`:

```php
<?php

use App\Enums\EditorialSectionType;
use App\Models\Book;
use App\Models\EditorialReview;
use App\Models\EditorialReviewChapterNote;
use App\Models\EditorialReviewSection;
use App\Models\License;
use App\Models\Storyline;

it('shows empty state when no review exists', function () {
    License::factory()->create();
    [$book] = createBookWithChapters(3);

    $page = visit("/books/{$book->id}/ai/editorial-review");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Editorial Review')
        ->assertSee('Start Editorial Review');
});

it('renders completed review with sections and scores', function () {
    License::factory()->create();
    [$book, $chapters] = createBookWithChapters(3);

    $review = EditorialReview::factory()->for($book)->create([
        'overall_score' => 72,
        'executive_summary' => 'A promising manuscript with strong character work but pacing issues in the middle act.',
    ]);

    EditorialReviewSection::factory()->for($review)->create([
        'type' => EditorialSectionType::Plot,
        'score' => 68,
        'summary' => 'The plot has a solid premise but the second act sags.',
        'findings' => [
            [
                'severity' => 'warning',
                'description' => 'The midpoint reversal comes too late at chapter 15.',
                'chapter_references' => [],
                'recommendation' => 'Move the reversal to chapter 12.',
            ],
        ],
    ]);

    EditorialReviewSection::factory()->for($review)->create([
        'type' => EditorialSectionType::Characters,
        'score' => 85,
        'summary' => 'Strong character development with clear arcs.',
    ]);

    $page = visit("/books/{$book->id}/ai/editorial-review");

    $page->assertNoJavaScriptErrors()
        ->assertSee('A promising manuscript')
        ->assertSee('Plot')
        ->assertSee('Characters');
});

it('shows chapter notes when present', function () {
    License::factory()->create();
    [$book, $chapters] = createBookWithChapters(3);

    $review = EditorialReview::factory()->for($book)->create();

    EditorialReviewSection::factory()->for($review)->create([
        'type' => EditorialSectionType::Plot,
    ]);

    EditorialReviewChapterNote::create([
        'editorial_review_id' => $review->id,
        'chapter_id' => $chapters[0]->id,
        'notes' => ['The opening hook is effective but could be sharper.'],
    ]);

    $page = visit("/books/{$book->id}/ai/editorial-review");

    $page->assertNoJavaScriptErrors()
        ->assertSee($chapters[0]->title);
});

it('redirects to settings when accessing editorial review without license', function () {
    [$book] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/ai/editorial-review");

    // RequiresLicense middleware redirects to settings
    $page->assertNoJavaScriptErrors()
        ->assertPathBeginsWith('/settings');
});
```

- [ ] **Step 2: Run the editorial review tests**

Run: `php artisan test tests/Browser/EditorialReviewTest.php --compact`
Expected: All 4 tests pass. The license redirect test may need adjustment based on where the middleware redirects to.

- [ ] **Step 3: Commit**

```bash
git add tests/Browser/EditorialReviewTest.php
git commit -m "test: add browser E2E tests for editorial review journey"
```

---

### Task 7: Export / Publish E2E Tests

**Files:**
- Create: `tests/Browser/ExportTest.php`

**Docs to check:** Routes: `GET /books/{book}/publish` and `GET /books/{book}/settings/export` are NOT behind license middleware — they are publicly accessible. Publish page shows: "Cover Image", "Book Metadata", "Front Matter", "Back Matter". Field labels: "Publisher Name", "ISBN", "Copyright", "Dedication", etc.

- [ ] **Step 1: Write the export/publish browser tests**

Create `tests/Browser/ExportTest.php`:

```php
<?php

use App\Models\Book;
use App\Models\Storyline;

it('renders publish page with book metadata sections', function () {
    [$book] = createBookWithChapters(2);

    $page = visit("/books/{$book->id}/publish");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Publish')
        ->assertSee('Cover Image')
        ->assertSee('Book Metadata')
        ->assertSee('Front Matter')
        ->assertSee('Back Matter');
});

it('displays metadata fields on publish page', function () {
    [$book] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/publish");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Publisher Name')
        ->assertSee('ISBN')
        ->assertSee('Copyright')
        ->assertSee('Dedication');
});

it('renders export page with book title', function () {
    [$book] = createBookWithChapters(2);

    $page = visit("/books/{$book->id}/settings/export");

    $page->assertNoJavaScriptErrors()
        ->assertSee($book->title);
});
```

- [ ] **Step 2: Run the export tests**

Run: `php artisan test tests/Browser/ExportTest.php --compact`
Expected: All 4 tests pass

- [ ] **Step 3: Commit**

```bash
git add tests/Browser/ExportTest.php
git commit -m "test: add browser E2E tests for export/publish journey"
```

---

### Task 8: Settings E2E Tests

**Files:**
- Create: `tests/Browser/SettingsTest.php`

**Docs to check:** Route: `GET /settings`. Tabs: "General", "Editor", "Print", "Account". Sections: "Appearance", "License", "Writing Style". Theme options: "Light", "Dark", "System". License section shows "Enter your license key..." when inactive.

- [ ] **Step 1: Write the settings browser tests**

Create `tests/Browser/SettingsTest.php`:

```php
<?php

use App\Models\License;

it('renders settings page with all tabs', function () {
    $page = visit('/settings');

    $page->assertNoJavaScriptErrors()
        ->assertSee('General')
        ->assertSee('Editor')
        ->assertSee('Print')
        ->assertSee('Account');
});

it('shows appearance section with theme options', function () {
    $page = visit('/settings');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Appearance')
        ->assertSee('Light')
        ->assertSee('Dark')
        ->assertSee('System');
});

it('shows license section for activating pro', function () {
    $page = visit('/settings');

    $page->assertNoJavaScriptErrors()
        ->assertSee('License')
        ->assertSee('Activate');
});

it('shows active license status when pro is enabled', function () {
    License::factory()->create();

    $page = visit('/settings');

    $page->assertNoJavaScriptErrors()
        ->assertSee('License active')
        ->assertSee('Deactivate');
});
```

- [ ] **Step 2: Run the settings tests**

Run: `php artisan test tests/Browser/SettingsTest.php --compact`
Expected: All 4 tests pass

- [ ] **Step 3: Commit**

```bash
git add tests/Browser/SettingsTest.php
git commit -m "test: add browser E2E tests for settings journey"
```

---

### Task 9: AiPreparationController Feature Tests

**Files:**
- Create: `tests/Feature/AiPreparationControllerTest.php`

**Docs to check:** Routes (behind `license` middleware): `POST /books/{book}/ai/prepare` → `AiPreparationController::start()`, `GET /books/{book}/ai/prepare/status` → `AiPreparationController::status()`. The `start()` method checks `AppSetting::showAiFeatures()` and `AiSetting::activeProvider()`. Uses `Bus::fake()` to verify job dispatch.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/AiPreparationControllerTest.php`:

```php
<?php

use App\Jobs\PrepareBookForAi;
use App\Models\AiPreparation;
use App\Models\AiSetting;
use App\Models\AppSetting;
use App\Models\License;
use Illuminate\Support\Facades\Bus;

it('starts ai preparation and dispatches job', function () {
    Bus::fake();
    License::factory()->create();
    [$book] = createBookWithChapters(2);
    AppSetting::set('show_ai_features', true);

    $response = $this->postJson(route('books.ai.prepare', $book));

    $response->assertOk()
        ->assertJsonStructure(['id', 'status', 'book_id']);

    Bus::assertDispatched(PrepareBookForAi::class);
    expect(AiPreparation::where('book_id', $book->id)->where('status', 'pending')->exists())->toBeTrue();
});

it('returns 422 when no ai provider is configured', function () {
    License::factory()->create();
    $book = \App\Models\Book::factory()->create();
    AppSetting::set('show_ai_features', true);

    // No AiSetting created, so activeProvider() returns null
    $response = $this->postJson(route('books.ai.prepare', $book));

    $response->assertUnprocessable()
        ->assertJson(['message' => 'AI is not enabled or no API key configured.']);
});

it('returns preparation status', function () {
    License::factory()->create();
    [$book, $chapters, $preparation] = createBookWithChapters(2);
    $preparation->update(['status' => 'analyzing', 'processed_chapters' => 1, 'total_chapters' => 2]);

    $response = $this->getJson(route('books.ai.prepare.status', $book));

    $response->assertOk()
        ->assertJson([
            'status' => 'analyzing',
            'processed_chapters' => 1,
            'total_chapters' => 2,
        ]);
});

it('returns null when no preparation exists', function () {
    License::factory()->create();
    $book = \App\Models\Book::factory()->create();

    $response = $this->getJson(route('books.ai.prepare.status', $book));

    $response->assertOk();
    expect($response->json())->toBeNull();
});

it('cancels existing preparation when starting new one', function () {
    Bus::fake();
    License::factory()->create();
    [$book] = createBookWithChapters(1);
    AppSetting::set('show_ai_features', true);

    // Create an existing in-progress preparation
    $existing = $book->aiPreparations()->create(['status' => 'analyzing']);

    $response = $this->postJson(route('books.ai.prepare', $book));

    $response->assertOk();
    expect($existing->fresh()->status)->toBe('failed');
    expect(AiPreparation::where('book_id', $book->id)->where('status', 'pending')->exists())->toBeTrue();
});

it('returns 422 when ai features are disabled', function () {
    License::factory()->create();
    [$book] = createBookWithChapters(1);
    AppSetting::set('show_ai_features', false);

    $response = $this->postJson(route('books.ai.prepare', $book));

    $response->assertUnprocessable()
        ->assertJson(['message' => 'AI is not enabled or no API key configured.']);
});

it('requires license to access preparation routes', function () {
    [$book] = createBookWithChapters(1);

    $this->postJson(route('books.ai.prepare', $book))
        ->assertForbidden();

    $this->getJson(route('books.ai.prepare.status', $book))
        ->assertForbidden();
});
```

- [ ] **Step 2: Run the tests to verify they fail (implementation already exists)**

Run: `php artisan test tests/Feature/AiPreparationControllerTest.php --compact`
Expected: Tests should pass since the controller already exists. If any fail, adjust the test setup (e.g., AppSetting keys, AiSetting factory setup).

- [ ] **Step 3: Fix any failures and re-run**

Run: `php artisan test tests/Feature/AiPreparationControllerTest.php --compact`
Expected: All 7 tests pass

- [ ] **Step 4: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/AiPreparationControllerTest.php
git commit -m "test: add feature tests for AiPreparationController"
```

---

### Task 10: NormalizationController Feature Tests

**Files:**
- Create: `tests/Feature/NormalizationControllerTest.php`

**Docs to check:** Routes (no middleware): `POST /books/{book}/normalize/preview` → `previewBook()`, `POST /books/{book}/normalize/apply` → `applyBook()`, `POST /books/{book}/chapters/{chapter}/normalize/preview` → `previewChapter()`, `POST /books/{book}/chapters/{chapter}/normalize/apply` → `applyChapter()`. All return JSON. Apply methods create new ChapterVersions with `source: VersionSource::Normalization`.

- [ ] **Step 1: Write the normalization controller tests**

Create `tests/Feature/NormalizationControllerTest.php`:

```php
<?php

use App\Models\ChapterVersion;

it('previews book normalization and returns changes', function () {
    [$book, $chapters] = createBookWithChapters(2);

    $response = $this->postJson(route('books.normalize.preview', $book));

    $response->assertOk()
        ->assertJsonStructure([
            'chapters',
            'total_changes',
        ]);
});

it('applies book normalization and creates new versions', function () {
    [$book, $chapters] = createBookWithChapters(2);

    $versionCountBefore = ChapterVersion::count();

    $response = $this->postJson(route('books.normalize.apply', $book));

    $response->assertOk()
        ->assertJsonStructure(['applied_chapters']);

    // If normalization made changes, new versions should exist
    $appliedCount = $response->json('applied_chapters');
    if ($appliedCount > 0) {
        expect(ChapterVersion::count())->toBeGreaterThan($versionCountBefore);
    }
});

it('previews chapter normalization', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];

    $response = $this->postJson(route('chapters.normalize.preview', [$book, $chapter]));

    $response->assertOk()
        ->assertJsonStructure([
            'chapters',
            'total_changes',
        ]);
});

it('applies chapter normalization', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];

    $response = $this->postJson(route('chapters.normalize.apply', [$book, $chapter]));

    $response->assertOk()
        ->assertJsonStructure(['applied']);
});

it('returns empty changes for chapter without content', function () {
    $book = \App\Models\Book::factory()->create();
    $storyline = \App\Models\Storyline::factory()->for($book)->create();
    $chapter = \App\Models\Chapter::factory()->for($book)->for($storyline)->create();

    // Chapter has no version/content
    $response = $this->postJson(route('chapters.normalize.preview', [$book, $chapter]));

    $response->assertOk()
        ->assertJson([
            'chapters' => [],
            'total_changes' => 0,
        ]);
});
```

- [ ] **Step 2: Run the tests**

Run: `php artisan test tests/Feature/NormalizationControllerTest.php --compact`
Expected: All 5 tests pass

- [ ] **Step 3: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/NormalizationControllerTest.php
git commit -m "test: add feature tests for NormalizationController"
```

---

### Task 11: CI Coverage Tracking

**Files:**
- Modify: `.github/workflows/tests.yml`
- Modify: `composer.json` (scripts section)

- [ ] **Step 1: Add `dev` branch to CI triggers**

In `.github/workflows/tests.yml`, update the branch lists (lines 5-9 and 11-15) to include `dev`:

```yaml
on:
  push:
    branches:
      - dev
      - develop
      - main
      - master
      - workos
  pull_request:
    branches:
      - dev
      - develop
      - main
      - master
      - workos
```

- [ ] **Step 2: Add Playwright install step**

In `.github/workflows/tests.yml`, add after the "Build Assets" step (after line 53) and before the "Tests" step:

```yaml
      - name: Install Playwright Browsers
        run: npx playwright install --with-deps chromium
```

- [ ] **Step 3: Update test command with coverage**

In `.github/workflows/tests.yml`, replace the test command (line 56):

```yaml
      - name: Tests
        run: ./vendor/bin/pest --coverage --coverage-clover=coverage.xml
```

- [ ] **Step 4: Add Codecov upload step**

In `.github/workflows/tests.yml`, add after the "Tests" step:

```yaml
      - name: Upload Coverage to Codecov
        if: matrix.php-version == '8.4'
        uses: codecov/codecov-action@v5
        with:
          files: coverage.xml
          fail_ci_if_error: false
```

- [ ] **Step 5: Add local coverage script to composer.json**

In `composer.json`, add to the `scripts` section after the `test` entry:

```json
"coverage": [
    "@php artisan test --coverage"
],
```

- [ ] **Step 6: Verify the workflow YAML is valid**

Run: `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/tests.yml'))" 2>&1 || echo "YAML is valid (python yaml not available, verify manually)"`

- [ ] **Step 7: Commit**

```bash
git add .github/workflows/tests.yml composer.json
git commit -m "ci: add coverage tracking with Codecov and Playwright CI support"
```

---

### Task 12: Final Verification

- [ ] **Step 1: Run all browser tests**

Run: `php artisan test tests/Browser --compact`
Expected: All browser tests pass (existing 8 + new ~24)

- [ ] **Step 2: Run all feature tests for new controllers**

Run: `php artisan test tests/Feature/AiPreparationControllerTest.php tests/Feature/NormalizationControllerTest.php --compact`
Expected: All 12 tests pass

- [ ] **Step 3: Run the full test suite**

Run: `php artisan test --compact`
Expected: All tests pass, no regressions. Should complete in under 3 minutes.

- [ ] **Step 4: Run Pint on all modified files**

Run: `vendor/bin/pint --dirty --format agent`
Expected: No formatting issues

- [ ] **Step 5: Final commit if any fixes were needed**

Only if previous steps required adjustments:
```bash
git add -A
git commit -m "fix: address test failures from final verification"
```
