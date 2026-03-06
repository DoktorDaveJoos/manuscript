import { beautify } from '@/actions/App/Http/Controllers/AiController';
import { split, updateTitle } from '@/actions/App/Http/Controllers/ChapterController';
import { destroy as destroyScene, store as storeScene } from '@/actions/App/Http/Controllers/SceneController';
import NormalizePreview from '@/components/dashboard/NormalizePreview';
import AiPanel from '@/components/editor/AiPanel';
import CommandPalette from '@/components/editor/CommandPalette';
import EditorBar, { type SaveStatus } from '@/components/editor/EditorBar';
import FormattingToolbar from '@/components/editor/FormattingToolbar';
import NotesPanel from '@/components/editor/NotesPanel';
import Sidebar from '@/components/editor/Sidebar';
import VersionHistoryOverlay from '@/components/editor/VersionHistoryOverlay';
import WritingSurface from '@/components/editor/WritingSurface';
import { useLicense } from '@/hooks/useLicense';
import { getXsrfToken } from '@/lib/csrf';
import { createChapter, jsonFetchHeaders } from '@/lib/utils';
import type { Book, Chapter, Character, CharacterChapterPivot, Scene } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import { DOMSerializer } from '@tiptap/pm/model';
import type { Editor } from '@tiptap/react';
import { useCallback, useEffect, useRef, useState } from 'react';

type ChapterWithRelations = Chapter & {
    characters?: (Character & { pivot: CharacterChapterPivot })[];
};

