# Version Diff Review Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a review workflow where AI-created versions (beautify, revise) land in "pending" status with a side-by-side diff view, requiring explicit accept/reject before becoming the active version.

**Architecture:** Add a `status` column (`accepted`/`pending`) to `chapter_versions` alongside existing `is_current`. AI actions create versions with `status=pending, is_current=false`. The chapter show page detects a pending version and renders a `DiffView` component instead of the normal editor. Accept sets the pending version as current; reject deletes it.

**Tech Stack:** Laravel migration, PHP enum, `diff` npm package (jsdiff) for paragraph-level diffing, React component for side-by-side view, Tailwind v4 for styling.

---

### Task 1: Add VersionStatus Enum

**Files:**
- Create: `app/Enums/VersionStatus.php`

**Step 1: Create the enum**

```php
<?php

namespace App\Enums;

enum VersionStatus: string
{
    case Accepted = 'accepted';
    case Pending = 'pending';
}
```

**Step 2: Commit**

```
feat: add VersionStatus enum for version review workflow
```

---

### Task 2: Add `status` Column Migration

**Files:**
- Create: new migration via artisan
- Modify: `app/Models/ChapterVersion.php:20-27` (add cast)

**Step 1: Write the failing test**

Add to `tests/Feature/ChapterControllerTest.php`:

```php
test('chapter version has status column defaulting to accepted', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $version = ChapterVersion::factory()->for($chapter)->create(['is_current' => true]);

    expect($version->fresh()->status->value)->toBe('accepted');
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="chapter version has status column"`
Expected: FAIL — `status` property doesn't exist.

**Step 3: Create migration**

Run: `php artisan make:migration add_status_to_chapter_versions_table --no-interaction`

Migration content:

```php
public function up(): void
{
    Schema::table('chapter_versions', function (Blueprint $table) {
        $table->string('status')->default('accepted')->after('is_current');
    });
}

public function down(): void
{
    Schema::table('chapter_versions', function (Blueprint $table) {
        $table->dropColumn('status');
    });
}
```

**Step 4: Add cast to ChapterVersion model**

In `app/Models/ChapterVersion.php`, add `VersionStatus` import and update `casts()`:

```php
use App\Enums\VersionStatus;

// In casts():
'status' => VersionStatus::class,
```

**Step 5: Run migration**

```bash
php artisan migrate --no-interaction
DB_DATABASE=database/nativephp.sqlite php artisan migrate --no-interaction
```

**Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter="chapter version has status column"`
Expected: PASS

**Step 7: Run all existing tests to ensure no regressions**

Run: `php artisan test --compact tests/Feature/ChapterControllerTest.php`
Expected: All tests PASS

**Step 8: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

**Step 9: Commit**

```
feat: add status column to chapter_versions table
```

---

### Task 3: Modify AI Controller to Create Pending Versions

**Files:**
- Modify: `app/Http/Controllers/AiController.php:92-137`
- Test: `tests/Feature/ChapterControllerTest.php`

**Step 1: Write the failing test**

Add to `tests/Feature/ChapterControllerTest.php`:

```php
test('streamAgentRevision creates pending version without changing current', function () {
    // We test indirectly via the version list endpoint since streaming
    // requires AI provider setup. Instead, test the accept/reject endpoints.
});
```

Actually, since `streamAgentRevision` is private and requires AI provider setup, we'll test the behavior through the accept/reject endpoints in Task 4. The AI controller change is straightforward:

**Step 2: Modify `streamAgentRevision` in `AiController.php`**

Replace the `.then()` callback (lines 112-136). The key changes:
1. Do NOT set `$currentVersion->is_current` to false
2. Set new version `is_current` to false, `status` to `pending`
3. Do NOT delete/recreate scenes (that happens on accept)

```php
->then(function ($response) use ($chapter, $currentVersion, $source, $changeSummary) {
    $nextNumber = ($currentVersion?->version_number ?? 0) + 1;

    $chapter->versions()->create([
        'version_number' => $nextNumber,
        'content' => $response->text,
        'source' => $source,
        'change_summary' => $changeSummary,
        'is_current' => false,
        'status' => VersionStatus::Pending,
    ]);
});
```

Add `use App\Enums\VersionStatus;` import at top.

**Step 3: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

**Step 4: Run existing tests**

Run: `php artisan test --compact tests/Feature/ChapterControllerTest.php`
Expected: All PASS

**Step 5: Commit**

```
feat: AI actions create pending versions instead of immediately replacing current
```

---

### Task 4: Add Accept/Reject Endpoints

**Files:**
- Modify: `app/Http/Controllers/ChapterController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/ChapterControllerTest.php`

**Step 1: Write the failing tests**

Add to `tests/Feature/ChapterControllerTest.php`:

```php
use App\Enums\VersionStatus;

