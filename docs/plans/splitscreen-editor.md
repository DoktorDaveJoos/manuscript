# Splitscreen Editor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enable opening multiple chapters side by side in vertical split panes, where each pane is an equal, independent chapter editor with shared access bar panels following focus.

**Architecture:** Extract the current monolithic `ChapterShow` page into a pane manager (`EditorPage`) that owns an array of `ChapterPane` components. Each pane fetches its own chapter data via a new JSON API endpoint and manages its own auto-save lifecycle. A focus model determines which pane the shared toolbar and panels operate on. Pane state is stored in the URL query string (`?panes=12,45`) for persistence across refreshes.

**Tech Stack:** React 19, Inertia.js v2, TipTap, Tailwind v4, Laravel 12, TypeScript

---

## File Structure

### New files
| File | Responsibility |
|------|---------------|
| `resources/js/pages/chapters/editor.tsx` | New `EditorPage` — pane manager, URL sync, shared panels, focus tracking |
| `resources/js/components/editor/ChapterPane.tsx` | Self-contained chapter editor (extracted from `ChapterShow` guts) — fetches data, manages scenes, title, auto-save |
| `resources/js/components/editor/PaneEmptyState.tsx` | Empty state shown when all panes are closed — tips + "Create Chapter" buttons |
| `resources/js/hooks/usePaneManager.ts` | Hook managing pane array, focus, open/close/navigate logic, URL sync |
| `resources/js/hooks/useChapterData.ts` | Hook that fetches chapter data via JSON API for a single pane |

### Modified files
| File | Changes |
|------|---------|
| `app/Http/Controllers/ChapterController.php` | Add `showJson()` method returning chapter data as JSON; update `editor()` to render new `editor` page with pane IDs |
| `routes/web.php` | Add JSON chapter endpoint route; update `/books/{book}/editor` route to render `chapters/editor` page |
| `resources/js/components/editor/Sidebar.tsx` | Pass `onOpenInNewPane` callback; handle Cmd+click for new pane |
| `resources/js/components/editor/ChapterList.tsx` | Thread `onOpenInNewPane` to chapter items; add Cmd+click detection |
| `resources/js/components/editor/ChapterListItem.tsx` | Detect Cmd+click, call `onOpenInNewPane`; normal click calls `onChapterNavigate` |
| `resources/js/components/editor/ChapterContextMenu.tsx` | Add "Open in New Pane" menu item |
| `resources/js/components/editor/EditorBar.tsx` | Add close (X) button; accept `onClose` prop |
| `resources/js/pages/chapters/show.tsx` | Redirect to new `editor` page (backwards compat), or keep as thin wrapper |

---

## Task 1: JSON API Endpoint for Chapter Data

Add a server-side endpoint that returns chapter data as JSON (not Inertia). This is how each pane will fetch its chapter independently.

**Files:**
- Modify: `app/Http/Controllers/ChapterController.php:88-131`
- Modify: `routes/web.php:65`

- [ ] **Step 1: Add the `showJson` method to `ChapterController`**

```php
public function showJson(Book $book, Chapter $chapter): JsonResponse
{
    $chapter->load([
        'currentVersion:id,chapter_id,version_number,content,source,is_current',
        'pendingVersion:id,chapter_id,version_number,content,source,change_summary,status',
        'scenes' => fn ($q) => $q->orderBy('sort_order'),
        'storyline:id,name,timeline_label',
        'povCharacter:id,name',
        'characters' => fn ($q) => $q->select('characters.id', 'characters.name'),
    ]);

    return response()->json([
        'chapter' => $chapter,
        'versionCount' => $chapter->versions()->count(),
        'prosePassRules' => Book::globalProsePassRules(),
        'proofreadingConfig' => Book::globalProofreadingConfig(),
        'customDictionary' => $book->custom_dictionary ?? [],
    ]);
}
```

- [ ] **Step 2: Register the route in `routes/web.php`**

Add after line 65 (the existing `chapters.show` route):

```php
Route::get('/books/{book}/chapters/{chapter}/json', [ChapterController::class, 'showJson'])->name('chapters.show.json');
```

- [ ] **Step 3: Generate Wayfinder types**

Run:
```bash
php artisan wayfinder:generate
```

- [ ] **Step 4: Write a feature test**

```bash
php artisan make:test --pest ChapterShowJsonTest
```

```php
<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\User;

it('returns chapter data as json', function () {
    $user = User::factory()->create();
    $book = Book::factory()->for($user)->create();
    $chapter = Chapter::factory()->for($book)->create();

    $response = $this->actingAs($user)
        ->getJson("/books/{$book->id}/chapters/{$chapter->id}/json");

    $response->assertOk()
        ->assertJsonStructure([
            'chapter' => ['id', 'title', 'scenes'],
            'versionCount',
            'prosePassRules',
            'proofreadingConfig',
        ]);
});
```

- [ ] **Step 5: Run the test**

```bash
php artisan test --compact --filter=ChapterShowJsonTest
```
Expected: PASS

- [ ] **Step 6: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ChapterController.php routes/web.php tests/Feature/ChapterShowJsonTest.php
git commit -m "feat: add JSON endpoint for chapter data (splitscreen support)"
```

---

## Task 2: `useChapterData` Hook

A React hook that fetches chapter data from the JSON endpoint for a single pane. Returns loading state, chapter data, and a refresh function.

**Files:**
- Create: `resources/js/hooks/useChapterData.ts`

- [ ] **Step 1: Create the hook**

```typescript
import { useCallback, useEffect, useRef, useState } from 'react';
import { showJson } from '@/actions/App/Http/Controllers/ChapterController';
import { jsonFetchHeaders } from '@/lib/utils';
import type {
    Chapter,
    Character,
    CharacterChapterPivot,
    ProofreadingConfig,
    ProsePassRule,
    Scene,
} from '@/types/models';

type ChapterWithRelations = Chapter & {
    characters?: (Character & { pivot: CharacterChapterPivot })[];
    scenes?: Scene[];
};

export type ChapterData = {
    chapter: ChapterWithRelations;
    versionCount: number;
    prosePassRules?: ProsePassRule[];
    proofreadingConfig?: ProofreadingConfig;
    customDictionary?: string[];
};

type UseChapterDataReturn = {
    data: ChapterData | null;
    isLoading: boolean;
    error: string | null;
    refresh: () => void;
};

