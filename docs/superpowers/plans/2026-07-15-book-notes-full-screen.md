# Book Notes Full-Screen Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the book-wide Notes & Research surface fill the viewport beside the persistent book sidebar while retaining all existing note behavior and shared UI controls.

**Architecture:** Keep the existing root application shell and sidebar, then make the notes page's main region a non-scrolling flex workspace containing `NotesPanel` directly. The notes component remains the sole owner of editing, autosave, errors, slash commands, and its shared `PanelHeader`; only the page composition changes.

**Tech Stack:** Laravel 13, Inertia.js v2, React 19, TypeScript, Tailwind CSS v4, Pest 4 browser testing, customized shadcn components in `resources/js/components/ui/`.

## Global Constraints

- Preserve the book sidebar and make the notes surface fill all remaining viewport width and height.
- Continue using `@/components/ui/Button` for every notes action; never introduce raw `<button>` markup or a second button implementation.
- Reuse `resources/js/components/ui/` first; evaluate a matching shadcn component before custom markup.
- Preserve the Wayfinder save URL, initial content, optimistic version handling, autosave, errors, slash commands, markdown blocks, todos, dividers, callouts, tables, and the `5_000_000` character limit.
- Do not change dependencies.

---

### Task 1: Full-Screen Book Notes Workspace

**Files:**
- Modify: `tests/Browser/BookNotesTest.php:5-29`
- Modify: `resources/js/pages/books/notes.tsx:1-49`

**Interfaces:**
- Consumes: `NotesPanel` props `bookId`, `initialNotes`, `initialVersion`, `saveUrl`, `title`, `placeholder`, `variant`, and `maxLength`; `Sidebar` props `book` and `storylines`; Wayfinder `update.url(book)`.
- Produces: A book notes route whose `[data-notes-panel]` bounding rectangle starts at viewport top and reaches viewport right and bottom beside the existing sidebar.

- [ ] **Step 1: Add a failing full-screen browser assertion**

In `tests/Browser/BookNotesTest.php`, immediately after `$page = visit("/books/{$book->id}/notes");`, add:

```php
    $layout = $page->script(<<<'JS'
        (() => {
            const panel = document.querySelector('[data-notes-panel]');
            const rect = panel.getBoundingClientRect();

            return {
                top: Math.round(rect.top),
                right: Math.round(rect.right),
                bottom: Math.round(rect.bottom),
                viewportWidth: window.innerWidth,
                viewportHeight: window.innerHeight,
            };
        })()
    JS);

    expect($layout['top'])->toBe(0)
        ->and($layout['right'])->toBe($layout['viewportWidth'])
        ->and($layout['bottom'])->toBe($layout['viewportHeight']);
```

This tests the observable layout contract rather than Tailwind class strings. The current fixed-height card fails because the panel is inset and ends before the viewport edges.

- [ ] **Step 2: Run the focused test and verify the layout assertion fails**

Run:

```bash
php artisan test --compact tests/Browser/BookNotesTest.php
```

Expected: FAIL on `right` and/or `bottom`; the existing page uses `p-12`, `max-w-[760px]`, and `h-[560px]`.

- [ ] **Step 3: Replace the constrained page composition with the editor-style workspace**

Replace `resources/js/pages/books/notes.tsx` with:

```tsx
import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { update } from '@/actions/App/Http/Controllers/BookNotesController';
import NotesPanel from '@/components/editor/NotesPanel';
import Sidebar from '@/components/editor/Sidebar';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import type { Book } from '@/types/models';

type Props = {
    book: Pick<Book, 'id' | 'title' | 'notes' | 'notes_version'>;
};

export default function Notes({ book }: Props) {
    const { t } = useTranslation('editor');
    const storylines = useSidebarStorylines();

    return (
        <>
            <Head title={`${t('notesResearch.pageTitle')} — ${book.title}`} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar book={book} storylines={storylines} />

                <main className="flex min-w-0 flex-1 flex-col overflow-hidden">
                    <NotesPanel
                        bookId={book.id}
                        initialNotes={book.notes ?? null}
                        initialVersion={book.notes_version ?? 0}
                        saveUrl={update.url(book)}
                        title={t('notesResearch.notebookTitle')}
                        placeholder={t('notesResearch.placeholder')}
                        variant="page"
                        maxLength={5_000_000}
                    />
                </main>
            </div>
        </>
    );
}
```

This removes only the page-level `Card`, `PageHeader`, padding, max-width, and fixed height. Do not modify `NotesPanel`, `NotesSlashMenu`, or their existing shared `Button` usage.

- [ ] **Step 4: Format the touched files**

Run:

```bash
npx prettier --write resources/js/pages/books/notes.tsx
vendor/bin/pint --dirty --format agent
```

Expected: the TSX file formats successfully, Pint formats the modified Pest file, and both commands exit successfully.

- [ ] **Step 5: Run the focused browser test and verify it passes**

Run:

```bash
php artisan test --compact tests/Browser/BookNotesTest.php
```

Expected: PASS. The full-screen bounding-box assertions, todo/table flow, persistence assertions, and JavaScript error assertion all succeed.

- [ ] **Step 6: Run focused static verification**

Run:

```bash
npx eslint resources/js/pages/books/notes.tsx
```

Expected: exit code 0 with no lint errors.

Search `resources/js/pages/books/notes.tsx`, `resources/js/components/editor/NotesPanel.tsx`, and `resources/js/components/editor/NotesSlashMenu.tsx` for raw `<button` markup. Expected: zero matches. Existing actions must remain `@/components/ui/Button` instances.

- [ ] **Step 7: Smoke-test the rendered route in Chromium**

Resolve the application URL with Laravel Boost's `get-absolute-url` tool, open `/books/{book}/notes`, and inspect `[data-notes-panel]` in a real browser. Verify its top is `0`, right equals `window.innerWidth`, bottom equals `window.innerHeight`, the sidebar remains visible, and browser logs contain no new JavaScript errors.

- [ ] **Step 8: Commit the implementation**

```bash
git add resources/js/pages/books/notes.tsx tests/Browser/BookNotesTest.php
git commit -m "fix: expand book notes workspace"
```