test('acceptVersion promotes pending version to current', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 10]);

    $current = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => '<p>Original text</p>',
        'status' => VersionStatus::Accepted,
    ]);

    Scene::factory()->for($chapter)->create(['content' => '<p>Original text</p>', 'sort_order' => 0]);

    $pending = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => false,
        'version_number' => 2,
        'content' => '<p>Beautified text here</p>',
        'source' => 'beautify',
        'status' => VersionStatus::Pending,
    ]);

    $this->postJson(route('chapters.acceptVersion', [$book, $chapter, $pending]))
        ->assertOk();

    expect($current->fresh()->is_current)->toBeFalse();
    expect($pending->fresh()->is_current)->toBeTrue();
    expect($pending->fresh()->status->value)->toBe('accepted');

    // Scenes replaced with pending version content
    $chapter->refresh();
    expect($chapter->scenes)->toHaveCount(1);
    expect($chapter->scenes->first()->content)->toBe('<p>Beautified text here</p>');
    expect($chapter->word_count)->toBe(3);
});

test('acceptVersion rejects non-pending version', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $version = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'status' => VersionStatus::Accepted,
    ]);

    $this->postJson(route('chapters.acceptVersion', [$book, $chapter, $version]))
        ->assertForbidden();
});

test('rejectVersion deletes pending version', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $current = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'status' => VersionStatus::Accepted,
    ]);

    $pending = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => false,
        'version_number' => 2,
        'status' => VersionStatus::Pending,
    ]);

    $this->postJson(route('chapters.rejectVersion', [$book, $chapter, $pending]))
        ->assertOk();

    expect(ChapterVersion::find($pending->id))->toBeNull();
    expect($current->fresh()->is_current)->toBeTrue();
});

test('rejectVersion rejects non-pending version', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $version = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'status' => VersionStatus::Accepted,
    ]);

    $this->postJson(route('chapters.rejectVersion', [$book, $chapter, $version]))
        ->assertForbidden();
});
```

**Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter="acceptVersion|rejectVersion"`
Expected: FAIL — routes don't exist.

**Step 3: Add routes**

In `routes/web.php`, add after the `destroyVersion` route:

```php
Route::post('/books/{book}/chapters/{chapter}/versions/{version}/accept', [ChapterController::class, 'acceptVersion'])->name('chapters.acceptVersion');
Route::post('/books/{book}/chapters/{chapter}/versions/{version}/reject', [ChapterController::class, 'rejectVersion'])->name('chapters.rejectVersion');
```

**Step 4: Add controller methods**

In `app/Http/Controllers/ChapterController.php`:

```php
use App\Enums\VersionStatus;

public function acceptVersion(Book $book, Chapter $chapter, ChapterVersion $version): JsonResponse
{
    abort_if($version->status !== VersionStatus::Pending, 403, 'Only pending versions can be accepted.');

    DB::transaction(function () use ($chapter, $version) {
        $chapter->versions()->where('is_current', true)->update(['is_current' => false]);

        $version->update([
            'is_current' => true,
            'status' => VersionStatus::Accepted,
        ]);

        $chapter->scenes()->forceDelete();
        $wordCount = str_word_count(strip_tags($version->content ?? ''));
        $chapter->scenes()->create([
            'title' => 'Scene 1',
            'content' => $version->content,
            'sort_order' => 0,
            'word_count' => $wordCount,
        ]);
        $chapter->update(['word_count' => $wordCount]);
    });

    return response()->json(['success' => true]);
}

public function rejectVersion(Book $book, Chapter $chapter, ChapterVersion $version): JsonResponse
{
    abort_if($version->status !== VersionStatus::Pending, 403, 'Only pending versions can be rejected.');

    $version->delete();

    return response()->json(['success' => true]);
}
```

**Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter="acceptVersion|rejectVersion"`
Expected: All 4 PASS

**Step 6: Run all chapter tests**

Run: `php artisan test --compact tests/Feature/ChapterControllerTest.php`
Expected: All PASS

**Step 7: Run NativePHP migration**

```bash
DB_DATABASE=database/nativephp.sqlite php artisan migrate --no-interaction
```

**Step 8: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
```

```
feat: add accept/reject endpoints for pending versions
```

---

### Task 5: Pass Pending Version to Frontend

**Files:**
- Modify: `app/Http/Controllers/ChapterController.php:79-104` (show method)
- Modify: `app/Models/Chapter.php` (add pendingVersion relationship)
- Test: `tests/Feature/ChapterControllerTest.php`

**Step 1: Write the failing test**

```php
test('show includes pending version when one exists', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'status' => VersionStatus::Accepted,
    ]);
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => false,
        'version_number' => 2,
        'status' => VersionStatus::Pending,
        'content' => 'Pending content',
        'source' => 'beautify',
        'change_summary' => 'AI text beautification',
    ]);

    $this->get(route('chapters.show', [$book, $chapter]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('chapters/show')
            ->has('pendingVersion')
            ->where('pendingVersion.version_number', 2)
            ->where('pendingVersion.content', 'Pending content')
        );
});

test('show does not include pending version when none exists', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'status' => VersionStatus::Accepted,
    ]);

    $this->get(route('chapters.show', [$book, $chapter]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('chapters/show')
            ->where('pendingVersion', null)
        );
});
```

**Step 2: Run tests to verify they fail**

**Step 3: Add `pendingVersion` relationship to Chapter model**

In `app/Models/Chapter.php`:

```php
use App\Enums\VersionStatus;

public function pendingVersion(): HasOne
{
    return $this->hasOne(ChapterVersion::class)->where('status', VersionStatus::Pending);
}
```

**Step 4: Update `show` method in ChapterController**

Add `pendingVersion` to the chapter load and pass it as a prop:

```php
$chapter->load([
    'currentVersion:id,chapter_id,version_number,content,source,is_current',
    'pendingVersion:id,chapter_id,version_number,content,source,change_summary,status',
    'scenes' => fn ($q) => $q->orderBy('sort_order'),
    'storyline:id,name,timeline_label',
    'povCharacter:id,name',
    'characters' => fn ($q) => $q->select('characters.id', 'characters.name'),
]);

return Inertia::render('chapters/show', [
    'book' => $book,
    'chapter' => $chapter,
    'versionCount' => $chapter->versions()->count(),
    'pendingVersion' => $chapter->pendingVersion,
]);
```

**Step 5: Run tests**

Expected: All PASS

**Step 6: Run Pint and commit**

```
feat: pass pending version data to chapter show page
```

---

### Task 6: Update TypeScript Types

**Files:**
- Modify: `resources/js/types/models.ts:3` (add VersionStatus type)
- Modify: `resources/js/types/models.ts:137-148` (add status to ChapterVersion)

**Step 1: Add VersionStatus type**

After `VersionSource` type line:

```typescript
export type VersionStatus = 'accepted' | 'pending';
```

**Step 2: Add `status` to ChapterVersion type**

```typescript
export type ChapterVersion = {
    id: number;
    chapter_id: number;
    version_number: number;
    content: string | null;
    source: VersionSource;
    change_summary: string | null;
    is_current: boolean;
    status: VersionStatus;
    created_at: string;
    updated_at: string;
    chapter?: Chapter;
};
```

**Step 3: Add `pending_version` to Chapter type**

In the Chapter type, add:

```typescript
pending_version?: ChapterVersion;
```

**Step 4: Commit**

```
feat: add VersionStatus type and pending_version to TypeScript models
```

---

### Task 7: Install `diff` NPM Package

**Step 1: Install**

```bash
npm install diff
npm install -D @types/diff
```

**Step 2: Commit**

```
chore: add diff (jsdiff) package for paragraph-level diffing
```

---

### Task 8: Build DiffView Component

**Files:**
- Create: `resources/js/components/editor/DiffView.tsx`

**Step 1: Create the component**

This is the main visual component matching the Paper design. It renders:
- Top bar with review status and Accept/Reject buttons
- Two scrollable columns: ORIGINAL (left) and REVISION (right)
- Paragraph-level diff: deleted text with red bg + strikethrough, added text with green bg

