import { Head, router, usePage } from '@inertiajs/react';
import type { Editor } from '@tiptap/react';
import {
    BookOpen,
    MessageCircle,
    NotebookPen,
    Sparkles,
    Workflow,
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
import ChapterPane, { firstLine } from '@/components/editor/ChapterPane';
import type { SaveStatus } from '@/components/editor/ChapterPane';
import CommandPalette from '@/components/editor/CommandPalette';
import ContinueWritingDialog, {
    defaultContinueWritingDraft,
} from '@/components/editor/ContinueWritingDialog';
import type { ContinueWritingDraft } from '@/components/editor/ContinueWritingDialog';
import GlobalFindDrawer from '@/components/editor/GlobalFindDrawer';
import NotesPanel from '@/components/editor/NotesPanel';
import PaneEmptyState from '@/components/editor/PaneEmptyState';
import PlotPanel from '@/components/editor/PlotPanel';
import RewriteSelectionDialog, {
    defaultRewriteSelectionDraft,
} from '@/components/editor/RewriteSelectionDialog';
import type { RewriteSelectionDraft } from '@/components/editor/RewriteSelectionDialog';
import Sidebar from '@/components/editor/Sidebar';
import WikiPanel from '@/components/editor/WikiPanel';
import WritingStyleSetupDialog from '@/components/editor/WritingStyleSetupDialog';
import Button from '@/components/ui/Button';
import Kbd from '@/components/ui/Kbd';
import SlidePanel from '@/components/ui/SlidePanel';
import type { SearchHighlight } from '@/extensions/SearchHighlightExtension';
import { useAiFeatures } from '@/hooks/useAiFeatures';
import type { ChapterData } from '@/hooks/useChapterData';
import useChapterData from '@/hooks/useChapterData';
import type { ContinueWritingReview } from '@/hooks/useContinueWriting';
import { useContinueWriting } from '@/hooks/useContinueWriting';
import usePaneManager from '@/hooks/usePaneManager';
import type { RewriteSelectionReview } from '@/hooks/useRewriteSelection';
import { useRewriteSelection } from '@/hooks/useRewriteSelection';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import { flushAllPanes } from '@/lib/pane';
import {
    addSceneToChapter,
    CHANNEL_CHAPTER_DATA_CHANGED,
    CHANNEL_DIFF_APPLIED,
    createChapter,
    jsonFetchHeaders,
    saveAppSetting,
} from '@/lib/utils';
import type { AppSettings, Book } from '@/types/models';

// ─── Constants ───────────────────────────────────────────────────────────────

const NOOP_EDITOR_CHANGE = () => {};

function useChapterRefreshChannel(
    channelName: string,
    chapterId: number,
    softRefresh: () => void,
) {
    useEffect(() => {
        if (typeof BroadcastChannel === 'undefined') return;
        const channel = new BroadcastChannel(channelName);
        channel.onmessage = (event) => {
            if (event.data?.chapterId === chapterId) softRefresh();
        };
        return () => channel.close();
    }, [channelName, chapterId, softRefresh]);
}

const VALID_PANELS: Set<PanelId> = new Set([
    'wiki',
    'notes',
    'plot',
    'ai',
    'chat',
]);

// ─── PaneWithData wrapper ────────────────────────────────────────────────────

function PaneWithData({
    bookId,
    bookLanguage,
    chapterId,
    appSettings,
    isFocused,
    isFocusMode,
    paneId,
    setFocusedPaneId,
    closePane,
    onActiveEditorChange,
    onSaveStatusChange,
    onChapterDataReady,
    onChapterMetaChange,
    scenesVisible,
    spellcheckEnabled,
    isTypewriterMode,
    onToggleTypewriterMode,
    review,
    onReviewDismiss,
    proseRunning,
    editorLocked,
    isLocalFindOpen,
    localFindShowReplace,
    onLocalFindClose,
}: {
    bookId: number;
    bookLanguage: string;
    chapterId: number;
    appSettings: AppSettings;
    isFocused: boolean;
    isFocusMode: boolean;
    paneId: string;
    setFocusedPaneId: (id: string) => void;
    closePane: (id: string) => void;
    onActiveEditorChange: (
        editor: Editor | null,
        sceneId: number | null,
    ) => void;
    onSaveStatusChange: (status: SaveStatus) => void;
    onChapterDataReady: (data: ChapterData) => void;
    onChapterMetaChange: (meta: {
        chapterId: number;
        title: string;
        wordCount: number;
    }) => void;
    scenesVisible: boolean;
    spellcheckEnabled: boolean;
    isTypewriterMode: boolean;
    onToggleTypewriterMode: () => void;
    review: ContinueWritingReview | RewriteSelectionReview | null;
    onReviewDismiss: () => void;
    proseRunning: boolean;
    editorLocked: boolean;
    isLocalFindOpen: boolean;
    localFindShowReplace: boolean;
    onLocalFindClose: () => void;
}) {
    const { data, isLoading, error, refresh, softRefresh } = useChapterData(
        bookId,
        chapterId,
    );

    const handleReviewApplied = useCallback(() => {
        onReviewDismiss();
        softRefresh();
    }, [onReviewDismiss, softRefresh]);

    // Refresh on cross-window diff-applied (from the diff window) and on
    // local chapter-data changes (e.g. scene added via palette).
    useChapterRefreshChannel(CHANNEL_DIFF_APPLIED, chapterId, softRefresh);
    useChapterRefreshChannel(
        CHANNEL_CHAPTER_DATA_CHANGED,
        chapterId,
        softRefresh,
    );

    const onFocus = useCallback(
        () => setFocusedPaneId(paneId),
        [paneId, setFocusedPaneId],
    );
    const onClose = useCallback(() => closePane(paneId), [paneId, closePane]);

    useEffect(() => {
        if (isFocused && data) {
            onChapterDataReady(data);
        }
    }, [isFocused, data, onChapterDataReady]);

    const prevFocusedRef = useRef(isFocused);
    useEffect(() => {
        if (isFocused && !prevFocusedRef.current) {
            softRefresh();
        }
        prevFocusedRef.current = isFocused;
    }, [isFocused, softRefresh]);

    if (isLoading || !data) {
        return (
            <div className="flex min-w-0 flex-1 items-center justify-center">
                <div className="size-5 animate-spin rounded-full border-2 border-ink-faint border-t-transparent" />
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex min-w-0 flex-1 flex-col items-center justify-center gap-3 text-sm text-ink-muted">
                Failed to load chapter
                <Button variant="secondary" size="sm" onClick={refresh}>
                    Try again
                </Button>
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
            onChapterMetaChange={onChapterMetaChange}
            onSaveStatusChange={onSaveStatusChange}
            onVersionsChanged={softRefresh}
            scenesVisible={scenesVisible}
            spellcheckEnabled={spellcheckEnabled}
            isTypewriterMode={isTypewriterMode}
            onToggleTypewriterMode={onToggleTypewriterMode}
            review={review}
            onReviewApplied={handleReviewApplied}
            proseRunning={proseRunning}
            editorLocked={editorLocked}
            isLocalFindOpen={isLocalFindOpen}
            localFindShowReplace={localFindShowReplace}
            onLocalFindClose={onLocalFindClose}
        />
    );
}

// ─── EditorPage ──────────────────────────────────────────────────────────────

export default function EditorPage({
    book,
    initialPanes,
    fallbackChapterId,
    writingStylePromptable = false,
}: {
    book: Book;
    initialPanes: string | null;
    fallbackChapterId: number | null;
    writingStylePromptable?: boolean;
}) {
    const { t } = useTranslation('editor');
    const { t: tAi } = useTranslation('ai');
    const { t: tPlotPanel } = useTranslation('plot-panel');
    const sidebarStorylines = useSidebarStorylines();
    const { usable: aiVisible } = useAiFeatures();
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

    // ── Spell check toggle ─────────────────────────────────────────────────
    const [isSpellcheckEnabled, setIsSpellcheckEnabled] = useState(true);
    const toggleSpellcheck = useCallback(
        () => setIsSpellcheckEnabled((prev) => !prev),
        [],
    );

    // ── Typewriter mode toggle (shared across panes) ───────────────────────
    const [isTypewriterMode, setIsTypewriterMode] = useState(
        app_settings.typewriter_mode,
    );
    const toggleTypewriterMode = useCallback(
        () => setIsTypewriterMode((prev) => !prev),
        [],
    );

    // ── Scenes visibility ───────────────────────────────────────────────
    const [scenesVisible, setScenesVisible] = useState(
        app_settings.show_scenes,
    );
    const handleScenesVisibleChange = useCallback((visible: boolean) => {
        setScenesVisible(visible);
        saveAppSetting('show_scenes', visible);
    }, []);

    // ── Prose revise UI state (drives the editor blur overlay only; the
    // post-run "Compare" toast is fired from AiPanel via sonner) ─────────
    const [proseRunningChapterId, setProseRunningChapterId] = useState<
        number | null
    >(null);
    const handleProseStart = useCallback(
        (chapterId: number) => setProseRunningChapterId(chapterId),
        [],
    );
    const handleProseEnd = useCallback(
        () => setProseRunningChapterId(null),
        [],
    );

    // Continue writing / rewrite selection stream INTO the editor, so they
    // must stay visible (no blur overlay) — but user input still has to be
    // rejected or keystrokes interleave with the stream and get committed.
    const [streamingChapterId, setStreamingChapterId] = useState<number | null>(
        null,
    );

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
    const [liveMeta, setLiveMeta] = useState<{
        chapterId: number;
        title: string;
        wordCount: number;
    } | null>(null);

    const handleChapterDataReady = useCallback((data: ChapterData) => {
        setFocusedChapterData(data);
    }, []);

    const handleChapterMetaChange = useCallback(
        (meta: { chapterId: number; title: string; wordCount: number }) => {
            setLiveMeta(meta);
        },
        [],
    );

    const handleNotesChange = useCallback((notes: string | null) => {
        setFocusedChapterData((prev) => {
            if (!prev || prev.chapter.notes === notes) return prev;
            return { ...prev, chapter: { ...prev.chapter, notes } };
        });
    }, []);

    const focusedChapterId = focusedPane?.chapterId ?? null;

    // ── Save status (per-pane tracking) ────────────────────────────────
    const saveStatusHandlersRef = useRef<
        Map<string, (status: SaveStatus) => void>
    >(new Map());
    const getSaveStatusHandler = useCallback((paneId: string) => {
        let handler = saveStatusHandlersRef.current.get(paneId);
        if (!handler) {
            handler = () => {};
            saveStatusHandlersRef.current.set(paneId, handler);
        }
        return handler;
    }, []);

    // Clean up cached handlers for removed panes
    useEffect(() => {
        const currentIds = new Set(panes.map((p) => p.id));
        for (const key of saveStatusHandlersRef.current.keys()) {
            if (!currentIds.has(key)) {
                saveStatusHandlersRef.current.delete(key);
            }
        }
    }, [panes]);

    // Flush before Inertia navigation
    useEffect(() => {
        const removeListener = router.on('before', () => {
            flushAllPanes();
        });
        return removeListener;
    }, []);

    // Flush on beforeunload — fire keepalive fetches as a last-resort safety net
    // (the browser may kill the page before async flushAllPanes completes)
    useEffect(() => {
        const handler = () => {
            // Best-effort async flush (may not complete before unload)
            flushAllPanes();

            // Keepalive fetches survive page teardown (64KB body limit per request)
            const paneEls = document.querySelectorAll('[data-pane-chapter]');
            paneEls.forEach((el) => {
                const getPendingAll = (
                    el as unknown as Record<
                        string,
                        () => {
                            url: string;
                            content: string;
                            expectedCurrentVersionId?: number | null;
                        }[]
                    >
                ).__getPendingAll;
                if (typeof getPendingAll !== 'function') return;
                for (const {
                    url,
                    content,
                    expectedCurrentVersionId,
                } of getPendingAll()) {
                    try {
                        fetch(url, {
                            method: 'PUT',
                            headers: jsonFetchHeaders(),
                            body: JSON.stringify({
                                content,
                                expected_current_version_id:
                                    expectedCurrentVersionId ?? null,
                            }),
                            keepalive: true,
                        });
                    } catch {
                        // Best effort — nothing more we can do during unload
                    }
                }
            });
        };
        window.addEventListener('beforeunload', handler);
        return () => window.removeEventListener('beforeunload', handler);
    }, []);

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
    const closePlot = useCallback(
        () => closePanelAndFocus('plot'),
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
    const [, setSearchHighlight] = useState<SearchHighlight | null>(null);
    const [isLocalFindOpen, setIsLocalFindOpen] = useState(false);
    const [localFindShowReplace, setLocalFindShowReplace] = useState(false);
    const closeLocalFind = useCallback(() => {
        setIsLocalFindOpen(false);
        setLocalFindShowReplace(false);
    }, []);

    // ── Writing style pre-flight gate ────────────────────────────────────
    // Prose-generating features (continue writing, rewrite, revise) first
    // offer to derive the book's writing style when none exists yet. The
    // pending action runs after the dialog resolves, whichever way.
    const [stylePromptable, setStylePromptable] = useState(
        writingStylePromptable,
    );
    const [pendingStyleGateAction, setPendingStyleGateAction] = useState<
        (() => void) | null
    >(null);
    const gateWritingStyle = useCallback(
        (action: () => void) => {
            if (stylePromptable) {
                setPendingStyleGateAction(() => action);
            } else {
                action();
            }
        },
        [stylePromptable],
    );

    // ── Continue writing ─────────────────────────────────────────────────
    const [isContinueWritingOpen, setIsContinueWritingOpen] = useState(false);
    // Draft survives closing the dialog (research, then come back) — it only
    // resets on submit, explicit reset, or switching chapters.
    const [continueWritingDraft, setContinueWritingDraft] =
        useState<ContinueWritingDraft>(defaultContinueWritingDraft);
    const continueWriting = useContinueWriting();

    useEffect(() => {
        setContinueWritingDraft(defaultContinueWritingDraft);
    }, [focusedChapterId]);

    // ── Rewrite selection ────────────────────────────────────────────────
    const [rewriteRange, setRewriteRange] = useState<{
        from: number;
        to: number;
    } | null>(null);
    // Draft survives closing the dialog (research, then come back) — it only
    // resets on submit, explicit reset, or switching chapters.
    const [rewriteDraft, setRewriteDraft] = useState<RewriteSelectionDraft>(
        defaultRewriteSelectionDraft,
    );
    const rewriteSelection = useRewriteSelection();

    useEffect(() => {
        setRewriteDraft(defaultRewriteSelectionDraft);
    }, [focusedChapterId]);

    const reviewForChapter = useCallback(
        (chapterId: number) => {
            for (const r of [rewriteSelection.review, continueWriting.review]) {
                if (r && r.chapterId === chapterId) return r;
            }
            return null;
        },
        [rewriteSelection.review, continueWriting.review],
    );
    const dismissAllReviews = useCallback(() => {
        continueWriting.dismissReview();
        rewriteSelection.dismissReview();
    }, [continueWriting, rewriteSelection]);

    // Dismiss per-chapter review banners once the diff window applied.
    useEffect(() => {
        if (typeof BroadcastChannel === 'undefined') return;
        const channel = new BroadcastChannel(CHANNEL_DIFF_APPLIED);
        channel.onmessage = () => dismissAllReviews();
        return () => channel.close();
    }, [dismissAllReviews]);

    // ── Command palette ──────────────────────────────────────────────────
    const [isPaletteOpen, setIsPaletteOpen] = useState(false);
    const closePalette = useCallback(() => {
        setIsPaletteOpen(false);
        // Return focus to the active editor at its prior selection so the
        // user can keep typing without clicking back into the canvas.
        requestAnimationFrame(() => {
            activeEditorRef.current?.commands.focus();
        });
    }, []);

    // ── Keyboard shortcuts ───────────────────────────────────────────────
    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if (e.key === 'p' && (e.metaKey || e.ctrlKey)) {
                e.preventDefault();
                if (isPaletteOpen) {
                    closePalette();
                } else {
                    setIsPaletteOpen(true);
                }
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
    }, [isFocusMode, isPaletteOpen, closePalette, exitFocusMode]);

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
        items.push({
            id: 'plot',
            icon: Workflow,
            label: tPlotPanel('headerTitle'),
        });
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
            );
        }
        return items;
    }, [aiVisible, t, tAi, tPlotPanel]);

    // ── Sidebar callbacks ────────────────────────────────────────────────
    const handleBeforeNavigate = useCallback(async () => {
        await flushAllPanes();
    }, []);

    // Derive sidebar-visible info from focused chapter data
    const focusedChapter = focusedChapterData?.chapter ?? null;
    const focusedScenes = focusedChapter?.scenes ?? [];
    const focusedWordCount = focusedScenes.reduce(
        (sum, s) => sum + s.word_count,
        0,
    );
    const focusedDisplayTitle = focusedChapter
        ? firstLine(focusedChapter.title)
        : '';
    const sidebarTitle =
        liveMeta?.chapterId === focusedChapterId
            ? liveMeta.title || undefined
            : focusedDisplayTitle || undefined;
    const sidebarWordCount =
        liveMeta?.chapterId === focusedChapterId
            ? liveMeta.wordCount
            : focusedWordCount;

    // ── Create chapter from empty state / palette ────────────────────────
    const handleCreateChapter = useCallback(() => {
        const storylineId =
            focusedChapter?.storyline_id ?? sidebarStorylines[0]?.id;
        if (storylineId) {
            createChapter(book.id, storylineId, sidebarStorylines);
        }
    }, [book.id, focusedChapter?.storyline_id, sidebarStorylines]);

    // ── Create scene in focused chapter from palette ────────────────────
    const focusedSceneCount = focusedScenes.length;
    const handleAddScene = useCallback(async () => {
        if (!focusedChapter) return;
        await addSceneToChapter(
            book.id,
            focusedChapter.id,
            focusedSceneCount,
            t,
        );
    }, [book.id, focusedChapter, focusedSceneCount, t]);

    // ── Find navigate ────────────────────────────────────────────────────
    const handleFindNavigate = useCallback(
        async (chapterId: number, _sceneId: number) => {
            // Navigate to chapter in current focused pane
            navigateToChapter(chapterId);
        },
        [navigateToChapter],
    );

    // ── Page title ───────────────────────────────────────────────────────
    const pageTitle = sidebarTitle
        ? `${sidebarTitle} — ${book.title}`
        : book.title;

    return (
        <>
            <Head title={pageTitle} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar
                    book={book}
                    storylines={sidebarStorylines}
                    activeChapterId={focusedChapterId ?? undefined}
                    activeChapterTitle={sidebarTitle}
                    activeChapterWordCount={sidebarWordCount}
                    onBeforeNavigate={handleBeforeNavigate}
                    onChapterNavigate={navigateToChapter}
                    onOpenInNewPane={openInNewPane}
                    activeScenes={focusedScenes}
                    scenesVisible={scenesVisible}
                    onScenesVisibleChange={handleScenesVisibleChange}
                    isFocusMode={isFocusMode}
                />

                {/* Pane area */}
                <div className="relative flex min-w-0 flex-1">
                    {panes.length === 0 ? (
                        <PaneEmptyState onCreateChapter={handleCreateChapter} />
                    ) : (
                        panes.map((pane, index) => (
                            <div
                                key={pane.id}
                                className="flex min-w-0 flex-1 overflow-hidden"
                            >
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
                                    paneId={pane.id}
                                    setFocusedPaneId={setFocusedPaneId}
                                    closePane={closePane}
                                    onActiveEditorChange={
                                        pane.id === focusedPaneId
                                            ? handleActiveEditorChange
                                            : NOOP_EDITOR_CHANGE
                                    }
                                    onSaveStatusChange={getSaveStatusHandler(
                                        pane.id,
                                    )}
                                    onChapterDataReady={handleChapterDataReady}
                                    onChapterMetaChange={
                                        handleChapterMetaChange
                                    }
                                    scenesVisible={scenesVisible}
                                    spellcheckEnabled={isSpellcheckEnabled}
                                    isTypewriterMode={isTypewriterMode}
                                    onToggleTypewriterMode={
                                        toggleTypewriterMode
                                    }
                                    review={reviewForChapter(pane.chapterId)}
                                    onReviewDismiss={dismissAllReviews}
                                    proseRunning={
                                        proseRunningChapterId === pane.chapterId
                                    }
                                    editorLocked={
                                        proseRunningChapterId ===
                                            pane.chapterId ||
                                        streamingChapterId === pane.chapterId
                                    }
                                    isLocalFindOpen={
                                        isLocalFindOpen &&
                                        pane.id === focusedPaneId
                                    }
                                    localFindShowReplace={localFindShowReplace}
                                    onLocalFindClose={closeLocalFind}
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
                                    key={focusedChapter.id}
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
                                    onNotesChange={handleNotesChange}
                                    onClose={closeNotes}
                                />
                            </SlidePanel>
                        )}

                        {focusedChapter && (
                            <SlidePanel
                                open={openPanels.has('plot')}
                                onClose={closePlot}
                                storageKey="manuscript:plot-panel-width"
                                defaultWidth={300}
                                minWidth={200}
                                maxWidth={600}
                            >
                                <PlotPanel
                                    key={focusedChapter.id}
                                    book={book}
                                    chapter={focusedChapter}
                                    onClose={closePlot}
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
                                    key={focusedChapter.id}
                                    book={book}
                                    chapter={focusedChapter}
                                    editorialChapterNote={
                                        focusedChapterData?.editorialChapterNote ??
                                        null
                                    }
                                    editorialFindings={
                                        focusedChapterData?.editorialFindings ??
                                        []
                                    }
                                    editorialReviewUrl={editorialReviewIndex.url(
                                        book,
                                    )}
                                    activeSceneId={
                                        focusedPane?.chapterId ===
                                        focusedChapter.id
                                            ? activeSceneId
                                            : null
                                    }
                                    onClose={closeAi}
                                    onError={(msg) => {
                                        console.error('[AiPanel]', msg);
                                    }}
                                    proseRunning={
                                        proseRunningChapterId ===
                                        focusedChapter.id
                                    }
                                    onProseStart={handleProseStart}
                                    onProseEnd={handleProseEnd}
                                    gateWritingStyle={gateWritingStyle}
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
                                    key={focusedChapter.id}
                                    book={book}
                                    chapter={focusedChapter}
                                    onClose={closeChat}
                                />
                            </SlidePanel>
                        )}

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
                onAddScene={focusedChapter ? handleAddScene : undefined}
                onEnterFocusMode={toggleFocusMode}
                isFocusMode={isFocusMode}
                panelItems={accessBarItems}
                openPanels={openPanels}
                onTogglePanel={togglePanel}
                isSpellcheckEnabled={isSpellcheckEnabled}
                onToggleSpellcheck={toggleSpellcheck}
                isTypewriterMode={isTypewriterMode}
                onToggleTypewriterMode={toggleTypewriterMode}
                onContinueWriting={
                    aiVisible && activeEditor && focusedChapter
                        ? () =>
                              gateWritingStyle(() =>
                                  setIsContinueWritingOpen(true),
                              )
                        : undefined
                }
                onRewriteSelection={
                    aiVisible && activeEditor && focusedChapter
                        ? () => {
                              const { from, to } = activeEditor.state.selection;
                              if (from === to) return;
                              gateWritingStyle(() =>
                                  setRewriteRange({ from, to }),
                              );
                          }
                        : undefined
                }
            />

            {pendingStyleGateAction && (
                <WritingStyleSetupDialog
                    bookId={book.id}
                    onProceed={(outcome) => {
                        // A plain skip keeps the offer alive for next time;
                        // generating or dismissing settles it for this book.
                        if (outcome !== 'skipped') setStylePromptable(false);
                        const action = pendingStyleGateAction;
                        setPendingStyleGateAction(null);
                        action();
                    }}
                    onClose={() => setPendingStyleGateAction(null)}
                />
            )}

            {isContinueWritingOpen && activeEditor && focusedChapter && (
                <ContinueWritingDialog
                    draft={continueWritingDraft}
                    onDraftChange={setContinueWritingDraft}
                    onReset={() =>
                        setContinueWritingDraft(defaultContinueWritingDraft)
                    }
                    onClose={() => setIsContinueWritingOpen(false)}
                    onSubmit={({ hint, wordGoal, chapterLink }) => {
                        const chapterId = focusedChapter.id;
                        setStreamingChapterId(chapterId);
                        continueWriting
                            .start({
                                editor: activeEditor,
                                activeSceneId,
                                bookId: book.id,
                                chapterId,
                                expectedCurrentVersionId:
                                    focusedChapter.current_version?.id ?? null,
                                hint,
                                wordGoal,
                                chapterLink,
                            })
                            .finally(() =>
                                setStreamingChapterId((current) =>
                                    current === chapterId ? null : current,
                                ),
                            );
                        setContinueWritingDraft(defaultContinueWritingDraft);
                    }}
                />
            )}

            {rewriteRange && activeEditor && focusedChapter && (
                <RewriteSelectionDialog
                    selectionPreview={activeEditor.state.doc.textBetween(
                        rewriteRange.from,
                        rewriteRange.to,
                        ' ',
                    )}
                    draft={rewriteDraft}
                    onDraftChange={setRewriteDraft}
                    onReset={() =>
                        setRewriteDraft(defaultRewriteSelectionDraft)
                    }
                    onClose={() => setRewriteRange(null)}
                    onSubmit={({ hint }) => {
                        const chapterId = focusedChapter.id;
                        setStreamingChapterId(chapterId);
                        rewriteSelection
                            .start({
                                editor: activeEditor,
                                bookId: book.id,
                                chapterId,
                                expectedCurrentVersionId:
                                    focusedChapter.current_version?.id ?? null,
                                hint,
                                selection: rewriteRange,
                            })
                            .finally(() =>
                                setStreamingChapterId((current) =>
                                    current === chapterId ? null : current,
                                ),
                            );
                        setRewriteDraft(defaultRewriteSelectionDraft);
                    }}
                />
            )}

            {/* Focus mode whisper chrome */}
            {isFocusMode && focusedChapter && (
                <WhisperChrome
                    chapterNumber={focusedChapter.reader_order}
                    chapterTitle={focusedDisplayTitle}
                    wordCount={sidebarWordCount}
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