export default function ChapterShow({
    book,
    chapter,
    versionCount,
}: {
    book: Book;
    chapter: ChapterWithRelations;
    versionCount: number;
}) {
    const { isActive: isLicensed } = useLicense();
    const [saveStatus, setSaveStatus] = useState<SaveStatus>('saved');
    const [scenes, setScenes] = useState<Scene[]>(chapter.scenes ?? []);
    const [activeEditor, setActiveEditor] = useState<Editor | null>(null);
    const [showVersions, setShowVersions] = useState(false);
    const [showNormalize, setShowNormalize] = useState(false);
    const [isBeautifying, setIsBeautifying] = useState(false);
    const [isPaletteOpen, setIsPaletteOpen] = useState(false);
    const [isNotesOpen, setIsNotesOpen] = useState(false);
    const [isTypewriterMode, setIsTypewriterMode] = useState(() => {
        try {
            return localStorage.getItem('manuscript:typewriter-scrolling') === 'true';
        } catch {
            return false;
        }
    });

    const toggleTypewriterMode = useCallback(() => {
        setIsTypewriterMode((prev) => {
            const next = !prev;
            try {
                localStorage.setItem('manuscript:typewriter-scrolling', String(next));
            } catch {
                // Ignore storage errors
            }
            return next;
        });
    }, []);

    const [isAiPanelOpen, setIsAiPanelOpen] = useState(() => {
        try {
            return localStorage.getItem('manuscript:ai-panel-open') !== 'false';
        } catch {
            return true;
        }
    });

    const toggleAiPanel = useCallback(() => {
        setIsAiPanelOpen((prev) => {
            const next = !prev;
            try {
                localStorage.setItem('manuscript:ai-panel-open', String(next));
            } catch {
                // Ignore storage errors
            }
            return next;
        });
    }, []);

    // Reset scenes when chapter changes (e.g. after version restore)
    useEffect(() => {
        setScenes(chapter.scenes ?? []);
    }, [chapter.id, chapter.scenes]);

    // Word count derived from scenes
    const wordCount = scenes.reduce((sum, s) => sum + s.word_count, 0);

    // Save status timeout for scene edits
    const saveTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const handleSceneWordCountChange = useCallback((sceneId: number, count: number) => {
        setScenes((prev) => prev.map((s) => (s.id === sceneId ? { ...s, word_count: count } : s)));
        setSaveStatus('unsaved');

        if (saveTimeoutRef.current) clearTimeout(saveTimeoutRef.current);
        saveTimeoutRef.current = setTimeout(() => {
            setSaveStatus('saved');
        }, 2000);
    }, []);

    // Clean up save status timeout on unmount
    useEffect(() => {
        return () => {
            if (saveTimeoutRef.current) clearTimeout(saveTimeoutRef.current);
        };
    }, []);

    // Chapter title auto-save
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
            const response = await fetch(updateTitle.url({ book: book.id, chapter: chapter.id }), {
                method: 'PATCH',
                headers: jsonFetchHeaders(),
                body: JSON.stringify({ title }),
                signal: controller.signal,
            });

            if (!response.ok) throw new Error('Save failed');

            setSaveStatus('saved');
        } catch (e) {
            if ((e as Error).name !== 'AbortError') {
                setSaveStatus('error');
            }
        }
    }, [book.id, chapter.id]);

    const handleTitleUpdate = useCallback(
        (title: string) => {
            setSaveStatus('unsaved');
            pendingTitleRef.current = title;

            if (titleTimerRef.current) {
                clearTimeout(titleTimerRef.current);
            }

            titleTimerRef.current = setTimeout(() => {
                flushTitleSave();
            }, 1500);
        },
        [flushTitleSave],
    );

    // Flush all pending saves (title + all scenes in parallel)
    const handleBeforeNavigate = useCallback(async () => {
        const sceneFlushes = Array.from(document.querySelectorAll('[id^="scene-"]')).map((el) => {
            const flush = (el as unknown as Record<string, () => Promise<void>>).__flush;
            return typeof flush === 'function' ? flush() : Promise.resolve();
        });

        await Promise.all([flushTitleSave(), ...sceneFlushes]);
    }, [flushTitleSave]);

    // Scene management
    const handleAddScene = useCallback(
        async (afterPosition: number) => {
            try {
                const response = await fetch(storeScene.url({ book: book.id, chapter: chapter.id }), {
                    method: 'POST',
                    headers: jsonFetchHeaders(),
                    body: JSON.stringify({
                        title: `Scene ${scenes.length + 1}`,
                        position: afterPosition,
                    }),
                });

                if (response.ok) {
                    const newScene: Scene = await response.json();
                    setScenes((prev) => {
                        const updated = [...prev];
                        updated.splice(afterPosition, 0, newScene);
                        return updated.map((s, i) => ({ ...s, sort_order: i }));
                    });
                }
            } catch {
                // Ignore
            }
        },
        [book.id, chapter.id, scenes.length],
    );

    const handleDeleteScene = useCallback(
        async (sceneId: number) => {
            try {
                const response = await fetch(
                    destroyScene.url({ book: book.id, chapter: chapter.id, scene: sceneId }),
                    {
                        method: 'DELETE',
                        headers: {
                            Accept: 'application/json',
                            'X-XSRF-TOKEN': getXsrfToken(),
                        },
                    },
                );

                if (response.ok) {
                    setScenes((prev) => prev.filter((s) => s.id !== sceneId));
                }
            } catch {
                // Ignore
            }
        },
        [book.id, chapter.id],
    );

    const handleBeautify = useCallback(async () => {
        setIsBeautifying(true);
        try {
            const response = await fetch(beautify.url({ book: book.id, chapter: chapter.id }), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
            });

            if (!response.ok) throw new Error('Beautify failed');

            router.reload();
        } catch {
            setSaveStatus('error');
        } finally {
            setIsBeautifying(false);
        }
    }, [book.id, chapter.id]);

    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if (e.key === '/' && e.shiftKey) {
                e.preventDefault();
                setIsPaletteOpen((prev) => !prev);
            }
        };
        document.addEventListener('keydown', handler, { capture: true });
        return () => document.removeEventListener('keydown', handler, { capture: true });
    }, []);

    const handleSplitChapter = useCallback(async () => {
        if (!activeEditor) return;

        const { from } = activeEditor.state.selection;
        const endPos = activeEditor.state.doc.content.size;

        const afterSlice = activeEditor.state.doc.slice(from, endPos);
        const serializer = DOMSerializer.fromSchema(activeEditor.schema);
        const container = document.createElement('div');
        container.appendChild(serializer.serializeFragment(afterSlice.content));
        const belowHtml = container.innerHTML;

        activeEditor.chain().deleteRange({ from, to: endPos }).run();

        await handleBeforeNavigate();

        const response = await fetch(split.url({ book: book.id, chapter: chapter.id }), {
            method: 'POST',
            headers: jsonFetchHeaders(),
            body: JSON.stringify({ title: 'Untitled', initial_content: belowHtml }),
        });

        if (!response.ok) return;

        const data = await response.json();
        router.visit(data.url);
    }, [activeEditor, book.id, chapter.id, handleBeforeNavigate]);

    const handleNewChapter = useCallback(async () => {
        await handleBeforeNavigate();
        createChapter(book.id, chapter.storyline_id, book.storylines ?? []);
    }, [book, chapter.storyline_id, handleBeforeNavigate]);

    const closePalette = useCallback(() => setIsPaletteOpen(false), []);
    const handlePaletteAddScene = useCallback(() => handleAddScene(scenes.length), [handleAddScene, scenes.length]);

    const povCharacterName = chapter.pov_character?.name ?? null;
    const timelineLabel = chapter.storyline?.timeline_label ?? null;

    return (
        <>
            <Head title={`${chapter.title} — ${book.title}`} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar
                    book={book}
                    storylines={book.storylines ?? []}
                    activeChapterId={chapter.id}
                    onBeforeNavigate={handleBeforeNavigate}
                />

                <div className="relative flex min-w-0 flex-1 flex-col">
                    <div className="relative">
                        <EditorBar
                            chapter={chapter}
                            storylineName={chapter.storyline?.name ?? 'Untitled storyline'}
                            wordCount={wordCount}
                            versionCount={versionCount}
                            saveStatus={saveStatus}
                            onVersionClick={() => setShowVersions(!showVersions)}
                            onNotesToggle={() => setIsNotesOpen((prev) => !prev)}
                            isNotesOpen={isNotesOpen}
                            hasNotes={!!chapter.notes}
                        />
                        {showVersions && (
                            <VersionHistoryOverlay
                                bookId={book.id}
                                chapterId={chapter.id}
                                onClose={() => setShowVersions(false)}
                            />
                        )}
                    </div>

                    {isNotesOpen && (
                        <NotesPanel
                            bookId={book.id}
                            chapterId={chapter.id}
                            initialNotes={chapter.notes}
                            onClose={() => setIsNotesOpen(false)}
                        />
                    )}

                    <FormattingToolbar
                        editor={activeEditor}
                        onNormalizeClick={() => setShowNormalize(true)}
                        onBeautifyClick={handleBeautify}
                        aiEnabled={book.ai_enabled}
                        isBeautifying={isBeautifying}
                        licensed={isLicensed}
                        isTypewriterMode={isTypewriterMode}
                        onTypewriterToggle={toggleTypewriterMode}
                    />

                    <WritingSurface
                        scenes={scenes}
                        bookId={book.id}
                        chapterId={chapter.id}
                        title={chapter.title}
                        povCharacterName={povCharacterName}
                        timelineLabel={timelineLabel}
                        onTitleUpdate={handleTitleUpdate}
                        activeEditor={activeEditor}
                        onActiveEditorChange={setActiveEditor}
                        onWordCountChange={handleSceneWordCountChange}
                        onAddScene={handleAddScene}
                        onDeleteScene={handleDeleteScene}
                        isTypewriterMode={isTypewriterMode}
                    />

                    <CommandPalette
                        editor={activeEditor}
                        isOpen={isPaletteOpen}
                        onClose={closePalette}
                        onSplitChapter={handleSplitChapter}
                        onNewChapter={handleNewChapter}
                        onAddScene={handlePaletteAddScene}
                    />
                </div>

                <AiPanel
                    characters={(chapter.characters as (Character & { pivot: CharacterChapterPivot })[]) ?? []}
                    book={book}
                    chapter={chapter}
                    isOpen={isAiPanelOpen}
                    onToggle={toggleAiPanel}
                    licensed={isLicensed}
                />
            </div>

            {showNormalize && (
                <NormalizePreview
                    bookId={book.id}
                    chapterId={chapter.id}
                    onClose={() => setShowNormalize(false)}
                />
            )}
        </>
    );
}