```tsx
import { acceptVersion, rejectVersion } from '@/actions/App/Http/Controllers/ChapterController';
import { getXsrfToken } from '@/lib/csrf';
import type { ChapterVersion } from '@/types/models';
import { router } from '@inertiajs/react';
import { diffWords } from 'diff';
import { useCallback, useMemo, useState } from 'react';

const sourceLabel: Record<string, string> = {
    original: 'original',
    ai_revision: 'ai prose pass',
    manual_edit: 'manual edit',
    normalization: 'normalize',
    beautify: 'beautify',
    snapshot: 'snapshot',
};

function splitParagraphs(html: string | null): string[] {
    if (!html) return [];
    // Split on paragraph tags or double newlines
    return html
        .replace(/<\/p>\s*<p>/gi, '</p>\n<p>')
        .split(/\n/)
        .map((p) => p.trim())
        .filter(Boolean);
}

function stripTags(html: string): string {
    return html.replace(/<[^>]*>/g, '');
}

type DiffSegment = { text: string; type: 'equal' | 'added' | 'removed' };

function diffParagraphs(
    original: string[],
    revised: string[],
): { left: { html: string; segments: DiffSegment[] }[]; right: { html: string; segments: DiffSegment[] }[]; changeCount: number } {
    // Use diffWords on the full text to identify changes, then map back to paragraphs
    const origText = original.map(stripTags).join('\n\n');
    const revText = revised.map(stripTags).join('\n\n');

    const wordDiffs = diffWords(origText, revText);

    // Build left (original) and right (revised) segments
    const leftSegments: DiffSegment[] = [];
    const rightSegments: DiffSegment[] = [];
    let changeCount = 0;

    for (const part of wordDiffs) {
        if (part.added) {
            rightSegments.push({ text: part.value, type: 'added' });
            changeCount++;
        } else if (part.removed) {
            leftSegments.push({ text: part.value, type: 'removed' });
        } else {
            leftSegments.push({ text: part.value, type: 'equal' });
            rightSegments.push({ text: part.value, type: 'equal' });
        }
    }

    // Re-split segments into paragraphs at double-newlines
    function splitSegmentsIntoParagraphs(segments: DiffSegment[]): { html: string; segments: DiffSegment[] }[] {
        const paragraphs: { html: string; segments: DiffSegment[] }[] = [];
        let current: DiffSegment[] = [];

        for (const seg of segments) {
            const parts = seg.text.split('\n\n');
            for (let i = 0; i < parts.length; i++) {
                if (i > 0) {
                    paragraphs.push({ html: '', segments: current });
                    current = [];
                }
                if (parts[i]) {
                    current.push({ text: parts[i], type: seg.type });
                }
            }
        }
        if (current.length > 0) {
            paragraphs.push({ html: '', segments: current });
        }

        return paragraphs;
    }

    return {
        left: splitSegmentsIntoParagraphs(leftSegments),
        right: splitSegmentsIntoParagraphs(rightSegments),
        changeCount,
    };
}

export default function DiffView({
    bookId,
    chapterId,
    chapterTitle,
    currentVersion,
    pendingVersion,
}: {
    bookId: number;
    chapterId: number;
    chapterTitle: string;
    currentVersion: ChapterVersion;
    pendingVersion: ChapterVersion;
}) {
    const [isAccepting, setIsAccepting] = useState(false);
    const [isRejecting, setIsRejecting] = useState(false);

    const diff = useMemo(() => {
        const origParagraphs = splitParagraphs(currentVersion.content);
        const revParagraphs = splitParagraphs(pendingVersion.content);
        return diffParagraphs(origParagraphs, revParagraphs);
    }, [currentVersion.content, pendingVersion.content]);

    const handleAccept = useCallback(async () => {
        setIsAccepting(true);
        try {
            const response = await fetch(
                acceptVersion.url({ book: bookId, chapter: chapterId, version: pendingVersion.id }),
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                    },
                },
            );
            if (!response.ok) throw new Error('Accept failed');
            router.reload();
        } catch {
            setIsAccepting(false);
        }
    }, [bookId, chapterId, pendingVersion.id]);

    const handleReject = useCallback(async () => {
        setIsRejecting(true);
        try {
            const response = await fetch(
                rejectVersion.url({ book: bookId, chapter: chapterId, version: pendingVersion.id }),
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                    },
                },
            );
            if (!response.ok) throw new Error('Reject failed');
            router.reload();
        } catch {
            setIsRejecting(false);
        }
    }, [bookId, chapterId, pendingVersion.id]);

    const maxLen = Math.max(diff.left.length, diff.right.length);

    return (
        <div className="flex flex-1 flex-col overflow-hidden">
            {/* Review bar */}
            <div className="flex h-12 shrink-0 items-center justify-between border-b border-border px-6">
                <div className="flex items-center gap-2 text-sm">
                    <span className="text-ink-faint">{chapterTitle}</span>
                    <span className="text-ink-faint">/</span>
                    <span className="font-medium text-accent">
                        Reviewing {sourceLabel[pendingVersion.source] ?? pendingVersion.source}
                    </span>
                    <span className="text-ink-faint">
                        {diff.changeCount} {diff.changeCount === 1 ? 'change' : 'changes'}
                    </span>
                </div>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={handleReject}
                        disabled={isRejecting || isAccepting}
                        className="rounded-md border border-border px-3 py-1.5 text-xs font-medium text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink disabled:opacity-50"
                    >
                        {isRejecting ? 'Rejecting...' : 'Reject all'}
                    </button>
                    <button
                        type="button"
                        onClick={handleAccept}
                        disabled={isAccepting || isRejecting}
                        className="rounded-md bg-ink px-3 py-1.5 text-xs font-medium text-surface transition-colors hover:bg-ink/90 disabled:opacity-50"
                    >
                        {isAccepting ? 'Accepting...' : 'Accept revision'}
                    </button>
                </div>
            </div>

            {/* Side-by-side diff */}
            <div className="flex flex-1 overflow-hidden">
                {/* Left: Original */}
                <div className="flex-1 overflow-y-auto border-r border-border px-12 py-8">
                    <div className="mb-6 flex items-center gap-2">
                        <span className="text-xs font-semibold uppercase tracking-wider text-ink-faint">Original</span>
                        <span className="text-xs text-ink-faint">
                            v{currentVersion.version_number} &middot; {sourceLabel[currentVersion.source] ?? currentVersion.source}
                        </span>
                    </div>
                    <div className="max-w-prose space-y-4 font-serif text-base leading-relaxed text-ink">
                        {diff.left.map((para, i) => (
                            <p key={i}>
                                {para.segments.map((seg, j) =>
                                    seg.type === 'removed' ? (
                                        <span key={j} className="bg-delete-bg text-delete line-through">
                                            {seg.text}
                                        </span>
                                    ) : (
                                        <span key={j}>{seg.text}</span>
                                    ),
                                )}
                            </p>
                        ))}
                    </div>
                </div>

                {/* Right: Revision */}
                <div className="flex-1 overflow-y-auto px-12 py-8">
                    <div className="mb-6 flex items-center gap-2">
                        <span className="text-xs font-semibold uppercase tracking-wider text-ink-faint">Revision</span>
                        <span className="text-xs text-ink-faint">
                            v{pendingVersion.version_number} &middot; {sourceLabel[pendingVersion.source] ?? pendingVersion.source}
                        </span>
                    </div>
                    <div className="max-w-prose space-y-4 font-serif text-base leading-relaxed text-ink">
                        {diff.right.map((para, i) => (
                            <p key={i}>
                                {para.segments.map((seg, j) =>
                                    seg.type === 'added' ? (
                                        <span key={j} className="bg-ai-green/15 text-ai-green">
                                            {seg.text}
                                        </span>
                                    ) : (
                                        <span key={j}>{seg.text}</span>
                                    ),
                                )}
                            </p>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
```

