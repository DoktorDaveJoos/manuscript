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
import { index as editorialReviewIndex } from '@/actions/App/Http/Controllers/EditorialReviewController';
import AccessBar from '@/components/editor/AccessBar';
import type {
    AccessBarItemConfig,
    PanelId,
} from '@/components/editor/AccessBar';
import AiChatDrawer from '@/components/editor/AiChatDrawer';
import AiPanel from '@/components/editor/AiPanel';
import ChapterPane from '@/components/editor/ChapterPane';
import type { SaveStatus } from '@/components/editor/ChapterPane';
import CommandPalette from '@/components/editor/CommandPalette';
import EditorialReviewPanel from '@/components/editor/EditorialReviewPanel';
import GlobalFindDrawer from '@/components/editor/GlobalFindDrawer';
import NotesPanel from '@/components/editor/NotesPanel';
import PaneEmptyState from '@/components/editor/PaneEmptyState';
import Sidebar from '@/components/editor/Sidebar';
import WikiPanel from '@/components/editor/WikiPanel';
import Kbd from '@/components/ui/Kbd';
import SlidePanel from '@/components/ui/SlidePanel';
import type { SearchHighlight } from '@/extensions/SearchHighlightExtension';
import { useAiFeatures } from '@/hooks/useAiFeatures';
import type { ChapterData } from '@/hooks/useChapterData';
import useChapterData from '@/hooks/useChapterData';
import usePaneManager from '@/hooks/usePaneManager';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import { createChapter } from '@/lib/utils';
import type {
    AppSettings,
    Book,
    Character,
    CharacterChapterPivot,
} from '@/types/models';

// ─── Constants ───────────────────────────────────────────────────────────────

const VALID_PANELS: Set<PanelId> = new Set([
    'wiki',
    'notes',
    'ai',
    'chat',
    'editorial',
]);

// ─── PaneWithData wrapper ────────────────────────────────────────────────────

