# Sidebar Collapsible Story/Publish Groups Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Group the book sidebar's five tool items into two collapsible groups — "Story" (Wiki, Plot, AI; default open) and "Publish" (Typesetting, Export; default collapsed) — so the chapter list gets more vertical room.

**Architecture:** A new `NavGroup` UI component wraps the existing Radix `Collapsible` (already re-exported from `resources/js/components/ui/Collapsible.tsx`) with a NavItem-styled trigger row, localStorage persistence, and auto-expand when the active route is inside the group. `Sidebar.tsx` renders the five items inside two `NavGroup`s in expanded mode and flat (exactly as today) in the 48px icon-only mode. No backend changes.

**Tech Stack:** React 19, Inertia v2, Radix `@radix-ui/react-collapsible`, Tailwind v4 design tokens, react-i18next, Pest 4 browser tests.

**Spec:** `docs/superpowers/specs/2026-07-09-sidebar-collapsible-groups-design.md`

## Global Constraints

- Design system: no hardcoded hex, icon sizes from the 5-step scale (`size={14}`/`size={16}` here), radius `rounded-md` for nav rows, text colors `text-ink-muted` / `text-ink-faint` by hierarchy.
- Group header rows match sibling `NavItem` styling exactly: `gap-2.5 px-2.5 py-[7px] text-[13px]`, hover `hover:bg-neutral-bg hover:text-ink`. (The spec says "text-xs"; the actual `NavItem` rows are `text-[13px]` — match the real siblings.)
- Reuse `resources/js/components/ui/Collapsible.tsx` and `NavItem.tsx`; do not install anything new.
- localStorage keys follow the existing `manuscript:` prefix convention (see `manuscript:sidebar-width` in `useResizablePanel`).
- Auto-expand must NOT write to localStorage; only a manual toggle persists.
- Icon-only (48px) sidebar mode renders all five icons flat, exactly as today.
- Browser tests: extend `tests/Browser/ChapterEditorTest.php` (browser-test-per-feature guardrail — do NOT create a new browser test file). Browser tests need `npm run build` first; if a Vite dev server is running, move `public/hot` aside (NEVER delete it) and restore after.
- New i18n keys are flat strings (`"nav.story"`, `"nav.publish"`) in `resources/js/i18n/{en,de,es}/common.json`.
- Frontend format/lint: `npm run format` + `npm run lint:check` + `npm run types:check`. PHP: `vendor/bin/pint --dirty --format agent`.
- Current branch is `dev`; work directly on it. Never switch branches.

---

### Task 1: Failing browser tests for the collapsible nav groups

**Files:**
- Modify: `tests/Browser/ChapterEditorTest.php` (append at end of file)

**Interfaces:**
- Consumes: existing `createBookWithChapters(int $count)` Pest helper (already used throughout this file); Pest browser API `visit()`, `->click(selector)`, `->assertPresent()`, `->assertNotPresent()`, `->refresh()` (confirmed to exist in `vendor/pestphp/pest-plugin-browser/src/Api/Concerns/InteractsWithToolbar.php:18`).
- Produces: four tests that pin the selectors Task 2 must implement:
  - `[data-testid='nav-group-story']` — Story group trigger button
  - `[data-testid='nav-group-publish']` — Publish group trigger button
  - `[data-testid='nav-group-story-content']` — Story group content wrapper (only in DOM when the group is open; Radix Collapsible unmounts closed content)
  - `[data-testid='nav-group-publish-content']` — Publish group content wrapper

- [ ] **Step 1: Build assets so browser tests run against current code**

Run: `npm run build`
Expected: Vite build completes without errors. (If a dev server is running, first `mv public/hot public/hot.bak` and restore it after the final test run in Task 3.)

- [ ] **Step 2: Append the four failing tests**

Add to the end of `tests/Browser/ChapterEditorTest.php`:

```php
it('collapses the publish nav group by default and keeps the story group open', function () {
    [$book, $chapters] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/chapters/{$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->assertPresent("[data-testid='nav-group-story-content']")
        ->assertNotPresent("[data-testid='nav-group-publish-content']");
});

it('expands the publish nav group on click revealing typesetting and export', function () {
    [$book, $chapters] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/chapters/{$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->click("[data-testid='nav-group-publish']")
        ->assertPresent("[data-testid='nav-group-publish-content']")
        ->assertSee('Typesetting')
        ->assertSee('Export');
});

it('auto-expands the publish nav group when landing on a route inside it', function () {
    [$book, $chapters] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/design");

    $page->assertNoJavaScriptErrors()
        ->assertPresent("[data-testid='nav-group-publish-content']");
});

it('persists a manually toggled nav group state across a reload', function () {
    [$book, $chapters] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/chapters/{$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->click("[data-testid='nav-group-publish']")
        ->assertPresent("[data-testid='nav-group-publish-content']")
        ->refresh()
        ->assertPresent("[data-testid='nav-group-publish-content']");
});
```