export default function useChapterData(
    bookId: number,
    chapterId: number | null,
): UseChapterDataReturn {
    const [data, setData] = useState<ChapterData | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const abortRef = useRef<AbortController | null>(null);

    const fetchData = useCallback(async () => {
        if (!chapterId) return;

        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;

        setIsLoading(true);
        setError(null);

        try {
            const response = await fetch(
                showJson.url({ book: bookId, chapter: chapterId }),
                {
                    headers: jsonFetchHeaders(),
                    signal: controller.signal,
                },
            );

            if (!response.ok) throw new Error('Failed to load chapter');

            const json: ChapterData = await response.json();
            setData(json);
        } catch (e) {
            if ((e as Error).name !== 'AbortError') {
                setError((e as Error).message);
            }
        } finally {
            if (!controller.signal.aborted) {
                setIsLoading(false);
            }
        }
    }, [bookId, chapterId]);

    useEffect(() => {
        fetchData();
        return () => abortRef.current?.abort();
    }, [fetchData]);

    return { data, isLoading, error, refresh: fetchData };
}
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/hooks/useChapterData.ts
git commit -m "feat: add useChapterData hook for fetch-based chapter loading"
```

---

## Task 3: `ChapterPane` Component

Extract the core editor from `ChapterShow` into a self-contained pane component. Each pane manages its own scenes, title auto-save, word count, and editor state. It receives chapter data as a prop (loaded by the parent via `useChapterData`).

**Files:**
- Create: `resources/js/components/editor/ChapterPane.tsx`
- Reference: `resources/js/pages/chapters/show.tsx` (source of extracted logic)

- [ ] **Step 1: Create `ChapterPane.tsx` with the editor core**

This component takes chapter data and renders the EditorBar + FormattingToolbar + WritingSurface stack. It manages:
- `scenes`, `chapterTitle`, `chapterNotes` local state
- `activeEditor`, `activeSceneId` tracking
- Title auto-save with debounce + flush
- Scene CRUD (add, split)
- Word count aggregation
- Save status tracking

```typescript
import type { Editor } from '@tiptap/react';
import { X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    show,
    split,
    updateTitle,
} from '@/actions/App/Http/Controllers/ChapterController';
import { store as storeScene } from '@/actions/App/Http/Controllers/SceneController';
import type { SaveStatus } from '@/components/editor/EditorBar';
import EditorBar from '@/components/editor/EditorBar';
import FormattingToolbar from '@/components/editor/FormattingToolbar';
import WritingSurface from '@/components/editor/WritingSurface';
import type { ChapterData } from '@/hooks/useChapterData';
import { useProofreading } from '@/hooks/useProofreading';
import { jsonFetchHeaders } from '@/lib/utils';
import { DEFAULT_PROOFREADING_CONFIG } from '@/types/models';
import type { AppSettings, Scene } from '@/types/models';

export type ChapterPaneHandle = {
    chapterId: number;
    flushAll: () => Promise<void>;
};