**Step 2: Commit**

```
feat: add DiffView component for side-by-side version comparison
```

---

### Task 9: Integrate DiffView into Chapter Show Page

**Files:**
- Modify: `resources/js/pages/chapters/show.tsx`

**Step 1: Update page props and conditional rendering**

Add `pendingVersion` to the props interface and import DiffView:

```typescript
import DiffView from '@/components/editor/DiffView';
import type { Book, Chapter, ChapterVersion, Character, CharacterChapterPivot, Scene } from '@/types/models';
```

Update the component signature:

```typescript
export default function ChapterShow({
    book,
    chapter,
    versionCount,
    pendingVersion,
}: {
    book: Book;
    chapter: ChapterWithRelations;
    versionCount: number;
    pendingVersion: ChapterVersion | null;
}) {
```

In the JSX, replace the main content area. Inside the second child `<div>` (the `relative flex min-w-0 flex-1 flex-col` div), wrap the editor content in a conditional:

```tsx
{pendingVersion && chapter.current_version ? (
    <DiffView
        bookId={book.id}
        chapterId={chapter.id}
        chapterTitle={displayTitle}
        currentVersion={chapter.current_version}
        pendingVersion={pendingVersion}
    />
) : (
    <>
        {/* existing FormattingToolbar + WritingSurface + NotesPanel */}
    </>
)}
```