- [ ] **Step 3: Run the new tests to verify they fail**

Run: `php artisan test --compact tests/Browser/ChapterEditorTest.php --filter="nav group"`
Expected: 4 FAILED — each failing on a missing `[data-testid='nav-group-...']` element (the sidebar has no groups yet). `assertNoJavaScriptErrors` must NOT be the failure reason.

- [ ] **Step 4: Run pint on the modified test file**

Run: `vendor/bin/pint --dirty --format agent`
Expected: 0 or more style fixes applied, no errors.

- [ ] **Step 5: Commit the red tests**

```bash
git add tests/Browser/ChapterEditorTest.php
git commit -m "test: sidebar Story/Publish nav groups must collapse, auto-expand, and persist

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 2: NavGroup component, Sidebar integration, i18n keys

**Files:**
- Create: `resources/js/components/ui/NavGroup.tsx`
- Modify: `resources/js/components/editor/Sidebar.tsx:246-330` (the five grouped NavItems; Dashboard and Book Settings rows stay untouched)
- Modify: `resources/js/i18n/en/common.json`, `resources/js/i18n/de/common.json`, `resources/js/i18n/es/common.json` (two new keys each, next to the existing `"nav.book"` key at line ~28)

**Interfaces:**
- Consumes: `Collapsible`, `CollapsibleTrigger`, `CollapsibleContent` from `@/components/ui/Collapsible`; `NavItem` from `@/components/ui/NavItem` (props: `label`, `icon`, `href`, `isActive`, `iconOnly`); `ChevronRight` from `lucide-react`.
- Produces: `NavGroup` default export with props `{ label: string; storageKey: string; defaultOpen?: boolean; containsActive?: boolean; testId: string; children: React.ReactNode }`. Renders `data-testid={testId}` on the trigger and `data-testid={`${testId}-content`}` on the content wrapper — these are the selectors Task 1's tests assert on.

- [ ] **Step 1: Create the NavGroup component**

Create `resources/js/components/ui/NavGroup.tsx`:

```tsx
import { ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/Collapsible';
import { cn } from '@/lib/utils';

export default function NavGroup({
    label,
    storageKey,
    defaultOpen = false,
    containsActive = false,
    testId,
    children,
}: {
    label: string;
    storageKey: string;
    defaultOpen?: boolean;
    containsActive?: boolean;
    testId: string;
    children: React.ReactNode;
}) {
    const [open, setOpen] = useState<boolean>(() => {
        const stored = localStorage.getItem(storageKey);
        if (stored !== null) {
            return stored === 'true';
        }
        return defaultOpen;
    });

    useEffect(() => {
        if (containsActive) {
            setOpen(true);
        }
    }, [containsActive]);

    const handleOpenChange = (next: boolean) => {
        setOpen(next);
        localStorage.setItem(storageKey, String(next));
    };

    return (
        <Collapsible open={open} onOpenChange={handleOpenChange}>
            <CollapsibleTrigger
                data-testid={testId}
                className="flex w-full items-center gap-2.5 rounded-md px-2.5 py-[7px] text-[13px] text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink"
            >
                <ChevronRight
                    size={14}
                    className={cn(
                        'shrink-0 text-ink-faint transition-transform',
                        open && 'rotate-90',
                    )}
                />
                {label}
            </CollapsibleTrigger>
            <CollapsibleContent
                data-testid={`${testId}-content`}
                className="flex flex-col gap-0.5 pl-4"
            >
                {children}
            </CollapsibleContent>
        </Collapsible>
    );
}
```

Behavior notes locked in by the spec:
- Initial state: stored preference wins; otherwise `defaultOpen`.
- The `useEffect` auto-expands when the active route enters the group (fires on mount and on Inertia navigation because `containsActive` flips). It sets React state only — it never writes localStorage.
- A manual toggle while on an active-route page still works: the effect only re-fires when `containsActive` *changes*, so it won't fight the user's click.

- [ ] **Step 2: Add the i18n keys**

In `resources/js/i18n/en/common.json`, directly after the `"nav.book"` line:

```json
    "nav.story": "Story",
    "nav.publish": "Publish",
```

In `resources/js/i18n/de/common.json`, directly after the `"nav.book"` line:

```json
    "nav.story": "Story",
    "nav.publish": "Veröffentlichen",
```

In `resources/js/i18n/es/common.json`, directly after the `"nav.book"` line:

```json
    "nav.story": "Historia",
    "nav.publish": "Publicar",
```

(Keys in these files are flat dotted strings, not nested objects — match the existing `"nav.dashboard"` style. Mind trailing-comma correctness in JSON.)

- [ ] **Step 3: Restructure the Book nav in Sidebar.tsx**

In `resources/js/components/editor/Sidebar.tsx`:

3a. Add imports:

```tsx
import NavGroup from '@/components/ui/NavGroup';
```

3b. After the `isBookSettings` declaration (line ~116), define the grouped items and a render helper:

```tsx
    const storyItems = [
        {
            label: t('nav.wiki'),
            href: indexWiki.url(book),
            isActive: isWiki,
            Icon: BookOpen,
        },
        {
            label: t('nav.plot'),
            href: indexPlot.url(book),
            isActive: isPlot,
            Icon: Waypoints,
        },
        {
            label: t('nav.ai'),
            href: editorialReviewIndex.url(book),
            isActive: isAi,
            Icon: Sparkles,
        },
    ];
    const publishItems = [
        {
            label: t('nav.design'),
            href: showDesign.url(book),
            isActive: isDesign,
            Icon: BookType,
        },
        {
            label: t('nav.export'),
            href: exportMethod.url(book),
            isActive: isExport,
            Icon: ArrowUpFromLine,
        },
    ];

    const renderNavItems = (items: typeof storyItems) =>
        items.map(({ label, href, isActive, Icon }) => (
            <NavItem
                key={label}
                label={label}
                href={href}
                isActive={isActive}
                iconOnly={isCollapsed}
                icon={<Icon size={16} className="shrink-0 text-ink-faint" />}
            />
        ));
```

3c. Replace the five grouped `<NavItem>` blocks (Wiki, Plot, AI, Design, Export — currently `Sidebar.tsx:270-329`) with:

```tsx
                    {isCollapsed ? (
                        <>
                            {renderNavItems(storyItems)}
                            {renderNavItems(publishItems)}
                        </>
                    ) : (
                        <>
                            <NavGroup
                                label={t('nav.story')}
                                storageKey="manuscript:nav-group-story"
                                defaultOpen
                                containsActive={isWiki || isPlot || isAi}
                                testId="nav-group-story"
                            >
                                {renderNavItems(storyItems)}
                            </NavGroup>
                            <NavGroup
                                label={t('nav.publish')}
                                storageKey="manuscript:nav-group-publish"
                                containsActive={isDesign || isExport}
                                testId="nav-group-publish"
                            >
                                {renderNavItems(publishItems)}
                            </NavGroup>
                        </>
                    )}
```

The Dashboard and Book Settings `<NavItem>`s above this block stay exactly as they are. The five lucide icon imports (`BookOpen`, `Waypoints`, `Sparkles`, `BookType`, `ArrowUpFromLine`) are still used (inside the item arrays) — do not remove them.

- [ ] **Step 4: Rebuild and run the new tests to verify they pass**

Run: `npm run build && php artisan test --compact tests/Browser/ChapterEditorTest.php --filter="nav group"`
Expected: 4 PASSED.

- [ ] **Step 5: Format and lint the frontend changes**

Run: `npm run format && npm run lint:check && npm run types:check`
Expected: prettier rewrites at most the touched files; eslint and tsc report zero errors.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/ui/NavGroup.tsx resources/js/components/editor/Sidebar.tsx resources/js/i18n/en/common.json resources/js/i18n/de/common.json resources/js/i18n/es/common.json
git commit -m "feat(sidebar): collapsible Story and Publish nav groups

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 3: Full-feature regression run and visual sanity check

**Files:**
- No new changes expected; this task verifies the whole feature.

**Interfaces:**
- Consumes: everything from Tasks 1–2.
- Produces: green `ChapterEditorTest.php` suite; confirmation that the icon-only rail and other Sidebar-hosting pages (Wiki, Plot, Dashboard, Design, Export, Book Settings) still render.

- [ ] **Step 1: Run the full ChapterEditorTest browser file**

Run: `php artisan test --compact tests/Browser/ChapterEditorTest.php`
Expected: ALL tests pass — the pre-existing sidebar tests (chapter rename, word count, status bubbles, etc.) must not regress.

- [ ] **Step 2: Run the sibling browser tests for pages that host the Sidebar**

Run: `php artisan test --compact tests/Browser/WikiTest.php tests/Browser/DashboardTest.php tests/Browser/BookDesignerTest.php tests/Browser/ExportTest.php`
Expected: ALL pass. These pages render the same `Sidebar` component; the auto-expand `containsActive` wiring must not break them (a JS error here would surface via their `assertNoJavaScriptErrors` calls).

- [ ] **Step 3: Run the guardrails + unit suite**

Run: `php artisan test --compact tests/Unit`
Expected: PASS (no controllers/auth touched, but cheap to confirm).

- [ ] **Step 4: Restore `public/hot` if it was moved aside in Task 1**

Run: `[ -f public/hot.bak ] && mv public/hot.bak public/hot || echo "nothing to restore"`
Expected: hot file restored or no-op.

- [ ] **Step 5: Verify no uncommitted drift and finish**

Run: `git status -s -- resources/ tests/ && git log --oneline -3`
Expected: clean status for the feature paths (prettier reformats from Task 2 Step 5, if any, were committed); the two feature commits on top.

If anything was reformatted after the Task 2 commit, amend or add a `style:` commit before declaring done.