export default function ChapterPane({
    bookId,
    chapterData,
    appSettings,
    isFocused,
    isFocusMode,
    onFocus,
    onClose,
    onActiveEditorChange,
    onSaveStatusChange,
    onNotesChange,
}: {
    bookId: number;
    chapterData: ChapterData;
    appSettings: AppSettings;
    isFocused: boolean;
    isFocusMode: boolean;
    onFocus: () => void;
    onClose: () => void;
    onActiveEditorChange: (editor: Editor | null, sceneId: number | null) => void;
    onSaveStatusChange: (status: SaveStatus) => void;
    onNotesChange?: (notes: string | null) => void;
}) {
    const { t } = useTranslation('editor');
    const { chapter, versionCount, proofreadingConfig: initialProofreadingConfig } = chapterData;

    const {
        config: proofreadingConfig,
    } = useProofreading(
        initialProofreadingConfig ?? DEFAULT_PROOFREADING_CONFIG,
        [],
        bookId,
    );

    const [saveStatus, setSaveStatus] = useState<SaveStatus>('saved');
    const [chapterTitle, setChapterTitle] = useState(chapter.title);
    const [scenes, setScenes] = useState<Scene[]>(chapter.scenes ?? []);
    const [chapterNotes, setChapterNotes] = useState<string | null>(chapter.notes);
    const [activeEditor, setActiveEditor] = useState<Editor | null>(null);
    const activeEditorRef = useRef<Editor | null>(null);
    activeEditorRef.current = activeEditor;
    const [activeSceneId, setActiveSceneId] = useState<number | null>(null);
    const [pendingFocusSceneId, setPendingFocusSceneId] = useState<number | null>(null);
    const [showVersions, setShowVersions] = useState(false);

    // Sync save status up to parent
    useEffect(() => {
        onSaveStatusChange(saveStatus);
    }, [saveStatus, onSaveStatusChange]);

    // Sync active editor up to parent when this pane is focused
    useEffect(() => {
        if (isFocused) {
            onActiveEditorChange(activeEditor, activeSceneId);
        }
    }, [isFocused, activeEditor, activeSceneId, onActiveEditorChange]);

    // Reset state when chapter data changes
    useEffect(() => {
        setScenes(chapter.scenes ?? []);
        setChapterTitle(chapter.title);
        setChapterNotes(chapter.notes);
    }, [chapter.id, chapter.scenes, chapter.title, chapter.notes]);

    const wordCount = scenes.reduce((sum, s) => sum + s.word_count, 0);

    const handleSceneWordCountChange = useCallback(
        (sceneId: number, count: number) => {
            setScenes((prev) =>
                prev.map((s) =>
                    s.id === sceneId ? { ...s, word_count: count } : s,
                ),
            );
        },
        [],
    );

    // Title auto-save
    const titleAbortRef = useRef<AbortController | null>(null);
    const titleTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const pendingTitleRef = useRef<string | null>(null);

    const flushTitleSave = useCallback(async () => {
        if (titleTimerRef.current) {
            clearTimeout(titleTimerRef.current);
            titleTimerRef.current = null;
        }
        const title = pendingTitleRef.current;
        if (title === null) return;
        pendingTitleRef.current = null;

        titleAbortRef.current?.abort();
        const controller = new AbortController();
        titleAbortRef.current = controller;

        setSaveStatus('saving');
        try {
            const response = await fetch(
                updateTitle.url({ book: bookId, chapter: chapter.id }),
                {
                    method: 'PATCH',
                    headers: jsonFetchHeaders(),
                    body: JSON.stringify({ title }),
                    signal: controller.signal,
                },
            );
            if (!response.ok) throw new Error('Save failed');
            setSaveStatus('saved');
        } catch (e) {
            if ((e as Error).name !== 'AbortError') setSaveStatus('error');
        }
    }, [bookId, chapter.id]);

    const handleTitleUpdate = useCallback(
        (title: string) => {
            setChapterTitle(title);
            setSaveStatus('unsaved');
            pendingTitleRef.current = title;
            if (titleTimerRef.current) clearTimeout(titleTimerRef.current);
            titleTimerRef.current = setTimeout(() => flushTitleSave(), 1500);
        },
        [flushTitleSave],
    );

    // Flush on unmount
    const flushTitleRef = useRef(flushTitleSave);
    flushTitleRef.current = flushTitleSave;
    useEffect(() => {
        return () => {
            if (titleTimerRef.current) clearTimeout(titleTimerRef.current);
            flushTitleRef.current();
        };
    }, []);

    // Flush all saves (title + scenes)
    const flushAll = useCallback(async () => {
        const sceneFlushes = Array.from(
            document.querySelectorAll(`[id^="scene-"][data-pane-chapter="${chapter.id}"]`),
        ).map((el) => {
            const flush = (el as unknown as Record<string, () => Promise<void>>).__flush;
            return typeof flush === 'function' ? flush() : Promise.resolve();
        });
        await Promise.all([flushTitleSave(), ...sceneFlushes]);
    }, [flushTitleSave, chapter.id]);

    // Expose flushAll via ref on DOM node for parent access
    const paneRef = useRef<HTMLDivElement>(null);
    useEffect(() => {
        const el = paneRef.current;
        if (el) {
            (el as unknown as Record<string, unknown>).__flushPane = flushAll;
        }
    }, [flushAll]);

    // Scene add
    const handleAddScene = useCallback(
        async (afterPosition: number) => {
            try {
                const response = await fetch(
                    storeScene.url({ book: bookId, chapter: chapter.id }),
                    {
                        method: 'POST',
                        headers: jsonFetchHeaders(),
                        body: JSON.stringify({
                            title: `Scene ${scenes.length + 1}`,
                            position: afterPosition,
                        }),
                    },
                );
                if (response.ok) {
                    const newScene: Scene = await response.json();
                    setScenes((prev) => {
                        const updated = [...prev];
                        updated.splice(afterPosition, 0, newScene);
                        return updated.map((s, i) => ({ ...s, sort_order: i }));
                    });
                    setPendingFocusSceneId(newScene.id);
                }
            } catch {
                // Ignore
            }
        },
        [bookId, chapter.id, scenes.length],
    );

    const [isTypewriterMode, setIsTypewriterMode] = useState(appSettings.typewriter_mode);

    const toggleTypewriterMode = useCallback(() => {
        const next = !isTypewriterMode;
        setIsTypewriterMode(next);
        fetch('/settings', {
            method: 'PUT',
            headers: jsonFetchHeaders(),
            body: JSON.stringify({ key: 'typewriter_mode', value: next }),
        });
    }, [isTypewriterMode]);

    const editorFont = appSettings.editor_font;
    const editorFontSize = appSettings.editor_font_size;
    const scenesVisible = appSettings.show_scenes;

    const povCharacterName = chapter.pov_character?.name ?? null;
    const timelineLabel = chapter.storyline?.timeline_label ?? null;
    const displayTitle = chapterTitle.split('\n')[0];

    const handleEditorFocus = useCallback(
        (editor: Editor) => {
            setActiveEditor(editor);
            onFocus();
        },
        [onFocus],
    );

    const handleSceneIdChange = useCallback(
        (sceneId: number) => {
            setActiveSceneId(sceneId);
        },
        [],
    );

    return (
        <div
            ref={paneRef}
            data-pane-chapter={chapter.id}
            className={`relative flex min-w-[400px] flex-1 flex-col ${
                isFocused
                    ? 'border-t-2 border-t-accent'
                    : 'border-t-2 border-t-transparent opacity-[0.97]'
            }`}
            onMouseDown={onFocus}
        >
            {/* Editor Bar with close button */}
            <div className={`overflow-hidden transition-[height,opacity] duration-300 ${isFocusMode ? 'h-0 opacity-0' : 'h-[38px]'}`}>
                <div className="flex items-center">
                    <div className="flex-1">
                        <EditorBar
                            chapter={chapter}
                            chapterTitle={displayTitle}
                            storylineName={chapter.storyline?.name ?? t('show.untitledStoryline')}
                            wordCount={wordCount}
                            saveStatus={saveStatus}
                            versionCount={versionCount}
                            onVersionClick={() => setShowVersions(!showVersions)}
                        />
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="mr-2 flex size-6 items-center justify-center rounded-md text-ink-faint hover:bg-ink/5 hover:text-ink"
                    >
                        <X size={14} />
                    </button>
                </div>
            </div>

            {/* Formatting toolbar (only shown for focused pane) */}
            <div className={`transition-[height,opacity] duration-300 ${
                isFocusMode || appSettings.hide_formatting_toolbar || !isFocused
                    ? 'h-0 overflow-hidden opacity-0'
                    : 'h-[38px]'
            }`}>
                <FormattingToolbar
                    editor={activeEditor}
                    onToggleFocusMode={() => {}}
                    isTypewriterMode={isTypewriterMode}
                    onToggleTypewriterMode={toggleTypewriterMode}
                />
            </div>

            <WritingSurface
                scenes={scenes}
                bookId={bookId}
                chapterId={chapter.id}
                title={chapterTitle}
                autoSelectTitle={false}
                povCharacterName={povCharacterName}
                timelineLabel={timelineLabel}
                onTitleUpdate={handleTitleUpdate}
                activeEditor={activeEditor}
                onActiveEditorChange={handleEditorFocus}
                onWordCountChange={handleSceneWordCountChange}
                onSaveStatusChange={setSaveStatus}
                isTypewriterMode={isTypewriterMode}
                editorFont={editorFont}
                editorFontSize={editorFontSize}
                pendingFocusSceneId={pendingFocusSceneId}
                onFocusHandled={() => setPendingFocusSceneId(null)}
                onActiveSceneIdChange={handleSceneIdChange}
                scenesVisible={scenesVisible}
                proofreadingConfig={proofreadingConfig}
                bookLanguage={chapter.book_id ? undefined : undefined}
            />
        </div>
    );
}
```

Note: The `data-pane-chapter` attribute is used by `flushAll` to scope scene flush queries to this pane only, avoiding conflicts between panes.

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/editor/ChapterPane.tsx
git commit -m "feat: extract ChapterPane component from ChapterShow"
```

---

## Task 4: `usePaneManager` Hook

Manages the pane array, focused pane index, and URL synchronization. Handles open, close, navigate, and focus operations.

**Files:**
- Create: `resources/js/hooks/usePaneManager.ts`

- [ ] **Step 1: Create the hook**