When `pendingVersion` exists:
- The EditorBar still shows (breadcrumb context)
- FormattingToolbar, WritingSurface, NotesPanel, and AiPanel are hidden
- DiffView takes over the content area

**Step 2: Build and verify**

```bash
npm run build
```

**Step 3: Commit**

```
feat: show diff view when chapter has pending AI revision
```

---

### Task 10: Update Frontend After AI Streaming Completes

**Files:**
- Modify: `resources/js/pages/chapters/show.tsx:282-302` (handleBeautify)
- Modify: `resources/js/components/editor/AiPanel.tsx` (handleRunProse)

**Step 1: Update handleBeautify**

The existing `router.reload()` after streaming already works perfectly — once the AI streams, the backend creates a pending version, `router.reload()` fetches fresh props including `pendingVersion`, and the page automatically shows the DiffView.

No code change needed for handleBeautify — it already calls `router.reload()` which will now pick up the pending version.

**Step 2: Update handleRunProse in AiPanel**

Same situation — `router.reload()` already handles it. No change needed.

**Step 3: Verify by building**

```bash
npm run build
```

**Step 4: Commit (skip if no changes)**

---

### Task 11: Generate Wayfinder Routes

**Step 1: Generate routes**

After adding new backend routes (acceptVersion, rejectVersion), regenerate Wayfinder:

```bash
php artisan wayfinder:generate --no-interaction
```

**Step 2: Verify the generated functions exist**

Check that `acceptVersion` and `rejectVersion` are available in `resources/js/actions/App/Http/Controllers/ChapterController.ts`.

**Step 3: Commit if generated files changed**

```
chore: regenerate Wayfinder routes for accept/reject endpoints
```

---

### Task 12: Update Version List Endpoint to Include Status

**Files:**
- Modify: `app/Http/Controllers/ChapterController.php:162-170` (versions method)
- Modify: `resources/js/components/editor/VersionHistoryOverlay.tsx`

**Step 1: Add `status` to versions select**

In `ChapterController::versions()`, add `status` to the select:

```php
->select('id', 'chapter_id', 'version_number', 'source', 'change_summary', 'is_current', 'status', 'created_at')
```

**Step 2: Show pending badge in VersionHistoryOverlay**

In `VersionHistoryOverlay.tsx`, after the "Current" label (around line 229-231), add:

```tsx
{version.status === 'pending' && (
    <span className="text-[10px] font-medium text-accent">Pending review</span>
)}
```

**Step 3: Remove the disabled Compare button**

Replace the disabled Compare button (lines 253-260) since we now have the diff view built into the main page flow. The button is no longer needed for the "Phase 2" placeholder.

**Step 4: Build and commit**

```bash
npm run build
```

```
feat: show pending status in version history overlay
```

---

### Task 13: Final Integration Test

**Step 1: Run all backend tests**

```bash
php artisan test --compact
```

Expected: All PASS

**Step 2: Build frontend**

```bash
npm run build
```

Expected: No TypeScript/build errors

**Step 3: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

**Step 4: Manual verification**

1. Open a chapter in the app
2. Create a manual snapshot → should work as before, no diff view
3. Run beautify on a chapter → should see loading, then diff view appears
4. Verify diff view shows ORIGINAL vs REVISION side-by-side
5. Click "Reject all" → back to normal editor, pending version deleted
6. Run beautify again → diff view appears
7. Click "Accept revision" → editor shows updated content

**Step 5: Final commit if needed**

```
feat: complete version diff review workflow
```