function PaneWithData({
    bookId,
    bookLanguage,
    chapterId,
    appSettings,
    isFocused,
    isFocusMode,
    onFocus,
    onClose,
    onActiveEditorChange,
    onSaveStatusChange,
    onChapterDataReady,
}: {
    bookId: number;
    bookLanguage: string;
    chapterId: number;
    appSettings: AppSettings;
    isFocused: boolean;
    isFocusMode: boolean;
    onFocus: () => void;
    onClose: () => void;
    onActiveEditorChange: (
        editor: Editor | null,
        sceneId: number | null,
    ) => void;
    onSaveStatusChange: (status: SaveStatus) => void;
    onChapterDataReady: (data: ChapterData) => void;
}) {
    const { data, isLoading, error } = useChapterData(bookId, chapterId);

    // When data loads and this pane is focused, notify parent
    useEffect(() => {
        if (isFocused && data) {
            onChapterDataReady(data);
        }
    }, [isFocused, data, onChapterDataReady]);

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
            bookLanguage={bookLanguage}
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

// ─── EditorPage ──────────────────────────────────────────────────────────────

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
    const sidebarStorylines = useSidebarStorylines();
    const { visible: aiVisible } = useAiFeatures();
    const { app_settings } = usePage<{ app_settings: AppSettings }>().props;

    // ── Pane management ──────────────────────────────────────────────────
    const {
        panes,
        focusedPaneId,
        focusedPane,
        setFocusedPaneId,
        openInNewPane,
        navigateToChapter,
        closePane,
    } = usePaneManager(book.id, initialPanes, fallbackChapterId ?? undefined);

    // ── Active editor tracking (from focused pane) ───────────────────────
    const [activeEditor, setActiveEditor] = useState<Editor | null>(null);
    const [activeSceneId, setActiveSceneId] = useState<number | null>(null);
    const activeEditorRef = useRef<Editor | null>(null);
    activeEditorRef.current = activeEditor;

    const handleActiveEditorChange = useCallback(
        (editor: Editor | null, sceneId: number | null) => {
            setActiveEditor(editor);
            setActiveSceneId(sceneId);
        },
        [],
    );

    // ── Focused chapter data (for panels) ────────────────────────────────
    const [focusedChapterData, setFocusedChapterData] =
        useState<ChapterData | null>(null);

    const handleChapterDataReady = useCallback((data: ChapterData) => {
        setFocusedChapterData(data);
    }, []);

    const focusedChapterId = focusedPane?.chapterId ?? null;

    // ── Save status (worst-case-wins) ────────────────────────────────────
    const paneStatusesRef = useRef<Map<string, SaveStatus>>(new Map());
    const [combinedSaveStatus, setCombinedSaveStatus] =
        useState<SaveStatus>('saved');

    const updateCombinedStatus = useCallback(() => {
        const statuses = Array.from(paneStatusesRef.current.values());
        if (statuses.includes('error')) setCombinedSaveStatus('error');
        else if (statuses.includes('unsaved')) setCombinedSaveStatus('unsaved');
        else if (statuses.includes('saving')) setCombinedSaveStatus('saving');
        else setCombinedSaveStatus('saved');
    }, []);

    const makeSaveStatusHandler = useCallback(
        (paneId: string) => (status: SaveStatus) => {
            paneStatusesRef.current.set(paneId, status);
            updateCombinedStatus();
        },
        [updateCombinedStatus],
    );

    // Clean up statuses for removed panes
    useEffect(() => {
        const currentIds = new Set(panes.map((p) => p.id));
        for (const key of paneStatusesRef.current.keys()) {
            if (!currentIds.has(key)) {
                paneStatusesRef.current.delete(key);
            }
        }
        updateCombinedStatus();
    }, [panes, updateCombinedStatus]);

    // ── Flush all panes ──────────────────────────────────────────────────
    const flushAllPanes = useCallback(async () => {
        const paneEls = document.querySelectorAll('[data-pane-chapter]');
        const flushes = Array.from(paneEls).map((el) => {
            const flush = (el as unknown as Record<string, () => Promise<void>>)
                .__flushPane;
            return typeof flush === 'function' ? flush() : Promise.resolve();
        });
        await Promise.all(flushes);
    }, []);

    // Flush before Inertia navigation
    useEffect(() => {
        const removeListener = router.on('before', () => {
            flushAllPanes();
        });
        return removeListener;
    }, [flushAllPanes]);

    // Flush on beforeunload
    useEffect(() => {
        const handler = () => {
            flushAllPanes();
        };
        window.addEventListener('beforeunload', handler);
        return () => window.removeEventListener('beforeunload', handler);
    }, [flushAllPanes]);

    // ── Panel state ──────────────────────────────────────────────────────
    const [openPanels, setOpenPanels] = useState<Set<PanelId>>(() => {
        try {
            const stored = localStorage.getItem('manuscript:open-panels');
            if (!stored) return new Set();
            const parsed: unknown = JSON.parse(stored);
            if (!Array.isArray(parsed)) return new Set();
            return new Set(
                parsed.filter((p): p is PanelId => VALID_PANELS.has(p)),
            );
        } catch {
            return new Set();
        }
    });

    useEffect(() => {
        try {
            localStorage.setItem(
                'manuscript:open-panels',
                JSON.stringify([...openPanels]),
            );
        } catch {
            /* no-op */
        }
    }, [openPanels]);

    const togglePanel = useCallback((panel: PanelId) => {
        setOpenPanels((prev) => {
            const next = new Set(prev);
            if (next.has(panel)) {
                next.delete(panel);
            } else {
                next.add(panel);
            }
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

    const closeWiki = useCallback(
        () => closePanelAndFocus('wiki'),
        [closePanelAndFocus],
    );
    const closeNotes = useCallback(
        () => closePanelAndFocus('notes'),
        [closePanelAndFocus],
    );
    const closeAi = useCallback(
        () => closePanelAndFocus('ai'),
        [closePanelAndFocus],
    );
    const closeChat = useCallback(
        () => closePanelAndFocus('chat'),
        [closePanelAndFocus],
    );
    const closeEditorial = useCallback(
        () => closePanelAndFocus('editorial'),
        [closePanelAndFocus],
    );

    // ── Focus mode ───────────────────────────────────────────────────────
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
            } catch {
                /* no-op */
            }
            if (next) {
                document.documentElement.requestFullscreen?.().catch(() => {});
            } else {
                if (document.fullscreenElement)
                    document.exitFullscreen?.().catch(() => {});
            }
            return next;
        });
    }, []);

    const exitFocusMode = useCallback(() => {
        setIsFocusMode(false);
        try {
            localStorage.setItem('manuscript:focus-mode', 'false');
        } catch {
            /* no-op */
        }
        if (document.fullscreenElement)
            document.exitFullscreen?.().catch(() => {});
    }, []);

    useEffect(() => {
        const onFullscreenChange = () => {
            if (!document.fullscreenElement && isFocusMode) {
                setIsFocusMode(false);
                try {
                    localStorage.setItem('manuscript:focus-mode', 'false');
                } catch {
                    /* no-op */
                }
            }
        };
        document.addEventListener('fullscreenchange', onFullscreenChange);
        return () =>
            document.removeEventListener(
                'fullscreenchange',
                onFullscreenChange,
            );
    }, [isFocusMode]);

    // ── Find / Replace ───────────────────────────────────────────────────
    const [isFindOpen, setIsFindOpen] = useState(false);
    const [findShowReplace, setFindShowReplace] = useState(false);
    const [searchHighlight, setSearchHighlight] =
        useState<SearchHighlight | null>(null);
    const [isLocalFindOpen, setIsLocalFindOpen] = useState(false);
    const [localFindShowReplace, setLocalFindShowReplace] = useState(false);

    // ── Command palette ──────────────────────────────────────────────────
    const [isPaletteOpen, setIsPaletteOpen] = useState(false);
    const closePalette = useCallback(() => setIsPaletteOpen(false), []);

    // ── Keyboard shortcuts ───────────────────────────────────────────────
    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if (e.key === 'p' && (e.metaKey || e.ctrlKey)) {
                e.preventDefault();
                setIsPaletteOpen((prev) => !prev);
            } else if (
                e.key === 'f' &&
                (e.metaKey || e.ctrlKey) &&
                e.shiftKey
            ) {
                e.preventDefault();
                setIsLocalFindOpen(false);
                setIsFindOpen(true);
                setFindShowReplace(false);
            } else if (
                e.key === 'f' &&
                (e.metaKey || e.ctrlKey) &&
                !e.shiftKey
            ) {
                e.preventDefault();
                setIsFindOpen(false);
                setSearchHighlight(null);
                setIsLocalFindOpen(true);
                setLocalFindShowReplace(false);
            } else if (
                e.key === 'r' &&
                (e.metaKey || e.ctrlKey) &&
                e.shiftKey
            ) {
                e.preventDefault();
                setIsLocalFindOpen(false);
                setIsFindOpen(true);
                setFindShowReplace(true);
            } else if (
                e.key === 'h' &&
                (e.metaKey || e.ctrlKey) &&
                !e.shiftKey
            ) {
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
        return () =>
            document.removeEventListener('keydown', handler, { capture: true });
    }, [isFocusMode, isPaletteOpen, exitFocusMode]);

    // ── AccessBar items ──────────────────────────────────────────────────
    const accessBarItems = useMemo(() => {
        const items: AccessBarItemConfig[] = [
            {
                id: 'wiki',
                icon: BookOpen,
                label: t('toolbar.wiki'),
            },
            {
                id: 'notes',
                icon: NotebookPen,
                label: t('toolbar.notes'),
            },
        ];
        if (aiVisible) {
            items.push(
                {
                    id: 'ai',
                    icon: Sparkles,
                    label: tAi('headerTitle'),
                },
                {
                    id: 'chat',
                    icon: MessageCircle,
                    label: tAi('askAi'),
                },
                {
                    id: 'editorial',
                    icon: NotebookText,
                    label: tEditorial('panel.title'),
                },
            );
        }
        return items;
    }, [aiVisible, t, tAi, tEditorial]);

    // ── Sidebar callbacks ────────────────────────────────────────────────
    const handleBeforeNavigate = useCallback(async () => {
        await flushAllPanes();
    }, [flushAllPanes]);

    // Derive sidebar-visible info from focused chapter data
    const focusedChapter = focusedChapterData?.chapter ?? null;
    const focusedScenes = focusedChapter?.scenes ?? [];
    const focusedWordCount = focusedScenes.reduce(
        (sum, s) => sum + s.word_count,
        0,
    );
    const focusedDisplayTitle = focusedChapter
        ? focusedChapter.title.split('\n')[0]
        : '';

    // ── Create chapter from empty state / palette ────────────────────────
    const handleCreateChapter = useCallback(() => {
        const firstStoryline = sidebarStorylines[0];
        if (firstStoryline) {
            createChapter(book.id, firstStoryline.id, sidebarStorylines);
        }
    }, [book.id, sidebarStorylines]);

    // ── Find navigate ────────────────────────────────────────────────────
    const handleFindNavigate = useCallback(
        async (chapterId: number, _sceneId: number) => {
            // Navigate to chapter in current focused pane
            navigateToChapter(chapterId);
        },
        [navigateToChapter],
    );

    // ── Page title ───────────────────────────────────────────────────────
    const pageTitle = focusedDisplayTitle
        ? `${focusedDisplayTitle} — ${book.title}`
        : book.title;

    return (
        <>
            <Head title={pageTitle} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar
                    book={book}
                    storylines={sidebarStorylines}
                    activeChapterId={focusedChapterId ?? undefined}
                    activeChapterTitle={focusedDisplayTitle || undefined}
                    activeChapterWordCount={focusedWordCount}
                    onBeforeNavigate={handleBeforeNavigate}
                    onChapterNavigate={navigateToChapter}
                    onOpenInNewPane={openInNewPane}
                    activeScenes={focusedScenes}
                    onChapterRename={() => {}}
                    onSceneRename={() => {}}
                    onSceneDelete={() => {}}
                    onSceneReorder={() => {}}
                    onSceneAdd={async () => {}}
                    scenesVisible={app_settings.show_scenes}
                    isFocusMode={isFocusMode}
                />

                {/* Pane area */}
                <div className="relative flex min-w-0 flex-1">
                    {panes.length === 0 ? (
                        <PaneEmptyState onCreateChapter={handleCreateChapter} />
                    ) : (
                        panes.map((pane, index) => (
                            <div key={pane.id} className="flex min-w-0 flex-1">
                                {index > 0 && (
                                    <div className="w-px shrink-0 bg-border-light" />
                                )}
                                <PaneWithData
                                    bookId={book.id}
                                    bookLanguage={book.language}
                                    chapterId={pane.chapterId}
                                    appSettings={app_settings}
                                    isFocused={pane.id === focusedPaneId}
                                    isFocusMode={isFocusMode}
                                    onFocus={() => setFocusedPaneId(pane.id)}
                                    onClose={() => closePane(pane.id)}
                                    onActiveEditorChange={
                                        pane.id === focusedPaneId
                                            ? handleActiveEditorChange
                                            : () => {}
                                    }
                                    onSaveStatusChange={makeSaveStatusHandler(
                                        pane.id,
                                    )}
                                    onChapterDataReady={handleChapterDataReady}
                                />
                            </div>
                        ))
                    )}
                </div>

                {/* Right-side panels — scoped to focused pane's chapter */}
                {!isFocusMode && (
                    <>
                        {focusedChapter && (
                            <SlidePanel
                                open={openPanels.has('wiki')}
                                onClose={closeWiki}
                                storageKey="manuscript:wiki-panel-width"
                                defaultWidth={300}
                                minWidth={200}
                                maxWidth={600}
                            >
                                <WikiPanel
                                    book={book}
                                    chapter={focusedChapter}
                                    onClose={closeWiki}
                                />
                            </SlidePanel>
                        )}

                        {focusedChapter && (
                            <SlidePanel
                                open={openPanels.has('notes')}
                                onClose={closeNotes}
                                storageKey="manuscript:notes-width"
                                defaultWidth={260}
                            >
                                <NotesPanel
                                    key={focusedChapter.id}
                                    bookId={book.id}
                                    chapterId={focusedChapter.id}
                                    initialNotes={focusedChapter.notes}
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
                                onNavigate={handleFindNavigate}
                                onSearchChange={setSearchHighlight}
                                showReplace={findShowReplace}
                            />
                        )}

                        {focusedChapter && (
                            <SlidePanel
                                open={openPanels.has('ai') && aiVisible}
                                onClose={closeAi}
                                storageKey="manuscript:ai-panel-width"
                                defaultWidth={272}
                            >
                                <AiPanel
                                    characters={
                                        (focusedChapter.characters as
                                            | (Character & {
                                                  pivot: CharacterChapterPivot;
                                              })[]
                                            | undefined) ?? []
                                    }
                                    book={book}
                                    chapter={focusedChapter}
                                    onClose={closeAi}
                                    onError={(msg) => {
                                        console.error('[AiPanel]', msg);
                                    }}
                                />
                            </SlidePanel>
                        )}

                        {focusedChapter && (
                            <SlidePanel
                                open={openPanels.has('chat') && aiVisible}
                                onClose={closeChat}
                                storageKey="manuscript:chat-width"
                                defaultWidth={320}
                                maxWidth={700}
                            >
                                <AiChatDrawer
                                    book={book}
                                    chapter={focusedChapter}
                                    onClose={closeChat}
                                />
                            </SlidePanel>
                        )}

                        <SlidePanel
                            open={openPanels.has('editorial') && aiVisible}
                            onClose={closeEditorial}
                            storageKey="manuscript:editorial-panel-width"
                            defaultWidth={280}
                        >
                            <EditorialReviewPanel
                                chapterNote={null}
                                editorialReviewUrl={editorialReviewIndex.url(
                                    book,
                                )}
                                onClose={closeEditorial}
                            />
                        </SlidePanel>

                        <AccessBar
                            items={accessBarItems}
                            openPanels={openPanels}
                            onToggle={togglePanel}
                        />
                    </>
                )}
            </div>

            {/* Command palette */}
            <CommandPalette
                editor={activeEditor}
                isOpen={isPaletteOpen}
                onClose={closePalette}
                onSplitScene={async () => {}}
                onSplitChapter={async () => {}}
                onNewChapter={handleCreateChapter}
                onEnterFocusMode={toggleFocusMode}
                isFocusMode={isFocusMode}
                panelItems={accessBarItems}
                openPanels={openPanels}
                onTogglePanel={togglePanel}
            />

            {/* Focus mode whisper chrome */}
            {isFocusMode && focusedChapter && (
                <WhisperChrome
                    chapterNumber={focusedChapter.reader_order}
                    chapterTitle={focusedDisplayTitle}
                    wordCount={focusedWordCount}
                />
            )}
        </>
    );
}

// ─── WhisperChrome (focus mode overlay) ──────────────────────────────────────

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
                {t('focusMode.chapterLabel', {
                    number: chapterNumber,
                    title: chapterTitle,
                })}
            </span>
            <span className="absolute left-1/2 flex -translate-x-1/2 items-center gap-1.5 text-[13px] leading-4 tracking-[0.02em] text-ink-whisper">
                <Kbd keys="Esc" /> {t('focusMode.leaveFocusMode')}
            </span>
            <span className="text-[13px] leading-4 tracking-[0.02em] text-ink-whisper">
                {t('focusMode.wordCount', {
                    count: wordCount,
                    formatted: wordCount.toLocaleString(i18n.language),
                })}
            </span>
        </div>
    );
}