```typescript
import { router } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';

export type Pane = {
    id: string; // unique instance ID (not chapter ID — for React keys)
    chapterId: number;
};

let paneIdCounter = 0;
function nextPaneId(): string {
    return `pane-${++paneIdCounter}`;
}

function panesFromQuery(query: string | null, fallbackChapterId?: number): Pane[] {
    if (query) {
        const ids = query.split(',').map(Number).filter(Boolean);
        if (ids.length > 0) {
            return ids.map((chapterId) => ({ id: nextPaneId(), chapterId }));
        }
    }
    if (fallbackChapterId) {
        return [{ id: nextPaneId(), chapterId: fallbackChapterId }];
    }
    return [];
}

function syncUrl(bookId: number, panes: Pane[]) {
    const paneIds = panes.map((p) => p.chapterId).join(',');
    const url = `/books/${bookId}/editor${panes.length ? `?panes=${paneIds}` : ''}`;
    window.history.replaceState({}, '', url);
}

export default function usePaneManager(bookId: number, initialQuery: string | null, fallbackChapterId?: number) {
    const [panes, setPanes] = useState<Pane[]>(() =>
        panesFromQuery(initialQuery, fallbackChapterId),
    );
    const [focusedPaneId, setFocusedPaneId] = useState<string | null>(
        () => panes[0]?.id ?? null,
    );

    const panesRef = useRef(panes);
    panesRef.current = panes;

    const updatePanes = useCallback(
        (updater: (prev: Pane[]) => Pane[]) => {
            setPanes((prev) => {
                const next = updater(prev);
                panesRef.current = next;
                syncUrl(bookId, next);
                return next;
            });
        },
        [bookId],
    );

    // Open a chapter in a new pane (if not already open)
    const openInNewPane = useCallback(
        (chapterId: number) => {
            const existing = panesRef.current.find((p) => p.chapterId === chapterId);
            if (existing) {
                setFocusedPaneId(existing.id);
                return;
            }
            const newPane: Pane = { id: nextPaneId(), chapterId };
            updatePanes((prev) => [...prev, newPane]);
            setFocusedPaneId(newPane.id);
        },
        [updatePanes],
    );

    // Navigate: replace the focused pane's chapter (or focus existing pane if already open)
    const navigateToChapter = useCallback(
        (chapterId: number) => {
            const existing = panesRef.current.find((p) => p.chapterId === chapterId);
            if (existing) {
                setFocusedPaneId(existing.id);
                return;
            }
            if (!focusedPaneId) {
                openInNewPane(chapterId);
                return;
            }
            updatePanes((prev) =>
                prev.map((p) =>
                    p.id === focusedPaneId ? { ...p, chapterId } : p,
                ),
            );
        },
        [focusedPaneId, openInNewPane, updatePanes],
    );

    // Close a pane by ID; focus shifts left
    const closePane = useCallback(
        (paneId: string) => {
            updatePanes((prev) => {
                const index = prev.findIndex((p) => p.id === paneId);
                const next = prev.filter((p) => p.id !== paneId);

                // Update focus
                if (focusedPaneId === paneId) {
                    const newFocusIndex = Math.max(0, index - 1);
                    setFocusedPaneId(next[newFocusIndex]?.id ?? null);
                }

                return next;
            });
        },
        [focusedPaneId, updatePanes],
    );

    const focusedPane = panes.find((p) => p.id === focusedPaneId) ?? null;

    return {
        panes,
        focusedPaneId,
        focusedPane,
        setFocusedPaneId,
        openInNewPane,
        navigateToChapter,
        closePane,
    };
}
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/hooks/usePaneManager.ts
git commit -m "feat: add usePaneManager hook for splitscreen pane orchestration"
```

---

## Task 5: `PaneEmptyState` Component

Shown when all panes are closed. Displays tips and action buttons to create a chapter or open an existing one.

**Files:**
- Create: `resources/js/components/editor/PaneEmptyState.tsx`

- [ ] **Step 1: Create the component**

```typescript
import { PanelLeft, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import Kbd from '@/components/ui/Kbd';

export default function PaneEmptyState({
    onCreateChapter,
}: {
    onCreateChapter: () => void;
}) {
    const { t } = useTranslation('editor');

    return (
        <div className="flex flex-1 flex-col items-center justify-center gap-6 pb-20">
            <div className="flex flex-col items-center gap-3 text-center">
                <div className="flex size-12 items-center justify-center rounded-xl bg-neutral-bg">
                    <PanelLeft size={24} className="text-ink-muted" />
                </div>
                <h2 className="font-serif text-xl font-semibold text-ink">
                    {t('emptyPane.title', 'No chapters open')}
                </h2>
                <p className="max-w-[320px] text-sm leading-relaxed text-ink-muted">
                    {t(
                        'emptyPane.description',
                        'Select a chapter from the sidebar to start writing, or create a new one.',
                    )}
                </p>
            </div>
            <div className="flex items-center gap-3">
                <Button variant="primary" size="md" onClick={onCreateChapter}>
                    <Plus size={14} />
                    {t('emptyPane.createChapter', 'New Chapter')}
                </Button>
            </div>
            <div className="mt-4 flex flex-col items-center gap-2 text-[12px] text-ink-faint">
                <span>
                    {t('emptyPane.tipSplit', 'Right-click a chapter to open in a new pane')}
                </span>
                <span className="flex items-center gap-1.5">
                    <Kbd keys="⌘" />
                    {t('emptyPane.tipModClick', '+ click to open side by side')}
                </span>
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/editor/PaneEmptyState.tsx
git commit -m "feat: add PaneEmptyState component for all-panes-closed state"
```

---

## Task 6: `EditorPage` — The Pane Manager Page

The new Inertia page component that replaces `ChapterShow` as the main editor entry point. It owns the pane array, renders `ChapterPane` instances, and manages shared state (panels, keyboard shortcuts, focus mode).

**Files:**
- Create: `resources/js/pages/chapters/editor.tsx`

- [ ] **Step 1: Create the `EditorPage` component**

This is the largest component. It wires together:
- `usePaneManager` for pane orchestration
- `useChapterData` for each pane's data
- Shared panels (wiki, notes, AI, chat, editorial) that follow focus
- Keyboard shortcuts (Cmd+P, Cmd+F, Cmd+Shift+F, Escape)
- Focus mode
- The sidebar with `onBeforeNavigate` flushing all panes
- `beforeunload` event to flush all panes

