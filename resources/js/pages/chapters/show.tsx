import { beautify } from '@/actions/App/Http/Controllers/AiController';
import { updateTitle } from '@/actions/App/Http/Controllers/ChapterController';
import { store as storeScene } from '@/actions/App/Http/Controllers/SceneController';
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

function firstLine(text: string): string {
    return text.split('\n')[0];
}

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
    const [chapterTitle, setChapterTitle] = useState(chapter.title);
    const [scenes, setScenes] = useState<Scene[]>(chapter.scenes ?? []);
    const [activeEditor, setActiveEditor] = useState<Editor | null>(null);
    const [activeSceneId, setActiveSceneId] = useState<number | null>(null);
    const [pendingFocusSceneId, setPendingFocusSceneId] = useState<number | null>(null);
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

    const [isFocusMode, setIsFocusMode] = useState(() => {
        try {
            return localStorage.getItem('manuscript:focus-mode') === 'true';
        } catch {
            return false;
        }
    });

    const toggleFocusMode = useCallback(() => {
        setIsFocusMode((prev) => {
            const next = !prev;
            try {
                localStorage.setItem('manuscript:focus-mode', String(next));
            } catch {}
            if (next) {
                document.documentElement.requestFullscreen?.().catch(() => {});
            } else {
                if (document.fullscreenElement) document.exitFullscreen?.().catch(() => {});
            }
            return next;
        });
    }, []);

    const exitFocusMode = useCallback(() => {
        setIsFocusMode(false);
        try {
            localStorage.setItem('manuscript:focus-mode', 'false');
        } catch {}
        if (document.fullscreenElement) document.exitFullscreen?.().catch(() => {});
    }, []);

    const [editorFont, setEditorFont] = useState(() => {
        try {
            return localStorage.getItem('manuscript:editor-font') || 'eb-garamond';
        } catch {
            return 'eb-garamond';
        }
    });

    const handleFontChange = useCallback((fontId: string) => {
        setEditorFont(fontId);
        try {
            localStorage.setItem('manuscript:editor-font', fontId);
        } catch {
            // Ignore storage errors
        }
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

    // Reset scenes and title when chapter changes (e.g. after version restore)
    useEffect(() => {
        setScenes(chapter.scenes ?? []);
    }, [chapter.id, chapter.scenes]);

    useEffect(() => {
        setChapterTitle(chapter.title);
    }, [chapter.id, chapter.title]);

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
            setChapterTitle(title);
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
                    setPendingFocusSceneId(newScene.id);
                }
            } catch {
                // Ignore
            }
        },
        [book.id, chapter.id, scenes.length],
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
            if (e.code === 'Slash' && e.shiftKey) {
                e.preventDefault();
                setIsPaletteOpen((prev) => !prev);
            } else if (e.key === 'Tab' && e.shiftKey) {
                e.preventDefault();
                setIsPaletteOpen((prev) => !prev);
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
                try {
                    localStorage.setItem('manuscript:focus-mode', 'false');
                } catch {}
            }
        };
        document.addEventListener('fullscreenchange', onFullscreenChange);
        return () => document.removeEventListener('fullscreenchange', onFullscreenChange);
    }, [isFocusMode]);

    const handleSplitScene = useCallback(async () => {
        if (!activeEditor || !activeSceneId) return;

        const { from } = activeEditor.state.selection;
        const endPos = activeEditor.state.doc.content.size;

        const afterSlice = activeEditor.state.doc.slice(from, endPos);
        const serializer = DOMSerializer.fromSchema(activeEditor.schema);
        const container = document.createElement('div');
        container.appendChild(serializer.serializeFragment(afterSlice.content));
        const belowHtml = container.innerHTML;

        activeEditor.chain().deleteRange({ from, to: endPos }).run();

        // Flush the current scene's save
        const sceneEl = document.getElementById(`scene-${activeSceneId}`);
        const flush = (sceneEl as unknown as Record<string, () => Promise<void>>)?.__flush;
        if (typeof flush === 'function') await flush();

        const currentIndex = scenes.findIndex((s) => s.id === activeSceneId);
        const insertPosition = currentIndex + 1;

        const response = await fetch(storeScene.url({ book: book.id, chapter: chapter.id }), {
            method: 'POST',
            headers: jsonFetchHeaders(),
            body: JSON.stringify({
                title: `Scene ${scenes.length + 1}`,
                position: insertPosition,
                content: belowHtml,
            }),
        });

        if (!response.ok) return;

        const newScene: Scene = await response.json();
        setScenes((prev) => {
            const updated = [...prev];
            updated.splice(insertPosition, 0, newScene);
            return updated.map((s, i) => ({ ...s, sort_order: i }));
        });
        setPendingFocusSceneId(newScene.id);
    }, [activeEditor, activeSceneId, book.id, chapter.id, scenes]);

    const handleNewChapter = useCallback(async () => {
        await handleBeforeNavigate();
        createChapter(book.id, chapter.storyline_id, book.storylines ?? []);
    }, [book, chapter.storyline_id, handleBeforeNavigate]);

    // Callbacks for sidebar-initiated scene mutations
    const handleSidebarSceneRename = useCallback((sceneId: number, newTitle: string) => {
        setScenes(prev => prev.map(s => s.id === sceneId ? { ...s, title: newTitle } : s));
    }, []);

    const handleSidebarSceneDelete = useCallback((sceneId: number) => {
        setScenes(prev => prev.filter(s => s.id !== sceneId));
    }, []);

    const handleSidebarSceneReorder = useCallback((orderedIds: number[]) => {
        setScenes(prev => {
            const map = new Map(prev.map(s => [s.id, s]));
            return orderedIds.map(id => map.get(id)!).filter(Boolean);
        });
    }, []);

    const closePalette = useCallback(() => setIsPaletteOpen(false), []);
    const handlePaletteAddScene = useCallback(() => handleAddScene(scenes.length), [handleAddScene, scenes.length]);

    const povCharacterName = chapter.pov_character?.name ?? null;
    const timelineLabel = chapter.storyline?.timeline_label ?? null;
    const displayTitle = firstLine(chapterTitle);

    return (
        <>
            <Head title={`${displayTitle} — ${book.title}`} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <div
                    className={`overflow-hidden transition-[width,opacity] duration-300 ${isFocusMode ? 'w-0 opacity-0' : 'w-60'}`}
                >
                    <Sidebar
                        book={book}
                        storylines={book.storylines ?? []}
                        activeChapterId={chapter.id}
                        activeChapterTitle={displayTitle}
                        activeChapterWordCount={wordCount}
                        onBeforeNavigate={handleBeforeNavigate}
                        activeScenes={scenes}
                        onSceneRename={handleSidebarSceneRename}
                        onSceneDelete={handleSidebarSceneDelete}
                        onSceneReorder={handleSidebarSceneReorder}
                        onSceneAdd={handleAddScene}
                    />
                </div>

                <div className="relative flex min-w-0 flex-1 flex-col">
                    <div
                        className={`relative overflow-hidden transition-[height,opacity] duration-300 ${isFocusMode ? 'h-0 opacity-0' : 'h-12'}`}
                    >
                        <EditorBar
                            chapter={chapter}
                            chapterTitle={displayTitle}
                            storylineName={chapter.storyline?.name ?? 'Untitled storyline'}
                            wordCount={wordCount}
                            versionCount={versionCount}
                            saveStatus={saveStatus}
                            onVersionClick={() => setShowVersions(!showVersions)}
                            onNotesToggle={() => setIsNotesOpen((prev) => !prev)}
                            isNotesOpen={isNotesOpen}
                            hasNotes={!!chapter.notes}
                        />
                        {showVersions && !isFocusMode && (
                            <VersionHistoryOverlay
                                bookId={book.id}
                                chapterId={chapter.id}
                                onClose={() => setShowVersions(false)}
                            />
                        )}
                    </div>

                    <div
                        className={`transition-[height,opacity] duration-300 ${isFocusMode ? 'h-0 overflow-hidden opacity-0' : 'h-9'}`}
                    >
                        <FormattingToolbar
                            editor={activeEditor}
                            onNormalizeClick={() => setShowNormalize(true)}
                            onBeautifyClick={handleBeautify}
                            aiEnabled={book.ai_enabled}
                            isBeautifying={isBeautifying}
                            licensed={isLicensed}
                            isTypewriterMode={isTypewriterMode}
                            onTypewriterToggle={toggleTypewriterMode}
                            editorFont={editorFont}
                            onFontChange={handleFontChange}
                        />
                    </div>

                    <WritingSurface
                        scenes={scenes}
                        bookId={book.id}
                        chapterId={chapter.id}
                        title={chapterTitle}
                        povCharacterName={povCharacterName}
                        timelineLabel={timelineLabel}
                        onTitleUpdate={handleTitleUpdate}
                        activeEditor={activeEditor}
                        onActiveEditorChange={setActiveEditor}
                        onWordCountChange={handleSceneWordCountChange}
                        isTypewriterMode={isTypewriterMode}
                        editorFont={editorFont}
                        pendingFocusSceneId={pendingFocusSceneId}
                        onFocusHandled={() => setPendingFocusSceneId(null)}
                        onActiveSceneIdChange={setActiveSceneId}
                    />

                    {isNotesOpen && !isFocusMode && (
                        <NotesPanel
                            bookId={book.id}
                            chapterId={chapter.id}
                            initialNotes={chapter.notes}
                            onClose={() => setIsNotesOpen(false)}
                        />
                    )}

                    <CommandPalette
                        editor={activeEditor}
                        isOpen={isPaletteOpen}
                        onClose={closePalette}
                        onSplitScene={handleSplitScene}
                        onNewChapter={handleNewChapter}
                        onAddScene={handlePaletteAddScene}
                        onEnterFocusMode={toggleFocusMode}
                        isFocusMode={isFocusMode}
                        onToggleNotes={() => setIsNotesOpen((prev) => !prev)}
                        licensed={isLicensed}
                    />
                </div>

                <div
                    className={`overflow-hidden transition-[width,opacity] duration-300 ${isFocusMode ? 'w-0 opacity-0' : ''}`}
                >
                    <AiPanel
                        characters={(chapter.characters as (Character & { pivot: CharacterChapterPivot })[]) ?? []}
                        book={book}
                        chapter={chapter}
                        isOpen={isAiPanelOpen}
                        onToggle={toggleAiPanel}
                        licensed={isLicensed}
                    />
                </div>
            </div>

            {isFocusMode && (
                <WhisperChrome
                    chapterNumber={chapter.reader_order}
                    chapterTitle={displayTitle}
                    wordCount={wordCount}
                />
            )}

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

function WhisperChrome({
    chapterNumber,
    chapterTitle,
    wordCount,
}: {
    chapterNumber: number;
    chapterTitle: string;
    wordCount: number;
}) {
    const [visible, setVisible] = useState(true);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const visibleRef = useRef(true);

    useEffect(() => {
        const onMouseMove = () => {
            if (!visibleRef.current) {
                visibleRef.current = true;
                setVisible(true);
            }
            if (timerRef.current) clearTimeout(timerRef.current);
            timerRef.current = setTimeout(() => {
                visibleRef.current = false;
                setVisible(false);
            }, 2500);
        };

        // Start the fade timer immediately
        timerRef.current = setTimeout(() => {
            visibleRef.current = false;
            setVisible(false);
        }, 2500);

        document.addEventListener('mousemove', onMouseMove);
        return () => {
            document.removeEventListener('mousemove', onMouseMove);
            if (timerRef.current) clearTimeout(timerRef.current);
        };
    }, []);

    return (
        <div
            className={`fixed inset-x-0 bottom-0 z-40 flex items-end justify-between px-12 pb-8 transition-opacity duration-500 ${visible ? 'opacity-100' : 'opacity-0'}`}
        >
            <span className="text-[13px] leading-4 tracking-[0.02em] text-ink-whisper">
                Chapter {chapterNumber} — {chapterTitle}
            </span>
            <span className="absolute left-1/2 -translate-x-1/2 text-[13px] leading-4 tracking-[0.02em] text-ink-whisper">
                Esc to leave focus mode
            </span>
            <span className="text-[13px] leading-4 tracking-[0.02em] text-ink-whisper">
                {wordCount.toLocaleString()} words
            </span>
        </div>
    );
}
