import { split, updateTitle } from '@/actions/App/Http/Controllers/ChapterController';
import { store as storeScene } from '@/actions/App/Http/Controllers/SceneController';
import NormalizePreview from '@/components/dashboard/NormalizePreview';
import AiChatDrawer from '@/components/editor/AiChatDrawer';
import AiPanel from '@/components/editor/AiPanel';
import CommandPalette from '@/components/editor/CommandPalette';
import DiffView from '@/components/editor/DiffView';
import EditorBar, { type SaveStatus } from '@/components/editor/EditorBar';
import FormattingToolbar from '@/components/editor/FormattingToolbar';
import NotesPanel from '@/components/editor/NotesPanel';
import Sidebar from '@/components/editor/Sidebar';
import VersionHistoryOverlay from '@/components/editor/VersionHistoryOverlay';
import WritingSurface from '@/components/editor/WritingSurface';
import Kbd from '@/components/ui/Kbd';
import { useAiFeatures } from '@/hooks/useAiFeatures';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import { getXsrfToken } from '@/lib/csrf';
import { createChapter, jsonFetchHeaders } from '@/lib/utils';
import type { Analysis, Book, Chapter, Character, CharacterChapterPivot, ProsePassRule, Scene } from '@/types/models';
import { Head, router, usePage } from '@inertiajs/react';
import { DOMSerializer } from '@tiptap/pm/model';
import type { Editor } from '@tiptap/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';


type ChapterWithRelations = Chapter & {
    characters?: (Character & { pivot: CharacterChapterPivot })[];
};

function firstLine(text: string): string {
    return text.split('\n')[0];
}

/** Cuts content from cursor to end of document, flushes the scene save, and returns the HTML + scene index. */
async function splitAtCursor(editor: Editor, sceneId: number, scenes: Scene[]): Promise<{ belowHtml: string; currentIndex: number }> {
    const { from } = editor.state.selection;
    const endPos = editor.state.doc.content.size;

    const afterSlice = editor.state.doc.slice(from, endPos);
    const serializer = DOMSerializer.fromSchema(editor.schema);
    const container = document.createElement('div');
    container.appendChild(serializer.serializeFragment(afterSlice.content));
    const belowHtml = container.innerHTML;

    editor.chain().deleteRange({ from, to: endPos }).run();

    // Flush the current scene's save
    const sceneEl = document.getElementById(`scene-${sceneId}`);
    const flush = (sceneEl as unknown as Record<string, () => Promise<void>>)?.__flush;
    if (typeof flush === 'function') await flush();

    const currentIndex = scenes.findIndex((s) => s.id === sceneId);
    return { belowHtml, currentIndex };
}