```typescript
import { Head, router, usePage } from '@inertiajs/react';
import type { Editor } from '@tiptap/react';
import {
    BookOpen,
    MessageCircle,
    NotebookPen,
    NotebookText,
    Sparkles,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import AccessBar from '@/components/editor/AccessBar';
import type { AccessBarItemConfig, PanelId } from '@/components/editor/AccessBar';
import AiChatDrawer from '@/components/editor/AiChatDrawer';
import AiPanel from '@/components/editor/AiPanel';
import CommandPalette from '@/components/editor/CommandPalette';
import EditorialReviewPanel from '@/components/editor/EditorialReviewPanel';
import GlobalFindDrawer from '@/components/editor/GlobalFindDrawer';
import NotesPanel from '@/components/editor/NotesPanel';
import Sidebar from '@/components/editor/Sidebar';
import WikiPanel from '@/components/editor/WikiPanel';
import ChapterPane from '@/components/editor/ChapterPane';
import PaneEmptyState from '@/components/editor/PaneEmptyState';
import type { SaveStatus } from '@/components/editor/EditorBar';
import type { SearchHighlight } from '@/extensions/SearchHighlightExtension';
import SlidePanel from '@/components/ui/SlidePanel';
import Kbd from '@/components/ui/Kbd';
import { useAiFeatures } from '@/hooks/useAiFeatures';
import useChapterData from '@/hooks/useChapterData';
import usePaneManager from '@/hooks/usePaneManager';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import { createChapter } from '@/lib/utils';
import type { AppSettings, Book } from '@/types/models';

const VALID_PANELS: Set<PanelId> = new Set([
    'wiki', 'notes', 'ai', 'chat', 'editorial',
]);

/** Wrapper that fetches data for a single pane and renders ChapterPane */
function PaneWithData({
    bookId,
    chapterId,
    appSettings,
    isFocused,
    isFocusMode,
    onFocus,
    onClose,
    onActiveEditorChange,
    onSaveStatusChange,
}: {
    bookId: number;
    chapterId: number;
    appSettings: AppSettings;
    isFocused: boolean;
    isFocusMode: boolean;
    onFocus: () => void;
    onClose: () => void;
    onActiveEditorChange: (editor: Editor | null, sceneId: number | null) => void;
    onSaveStatusChange: (status: SaveStatus) => void;
}) {
    const { data, isLoading, error } = useChapterData(bookId, chapterId);

    if (isLoading || !data) {
        return (
            <div className="flex min-w-[400px] flex-1 items-center justify-center">
                <div className="size-5 animate-spin rounded-full border-2 border-ink-faint border-t-transparent" />
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex min-w-[400px] flex-1 items-center justify-center text-sm text-ink-muted">
                Failed to load chapter
            </div>
        );
    }

    return (
        <ChapterPane
            bookId={bookId}
            chapterData={data}
            appSettings={appSettings}
            isFocused={isFocused}
            isFocusMode={isFocusMode}
            onFocus={onFocus}
            onClose={onClose}
            onActiveEditorChange={onActiveEditorChange}
            onSaveStatusChange={onSaveStatusChange}
        />
    );
}

export default function EditorPage({
    book,
    initialPanes,
    fallbackChapterId,
}: {
    book: Book;
    initialPanes: string | null;
    fallbackChapterId: number | null;
}) {
    const { t } = useTranslation('editor');
    const { t: tAi } = useTranslation('ai');
    const { t: tEditorial } = useTranslation('editorial-review');
    const { visible: aiVisible } = useAiFeatures();
    const { app_settings } = usePage<{ app_settings: AppSettings }>().props;
    const sidebarStorylines = useSidebarStorylines();

    const {
        panes,
        focusedPaneId,
        focusedPane,
        setFocusedPaneId,
        openInNewPane,
        navigateToChapter,
        closePane,
    } = usePaneManager(book.id, initialPanes, fallbackChapterId ?? undefined);

    // Track active editor + scene from the focused pane
    const [activeEditor, setActiveEditor] = useState<Editor | null>(null);
    const activeEditorRef = useRef<Editor | null>(null);
    const [activeSceneId, setActiveSceneId] = useState<number | null>(null);

    const handleActiveEditorChange = useCallback(
        (editor: Editor | null, sceneId: number | null) => {
            setActiveEditor(editor);
            activeEditorRef.current = editor;
            setActiveSceneId(sceneId);
        },
        [],
    );

    // Save status: worst-case-wins across all panes
    const paneStatusesRef = useRef<Map<string, SaveStatus>>(new Map());
    const [combinedSaveStatus, setCombinedSaveStatus] = useState<SaveStatus>('saved');

    const updateCombinedStatus = useCallback(() => {
        const statuses = Array.from(paneStatusesRef.current.values());
        if (statuses.includes('error')) setCombinedSaveStatus('error');
        else if (statuses.includes('unsaved')) setCombinedSaveStatus('unsaved');
        else if (statuses.includes('saving')) setCombinedSaveStatus('saving');
        else setCombinedSaveStatus('saved');
    }, []);

    const makePaneSaveStatusHandler = useCallback(
        (paneId: string) => (status: SaveStatus) => {
            paneStatusesRef.current.set(paneId, status);
            updateCombinedStatus();
        },
        [updateCombinedStatus],
    );

    // Flush all panes before navigation
    const flushAllPanes = useCallback(async () => {
        const paneEls = document.querySelectorAll('[data-pane-chapter]');
        const flushes = Array.from(paneEls).map((el) => {
            const flush = (el as unknown as Record<string, () => Promise<void>>).__flushPane;
            return typeof flush === 'function' ? flush() : Promise.resolve();
        });
        await Promise.all(flushes);
    }, []);

    // Flush on beforeunload
    useEffect(() => {
        const handler = () => {
            flushAllPanes();
        };
        window.addEventListener('beforeunload', handler);
        return () => window.removeEventListener('beforeunload', handler);
    }, [flushAllPanes]);

    // Panel state (same pattern as current ChapterShow)
    const [openPanels, setOpenPanels] = useState<Set<PanelId>>(() => {
        try {
            const stored = localStorage.getItem('manuscript:open-panels');
            if (!stored) return new Set();
            const parsed: unknown = JSON.parse(stored);
            if (!Array.isArray(parsed)) return new Set();
            return new Set(parsed.filter((p): p is PanelId => VALID_PANELS.has(p)));
        } catch {
            return new Set();
        }
    });

    useEffect(() => {
        try {
            localStorage.setItem('manuscript:open-panels', JSON.stringify([...openPanels]));
        } catch { /* no-op */ }
    }, [openPanels]);

    const togglePanel = useCallback((panel: PanelId) => {
        setOpenPanels((prev) => {
            const next = new Set(prev);
            if (next.has(panel)) next.delete(panel);
            else next.add(panel);
            return next;
        });
    }, []);

    const closePanel = useCallback((panel: PanelId) => {
        setOpenPanels((prev) => {
            if (!prev.has(panel)) return prev;
            const next = new Set(prev);
            next.delete(panel);
            return next;
        });
    }, []);

    const closePanelAndFocus = useCallback(
        (panel: PanelId) => {
            closePanel(panel);
            activeEditorRef.current?.commands.focus();
        },
        [closePanel],
    );

    const closeWiki = useCallback(() => closePanelAndFocus('wiki'), [closePanelAndFocus]);
    const closeNotes = useCallback(() => closePanelAndFocus('notes'), [closePanelAndFocus]);
    const closeAi = useCallback(() => closePanelAndFocus('ai'), [closePanelAndFocus]);
    const closeChat = useCallback(() => closePanelAndFocus('chat'), [closePanelAndFocus]);
    const closeEditorial = useCallback(() => closePanelAndFocus('editorial'), [closePanelAndFocus]);

    // Focus mode
    const [isFocusMode, setIsFocusMode] = useState(() => {
        try { return localStorage.getItem('manuscript:focus-mode') === 'true'; }
        catch { return false; }
    });

    const toggleFocusMode = useCallback(() => {
        setIsFocusMode((prev) => {
            const next = !prev;
            try { localStorage.setItem('manuscript:focus-mode', String(next)); } catch { /* no-op */ }
            if (next) document.documentElement.requestFullscreen?.().catch(() => {});
            else if (document.fullscreenElement) document.exitFullscreen?.().catch(() => {});
            return next;
        });
    }, []);

    const exitFocusMode = useCallback(() => {
        setIsFocusMode(false);
        try { localStorage.setItem('manuscript:focus-mode', 'false'); } catch { /* no-op */ }
        if (document.fullscreenElement) document.exitFullscreen?.().catch(() => {});
    }, []);

    // Command palette
    const [isPaletteOpen, setIsPaletteOpen] = useState(false);
    const [isFindOpen, setIsFindOpen] = useState(false);
    const [findShowReplace, setFindShowReplace] = useState(false);
    const [searchHighlight, setSearchHighlight] = useState<SearchHighlight | null>(null);
    const [isLocalFindOpen, setIsLocalFindOpen] = useState(false);
    const [localFindShowReplace, setLocalFindShowReplace] = useState(false);

    // Keyboard shortcuts
    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if (e.key === 'p' && (e.metaKey || e.ctrlKey)) {
                e.preventDefault();
                setIsPaletteOpen((prev) => !prev);
            } else if (e.key === 'f' && (e.metaKey || e.ctrlKey) && e.shiftKey) {
                e.preventDefault();
                setIsLocalFindOpen(false);
                setIsFindOpen(true);
                setFindShowReplace(false);
            } else if (e.key === 'f' && (e.metaKey || e.ctrlKey) && !e.shiftKey) {
                e.preventDefault();
                setIsFindOpen(false);
                setSearchHighlight(null);
                setIsLocalFindOpen(true);
                setLocalFindShowReplace(false);
            } else if (e.key === 'r' && (e.metaKey || e.ctrlKey) && e.shiftKey) {
                e.preventDefault();
                setIsLocalFindOpen(false);
                setIsFindOpen(true);
                setFindShowReplace(true);
            } else if (e.key === 'h' && (e.metaKey || e.ctrlKey) && !e.shiftKey) {
                e.preventDefault();
                setIsFindOpen(false);
                setSearchHighlight(null);
                setIsLocalFindOpen(true);
                setLocalFindShowReplace(true);
            } else if (e.key === 'Escape' && isFocusMode && !isPaletteOpen) {
                e.preventDefault();
                exitFocusMode();
            }
        };
        document.addEventListener('keydown', handler, { capture: true });
        return () => document.removeEventListener('keydown', handler, { capture: true });
    }, [isFocusMode, isPaletteOpen, exitFocusMode]);

    useEffect(() => {
        const onFullscreenChange = () => {
            if (!document.fullscreenElement && isFocusMode) {
                setIsFocusMode(false);
                try { localStorage.setItem('manuscript:focus-mode', 'false'); } catch { /* no-op */ }
            }
        };
        document.addEventListener('fullscreenchange', onFullscreenChange);
        return () => document.removeEventListener('fullscreenchange', onFullscreenChange);
    }, [isFocusMode]);

    const accessBarItems = useMemo(() => {
        const items: AccessBarItemConfig[] = [
            { id: 'wiki', icon: BookOpen, label: t('toolbar.wiki') },
            { id: 'notes', icon: NotebookPen, label: t('toolbar.notes') },
        ];
        if (aiVisible) {
            items.push(
                { id: 'ai', icon: Sparkles, label: tAi('headerTitle') },
                { id: 'chat', icon: MessageCircle, label: tAi('askAi') },
                { id: 'editorial', icon: NotebookText, label: tEditorial('panel.title') },
            );
        }
        return items;
    }, [aiVisible, t, tAi, tEditorial]);

    // Sidebar navigation handlers
    const handleBeforeNavigate = useCallback(async () => {
        await flushAllPanes();
    }, [flushAllPanes]);

    const handleChapterNavigate = useCallback(
        (chapterId: number) => {
            navigateToChapter(chapterId);
        },
        [navigateToChapter],
    );

    const handleNewChapter = useCallback(async () => {
        await flushAllPanes();
        createChapter(book.id, undefined, sidebarStorylines);
    }, [book.id, flushAllPanes, sidebarStorylines]);

    // Determine which chapter data to pass to panels (from focused pane)
    // Panels need the focused pane's chapter — ChapterPane exposes this via the data-pane-chapter attr
    // For panels that need chapter object, we'll use a lightweight approach
    const focusedChapterId = focusedPane?.chapterId ?? null;

    return (
        <>
            <Head title={book.title} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar
                    book={book}
                    storylines={sidebarStorylines}
                    activeChapterId={focusedChapterId}
                    activeChapterTitle=""
                    activeChapterWordCount={0}
                    onBeforeNavigate={handleBeforeNavigate}
                    activeScenes={[]}
                    onChapterRename={() => {}}
                    onSceneRename={() => {}}
                    onSceneDelete={() => {}}
                    onSceneReorder={() => {}}
                    onSceneAdd={() => {}}
                    scenesVisible={app_settings.show_scenes}
                    onScenesVisibleChange={() => {}}
                    isFocusMode={isFocusMode}
                    onChapterNavigate={handleChapterNavigate}
                    onOpenInNewPane={openInNewPane}
                />

                {/* Pane area */}
                <div className="relative flex min-w-0 flex-1">
                    {panes.length === 0 ? (
                        <PaneEmptyState onCreateChapter={handleNewChapter} />
                    ) : (
                        panes.map((pane) => (
                            <PaneWithData
                                key={pane.id}
                                bookId={book.id}
                                chapterId={pane.chapterId}
                                appSettings={app_settings}
                                isFocused={pane.id === focusedPaneId}
                                isFocusMode={isFocusMode}
                                onFocus={() => setFocusedPaneId(pane.id)}
                                onClose={() => closePane(pane.id)}
                                onActiveEditorChange={handleActiveEditorChange}
                                onSaveStatusChange={makePaneSaveStatusHandler(pane.id)}
                            />
                        ))
                    )}

                    {/* Vertical dividers between panes */}
                    {/* Handled via CSS border on each ChapterPane */}
                </div>

                {/* Right-side panels */}
                {!isFocusMode && (
                    <>
                        <SlidePanel
                            open={openPanels.has('wiki')}
                            onClose={closeWiki}
                            storageKey="manuscript:wiki-panel-width"
                            defaultWidth={300}
                            minWidth={200}
                            maxWidth={600}
                        >
                            <WikiPanel book={book} chapter={null} onClose={closeWiki} />
                        </SlidePanel>

                        {focusedChapterId && (
                            <SlidePanel
                                open={openPanels.has('notes')}
                                onClose={closeNotes}
                                storageKey="manuscript:notes-width"
                                defaultWidth={260}
                            >
                                <NotesPanel
                                    key={focusedChapterId}
                                    bookId={book.id}
                                    chapterId={focusedChapterId}
                                    initialNotes={null}
                                    onNotesChange={() => {}}
                                    onClose={closeNotes}
                                />
                            </SlidePanel>
                        )}

                        {isFindOpen && focusedChapterId && (
                            <GlobalFindDrawer
                                bookId={book.id}
                                currentChapterId={focusedChapterId}
                                onClose={() => {
                                    setIsFindOpen(false);
                                    setSearchHighlight(null);
                                }}
                                onNavigate={(chapterId) => navigateToChapter(chapterId)}
                                onSearchChange={setSearchHighlight}
                                showReplace={findShowReplace}
                            />
                        )}

                        <AccessBar
                            items={accessBarItems}
                            openPanels={openPanels}
                            onToggle={togglePanel}
                        />
                    </>
                )}
            </div>
        </>
    );
}
```

Note: This is a skeleton — the panel props (chapter object for WikiPanel, AiPanel, etc.) will need to be wired to the focused pane's chapter data. This is addressed in Task 8.

- [ ] **Step 2: Commit**

```bash
git add resources/js/pages/chapters/editor.tsx
git commit -m "feat: add EditorPage pane manager as new editor entry point"
```

---

## Task 7: Server-Side Routing Changes

Update the Laravel routes and controller to serve the new `EditorPage` instead of `ChapterShow`.

**Files:**
- Modify: `app/Http/Controllers/ChapterController.php:32-47` (the `editor()` method)
- Modify: `routes/web.php:63-65`

- [ ] **Step 1: Update `ChapterController@editor` to render the new page**

Replace the `editor()` method:

```php
public function editor(Request $request, Book $book): RedirectResponse|Response
{
    $firstChapter = $book->chapters()
        ->orderBy('reader_order')
        ->first();

    if (! $firstChapter && ! $request->query('panes')) {
        $book->load('storylines:id,book_id,name');

        return Inertia::render('chapters/empty', [
            'book' => $book,
        ]);
    }

    $book->load([
        'storylines' => fn ($q) => $q->orderBy('sort_order'),
        'storylines.chapters' => fn ($q) => $q
            ->select('id', 'book_id', 'storyline_id', 'title', 'reader_order', 'status', 'word_count')
            ->orderBy('reader_order'),
        'storylines.chapters.scenes' => fn ($q) => $q
            ->select('id', 'chapter_id', 'title', 'sort_order', 'word_count')
            ->orderBy('sort_order'),
    ]);

    return Inertia::render('chapters/editor', [
        'book' => $book,
        'initialPanes' => $request->query('panes'),
        'fallbackChapterId' => $firstChapter?->id,
    ]);
}
```