export default function ChapterShow({
    book,
    chapter,
    versionCount,
    prosePassRules,
    chapterAnalyses,
}: {
    book: Book;
    chapter: ChapterWithRelations;
    versionCount: number;
    prosePassRules?: ProsePassRule[];
    chapterAnalyses?: Record<string, Analysis>;
}) {
    const { t } = useTranslation('editor');
    const pendingVersion = chapter.pending_version ?? null;
    const sidebarStorylines = useSidebarStorylines();
    const { visible: aiVisible, licensed: isLicensed } = useAiFeatures();
    const { app_settings } = usePage<{ app_settings: import('@/types/models').AppSettings }>().props;
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
    const [showNormalize, setShowNormalize] = useState(false);
    const [isPaletteOpen, setIsPaletteOpen] = useState(false);
    const [isNotesOpen, setIsNotesOpen] = useState(() => {
        try {
            return localStorage.getItem('manuscript:notes-open') === 'true';
        } catch {
            return false;
        }
    });

    const toggleNotes = useCallback(() => {
        setIsNotesOpen((prev) => {
            const next = !prev;
            try {
                localStorage.setItem('manuscript:notes-open', String(next));
            } catch {}
            return next;
        });
    }, []);

    const closeNotes = useCallback(() => {
        setIsNotesOpen(false);
        try {
            localStorage.setItem('manuscript:notes-open', 'false');
        } catch {}
        activeEditorRef.current?.commands.focus();
    }, []);

    const [scenesVisible, setScenesVisible] = useState(app_settings.show_scenes);

    const handleScenesVisibleChange = useCallback((v: boolean) => {
        setScenesVisible(v);
        fetch('/settings', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getXsrfToken(), Accept: 'application/json' },
            body: JSON.stringify({ key: 'show_scenes', value: v }),
        }).then(() => router.reload({ only: ['app_settings'] }));
    }, []);

    const [isTypewriterMode, setIsTypewriterMode] = useState(app_settings.typewriter_mode);

    const toggleTypewriterMode = useCallback(() => {
        setIsTypewriterMode((prev) => {
            const next = !prev;
            fetch('/settings', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getXsrfToken(), Accept: 'application/json' },
                body: JSON.stringify({ key: 'typewriter_mode', value: next }),
            }).then(() => router.reload({ only: ['app_settings'] }));
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

    const [editorFontSize, setEditorFontSize] = useState(() => {
        try {
            const stored = localStorage.getItem('manuscript:editor-font-size');
            return stored ? Number(stored) : 18;
        } catch {
            return 18;
        }
    });

    const handleFontSizeChange = useCallback((size: number) => {
        setEditorFontSize(size);
        try {
            localStorage.setItem('manuscript:editor-font-size', String(size));
        } catch {
            // Ignore storage errors
        }
    }, []);

    const [isChatOpen, setIsChatOpen] = useState(false);

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

    useEffect(() => {
        setChapterNotes(chapter.notes);
    }, [chapter.id, chapter.notes]);

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

    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if (e.key === 'p' && (e.metaKey || e.ctrlKey)) {
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

        const { belowHtml, currentIndex } = await splitAtCursor(activeEditor, activeSceneId, scenes);
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

    const handleSplitChapter = useCallback(async () => {
        if (!activeEditor || !activeSceneId) return;

        const { belowHtml, currentIndex } = await splitAtCursor(activeEditor, activeSceneId, scenes);
        const subsequentSceneIds = scenes.slice(currentIndex + 1).map((s) => s.id);

        const response = await fetch(split.url({ book: book.id, chapter: chapter.id }), {
            method: 'POST',
            headers: jsonFetchHeaders(),
            body: JSON.stringify({
                title: t('palette.newChapter'),
                initial_content: belowHtml,
                scene_ids: subsequentSceneIds,
            }),
        });

        if (!response.ok) return;

        const data: { url: string } = await response.json();
        router.visit(data.url);
    }, [activeEditor, activeSceneId, book.id, chapter.id, scenes, t]);

    const handleNewChapter = useCallback(async () => {
        await handleBeforeNavigate();
        createChapter(book.id, chapter.storyline_id, sidebarStorylines);
    }, [book, chapter.storyline_id, handleBeforeNavigate, sidebarStorylines]);

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
                <Sidebar
                    book={book}
                    storylines={sidebarStorylines}
                    activeChapterId={chapter.id}
                    activeChapterTitle={displayTitle}
                    activeChapterWordCount={wordCount}
                    onBeforeNavigate={handleBeforeNavigate}
                    activeScenes={scenes}
                    onSceneRename={handleSidebarSceneRename}
                    onSceneDelete={handleSidebarSceneDelete}
                    onSceneReorder={handleSidebarSceneReorder}
                    onSceneAdd={handleAddScene}
                    scenesVisible={scenesVisible}
                    onScenesVisibleChange={handleScenesVisibleChange}
                    isFocusMode={isFocusMode}
                />

                <div className="relative flex min-w-0 flex-1 flex-col">
                    <div className="relative">
                        <div
                            className={`overflow-hidden transition-[height,opacity] duration-300 ${isFocusMode ? 'h-0 opacity-0' : 'h-[38px]'}`}
                        >
                            <EditorBar
                                chapter={chapter}
                                chapterTitle={displayTitle}
                                storylineName={chapter.storyline?.name ?? t('show.untitledStoryline')}
                                wordCount={wordCount}
                                versionCount={versionCount}
                                onVersionClick={() => setShowVersions(!showVersions)}
                            />
                        </div>
                        {showVersions && !isFocusMode && (
                            <VersionHistoryOverlay
                                bookId={book.id}
                                chapterId={chapter.id}
                                onClose={() => setShowVersions(false)}
                            />
                        )}
                    </div>

                    {pendingVersion && chapter.current_version ? (
                        <DiffView
                            bookId={book.id}
                            chapterId={chapter.id}
                            chapterTitle={displayTitle}
                            currentVersion={chapter.current_version}
                            pendingVersion={pendingVersion}
                            prosePassRules={prosePassRules}
                        />
                    ) : (
                        <>
                            <div
                                className={`transition-[height,opacity] duration-300 ${isFocusMode || app_settings.hide_formatting_toolbar ? 'h-0 overflow-hidden opacity-0' : 'h-[38px]'}`}
                            >
                                <FormattingToolbar
                                    editor={activeEditor}
                                    editorFont={editorFont}
                                    onFontChange={handleFontChange}
                                    editorFontSize={editorFontSize}
                                    onFontSizeChange={handleFontSizeChange}
                                    onToggleFocusMode={toggleFocusMode}
                                    onToggleNotes={toggleNotes}
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
                                editorFontSize={editorFontSize}
                                pendingFocusSceneId={pendingFocusSceneId}
                                onFocusHandled={() => setPendingFocusSceneId(null)}
                                onActiveSceneIdChange={setActiveSceneId}
                                scenesVisible={scenesVisible}
                            />

                            <CommandPalette
                                editor={activeEditor}
                                isOpen={isPaletteOpen}
                                onClose={closePalette}
                                onSplitScene={handleSplitScene}
                                onSplitChapter={handleSplitChapter}
                                onNewChapter={handleNewChapter}
                                onAddScene={handlePaletteAddScene}
                                onEnterFocusMode={toggleFocusMode}
                                isFocusMode={isFocusMode}
                                onToggleNotes={toggleNotes}
                                licensed={isLicensed}
                            />
                        </>
                    )}
                </div>

                {isNotesOpen && !isFocusMode && !pendingVersion && (
                    <NotesPanel
                        bookId={book.id}
                        chapterId={chapter.id}
                        initialNotes={chapterNotes}
                        onNotesChange={setChapterNotes}
                        onClose={closeNotes}
                    />
                )}

                {!pendingVersion && aiVisible && (
                    <div
                        className={`flex overflow-hidden transition-[width,opacity] duration-300 ${isFocusMode ? 'w-0 opacity-0' : ''}`}
                    >
                        <AiPanel
                            characters={(chapter.characters as (Character & { pivot: CharacterChapterPivot })[]) ?? []}
                            book={book}
                            chapter={chapter}
                            isOpen={isAiPanelOpen}
                            onToggle={toggleAiPanel}
                            onError={(msg) => { console.error('[AiPanel]', msg); setSaveStatus('error'); }}
                            onOpenChat={() => setIsChatOpen(true)}
                            chapterAnalyses={chapterAnalyses}
                        />
                        {isChatOpen && (
                            <AiChatDrawer book={book} chapter={chapter} onClose={() => setIsChatOpen(false)} />
                        )}
                    </div>
                )}
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
    const { t, i18n } = useTranslation('editor');
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
                {t('focusMode.chapterLabel', { number: chapterNumber, title: chapterTitle })}
            </span>
            <span className="absolute left-1/2 flex -translate-x-1/2 items-center gap-1.5 text-[13px] leading-4 tracking-[0.02em] text-ink-whisper">
                <Kbd keys="Esc" /> {t('focusMode.leaveFocusMode')}
            </span>
            <span className="text-[13px] leading-4 tracking-[0.02em] text-ink-whisper">
                {t('focusMode.wordCount', { count: wordCount, formatted: wordCount.toLocaleString(i18n.language) })}
            </span>
        </div>
    );
}