You'll need to add `use Illuminate\Http\Request;` at the top of the controller if not already imported.

- [ ] **Step 2: Update the `chapters.show` route to redirect to the editor**

In `routes/web.php`, change the `chapters.show` route to redirect to the editor with the chapter as a pane query param. This ensures old URLs and Inertia links still work:

```php
Route::get('/books/{book}/chapters/{chapter}', function (Book $book, Chapter $chapter) {
    return redirect()->route('books.editor', ['book' => $book, 'panes' => $chapter->id]);
})->name('chapters.show');
```

Add the model imports at the top of `routes/web.php` if not present:
```php
use App\Models\Book;
use App\Models\Chapter;
```

- [ ] **Step 3: Generate Wayfinder types**

```bash
php artisan wayfinder:generate
```

- [ ] **Step 4: Run existing tests to check for regressions**

```bash
php artisan test --compact
```

Expected: All pass (the redirect preserves the `chapters.show` named route).

- [ ] **Step 5: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ChapterController.php routes/web.php
git commit -m "feat: route editor through new pane-based EditorPage"
```

---

## Task 8: Wire Sidebar for Splitscreen

Update the sidebar to support `onChapterNavigate` and `onOpenInNewPane` callbacks. The sidebar needs to:
1. Handle normal click → call `onChapterNavigate(chapterId)` instead of `router.visit`
2. Handle Cmd+click → call `onOpenInNewPane(chapterId)`
3. Add "Open in New Pane" to the chapter context menu

**Files:**
- Modify: `resources/js/components/editor/Sidebar.tsx`
- Modify: `resources/js/components/editor/ChapterList.tsx`
- Modify: `resources/js/components/editor/ChapterListItem.tsx`
- Modify: `resources/js/components/editor/ChapterContextMenu.tsx`

- [ ] **Step 1: Add props to `Sidebar.tsx`**

Add two optional props to the Sidebar component:

```typescript
onChapterNavigate?: (chapterId: number) => void;
onOpenInNewPane?: (chapterId: number) => void;
```

Thread these down to `<ChapterList />`.

- [ ] **Step 2: Update `ChapterList.tsx` to thread the new props**

Add the same two optional props. Pass them through to each `SortableChapterItem` and ultimately to `ChapterListItem`. Also pass `onOpenInNewPane` to `ChapterContextMenu`.

- [ ] **Step 3: Update `ChapterListItem.tsx` to handle Cmd+click**

Replace the `handleClick` function:

```typescript
const handleClick = async (e: React.MouseEvent) => {
    if (isActive && !onOpenInNewPane) return;

    if ((e.metaKey || e.ctrlKey) && onOpenInNewPane) {
        onOpenInNewPane(chapter.id);
        return;
    }

    if (onBeforeNavigate) {
        await onBeforeNavigate();
    }

    if (onChapterNavigate) {
        onChapterNavigate(chapter.id);
    } else {
        router.visit(show.url({ book: bookId, chapter: chapter.id }), {
            preserveScroll: true,
        });
    }
};
```

Add props:
```typescript
onChapterNavigate?: (chapterId: number) => void;
onOpenInNewPane?: (chapterId: number) => void;
```

- [ ] **Step 4: Add "Open in New Pane" to `ChapterContextMenu.tsx`**

Add a new optional prop and menu item:

```typescript
onOpenInNewPane?: (chapterId: number) => void;
```

Add before the separator/delete section:

```tsx
{onOpenInNewPane && (
    <ContextMenu.Item
        icon={<PanelLeft size={14} className="shrink-0 text-ink-muted" />}
        label={t('contextMenu.openInNewPane', 'Open in New Pane')}
        onClick={() => {
            onClose();
            onOpenInNewPane(chapter.id);
        }}
    />
)}
```

Import `PanelLeft` from `lucide-react`.

- [ ] **Step 5: Add translation keys**

Add to the editor translation file(s):
- `contextMenu.openInNewPane`: "Open in New Pane"
- `emptyPane.title`: "No chapters open"
- `emptyPane.description`: "Select a chapter from the sidebar to start writing, or create a new one."
- `emptyPane.createChapter`: "New Chapter"
- `emptyPane.tipSplit`: "Right-click a chapter to open in a new pane"
- `emptyPane.tipModClick`: "+ click to open side by side"

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/editor/Sidebar.tsx resources/js/components/editor/ChapterList.tsx resources/js/components/editor/ChapterListItem.tsx resources/js/components/editor/ChapterContextMenu.tsx
git commit -m "feat: wire sidebar for splitscreen — Cmd+click and context menu support"
```

---

## Task 9: Pane Dividers and Focus Styling

Add visual separation between panes and the focus indicator.

**Files:**
- Modify: `resources/js/components/editor/ChapterPane.tsx`

- [ ] **Step 1: Add vertical divider between panes**

In `ChapterPane`, add a left border when the pane is not the first child. Use the CSS selector approach via a wrapper in `EditorPage`:

In `EditorPage`, wrap the pane area with a flex container that uses a gap or border pattern:

```tsx
{panes.map((pane, index) => (
    <React.Fragment key={pane.id}>
        {index > 0 && (
            <div className="w-px shrink-0 bg-border-light" />
        )}
        <PaneWithData ... />
    </React.Fragment>
))}
```

- [ ] **Step 2: Verify focus styling in `ChapterPane`**

The `ChapterPane` already has:
- `border-t-2 border-t-accent` when focused
- `border-t-2 border-t-transparent opacity-[0.97]` when not focused

Verify this renders correctly with 2+ panes. The accent color provides the active indicator, and the slight opacity reduction mutes inactive panes.

- [ ] **Step 3: Build and verify visually**

```bash
npm run build
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/chapters/editor.tsx resources/js/components/editor/ChapterPane.tsx
git commit -m "feat: add pane dividers and focus indicator styling"
```

---

## Task 10: Panel Focus-Following for Notes and AI

Ensure the right-side panels (notes, AI, chat, editorial) respond to the focused pane's chapter. The wiki panel is book-scoped and doesn't need changes. The notes panel needs to reload when the focused chapter changes. AI panels need the focused chapter's data.

**Files:**
- Modify: `resources/js/pages/chapters/editor.tsx`
- Modify: `resources/js/components/editor/ChapterPane.tsx`

- [ ] **Step 1: Expose focused chapter data from ChapterPane**

Add a state-lifting pattern: `ChapterPane` calls an `onChapterDataReady` callback with its loaded chapter data. The parent `EditorPage` tracks the focused pane's chapter data for passing to panels.

In `EditorPage`, add:

```typescript
const [focusedChapterData, setFocusedChapterData] = useState<ChapterData | null>(null);
```

In `PaneWithData`, when data loads and the pane is focused, call:

```typescript
useEffect(() => {
    if (isFocused && data) {
        onFocusedChapterDataChange(data);
    }
}, [isFocused, data]);
```

- [ ] **Step 2: Wire panels to focused chapter data**

Update the panel rendering in `EditorPage` to use `focusedChapterData`:

```tsx
{focusedChapterData && (
    <SlidePanel open={openPanels.has('notes')} onClose={closeNotes} ...>
        <NotesPanel
            key={focusedChapterData.chapter.id}
            bookId={book.id}
            chapterId={focusedChapterData.chapter.id}
            initialNotes={focusedChapterData.chapter.notes}
            onNotesChange={() => {}}
            onClose={closeNotes}
        />
    </SlidePanel>
)}
```

Apply similar pattern for AI panels that need chapter-specific data.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/chapters/editor.tsx resources/js/components/editor/ChapterPane.tsx
git commit -m "feat: wire panels to follow focused pane's chapter data"
```

---

## Task 11: Backward Compatibility and Cleanup

Ensure the old `ChapterShow` page still works for any direct links or bookmarks. Clean up dead code paths.

**Files:**
- Modify: `resources/js/pages/chapters/show.tsx`

- [ ] **Step 1: Make `ChapterShow` redirect to EditorPage**

Convert `show.tsx` into a thin redirect that sends the user to the new editor page:

```typescript
import { router } from '@inertiajs/react';
import { useEffect } from 'react';

export default function ChapterShow({ book, chapter }: { book: { id: number }; chapter: { id: number } }) {
    useEffect(() => {
        router.visit(`/books/${book.id}/editor?panes=${chapter.id}`, { replace: true });
    }, [book.id, chapter.id]);

    return null;
}
```

Or, if the server-side redirect (Task 7, Step 2) handles this entirely, simply delete the body and keep a minimal component that will never actually render.

- [ ] **Step 2: Run the full test suite**

```bash
php artisan test --compact
```

Expected: All pass.

- [ ] **Step 3: Build frontend**

```bash
npm run build
```

Expected: No TypeScript errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/chapters/show.tsx
git commit -m "refactor: convert ChapterShow to redirect for backward compat"
```

---

## Task 12: Integration Testing

Write feature tests covering the splitscreen-specific behaviors.

**Files:**
- Create: `tests/Feature/EditorSplitscreenTest.php`

- [ ] **Step 1: Create the test file**

```bash
php artisan make:test --pest EditorSplitscreenTest
```

- [ ] **Step 2: Write the tests**

```php
<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\User;

it('loads editor page with single pane from query', function () {
    $user = User::factory()->create();
    $book = Book::factory()->for($user)->create();
    $chapter = Chapter::factory()->for($book)->create();

    $response = $this->actingAs($user)
        ->get("/books/{$book->id}/editor?panes={$chapter->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('chapters/editor')
            ->has('book')
            ->where('initialPanes', (string) $chapter->id)
        );
});

it('loads editor page with multiple panes from query', function () {
    $user = User::factory()->create();
    $book = Book::factory()->for($user)->create();
    $ch1 = Chapter::factory()->for($book)->create();
    $ch2 = Chapter::factory()->for($book)->create();

    $response = $this->actingAs($user)
        ->get("/books/{$book->id}/editor?panes={$ch1->id},{$ch2->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('chapters/editor')
            ->where('initialPanes', "{$ch1->id},{$ch2->id}")
        );
});

it('redirects chapters.show to editor with pane', function () {
    $user = User::factory()->create();
    $book = Book::factory()->for($user)->create();
    $chapter = Chapter::factory()->for($book)->create();

    $response = $this->actingAs($user)
        ->get("/books/{$book->id}/chapters/{$chapter->id}");

    $response->assertRedirect("/books/{$book->id}/editor?panes={$chapter->id}");
});

it('falls back to first chapter when no panes query', function () {
    $user = User::factory()->create();
    $book = Book::factory()->for($user)->create();
    $chapter = Chapter::factory()->for($book)->create();

    $response = $this->actingAs($user)
        ->get("/books/{$book->id}/editor");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('chapters/editor')
            ->where('fallbackChapterId', $chapter->id)
        );
});

it('shows empty state when book has no chapters', function () {
    $user = User::factory()->create();
    $book = Book::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->get("/books/{$book->id}/editor");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->component('chapters/empty'));
});
```

- [ ] **Step 3: Run the tests**

```bash
php artisan test --compact --filter=EditorSplitscreenTest
```

Expected: All PASS.

- [ ] **Step 4: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/EditorSplitscreenTest.php
git commit -m "test: add integration tests for splitscreen editor routing"
```

---

## Implementation Order Summary

| Task | Description | Dependencies |
|------|-------------|-------------|
| 1 | JSON API endpoint | None |
| 2 | `useChapterData` hook | Task 1 |
| 3 | `ChapterPane` component | None (can use mock data initially) |
| 4 | `usePaneManager` hook | None |
| 5 | `PaneEmptyState` component | None |
| 6 | `EditorPage` (pane manager page) | Tasks 2-5 |
| 7 | Server-side routing changes | Task 6 |
| 8 | Sidebar splitscreen wiring | Tasks 4, 6 |
| 9 | Pane dividers and focus styling | Task 6 |
| 10 | Panel focus-following | Tasks 6, 3 |
| 11 | Backward compatibility cleanup | Tasks 7 |
| 12 | Integration tests | Tasks 7 |

Tasks 1-5 can be parallelized (they're independent). Tasks 6+ are sequential.
