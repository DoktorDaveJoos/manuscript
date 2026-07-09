import type { Editor } from '@tiptap/react';
import { X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { updateTitle } from '@/actions/App/Http/Controllers/ChapterController';
import { openWindow as openDiffWindow } from '@/actions/App/Http/Controllers/ChapterDiffController';
import type { ChapterData } from '@/hooks/useChapterData';
import type { ContinueWritingReview } from '@/hooks/useContinueWriting';
import { useProofreading } from '@/hooks/useProofreading';
import type { RewriteSelectionReview } from '@/hooks/useRewriteSelection';
import { htmlBlockText } from '@/lib/proseText';
import { jsonFetchHeaders } from '@/lib/utils';
import { DEFAULT_PROOFREADING_CONFIG } from '@/types/models';
import type { AppSettings, ChapterVersion, Scene } from '@/types/models';
import DiffView from './DiffView';
import EditorBar from './EditorBar';
import type { SaveStatus } from './EditorBar';
import FormattingToolbar from './FormattingToolbar';
import VersionHistoryOverlay from './VersionHistoryOverlay';
import WritingSurface from './WritingSurface';

export function firstLine(text: string): string {
    return text.split('\n')[0];
}

export default function ChapterPane({
    bookId,
    bookLanguage,
    chapterData,
    appSettings,
    isFocused,
    isFocusMode,
    onFocus,
    onClose,
    onActiveEditorChange,
    onChapterMetaChange,
    onSaveStatusChange,
    onVersionsChanged,
    scenesVisible,
    spellcheckEnabled,
    isTypewriterMode,
    onToggleTypewriterMode,
    review,
    onReviewApplied,
    proseRunning = false,
    editorLocked = false,
    isLocalFindOpen = false,
    localFindShowReplace = false,
    onLocalFindClose,
}: {
    bookId: number;
    bookLanguage: string;
    chapterData: ChapterData;
    appSettings: AppSettings;
    isFocused: boolean;
    isFocusMode: boolean;
    onFocus: () => void;
    onClose: () => void;
    onActiveEditorChange: (
        editor: Editor | null,
        sceneId: number | null,
    ) => void;
    onChapterMetaChange?: (meta: {
        chapterId: number;
        title: string;
        wordCount: number;
    }) => void;
    onSaveStatusChange: (status: SaveStatus) => void;
    onVersionsChanged: () => void;
    scenesVisible: boolean;
    spellcheckEnabled: boolean;
    isTypewriterMode: boolean;
    onToggleTypewriterMode: () => void;
    review: ContinueWritingReview | RewriteSelectionReview | null;
    onReviewApplied: () => void;
    proseRunning?: boolean;
    /** True while any AI flow writes to this chapter — scene editors reject user input. */
    editorLocked?: boolean;
    isLocalFindOpen?: boolean;
    localFindShowReplace?: boolean;
    onLocalFindClose?: () => void;
}) {
    const { t } = useTranslation('editor');
    const { chapter, proofreadingConfig: initialProofreadingConfig } =
        chapterData;

    const { config: proofreadingConfig } = useProofreading(
        initialProofreadingConfig ?? DEFAULT_PROOFREADING_CONFIG,
        [],
        bookId,
    );

    // ── Local state ──────────────────────────────────────────────────────
    const [scenes, setScenes] = useState<Scene[]>(chapter.scenes ?? []);
    const [chapterTitle, setChapterTitle] = useState(chapter.title);
    const [saveStatus, setSaveStatus] = useState<SaveStatus>('saved');
    const [activeEditor, setActiveEditor] = useState<Editor | null>(null);
    const activeEditorRef = useRef<Editor | null>(null);
    activeEditorRef.current = activeEditor;
    const [activeSceneId, setActiveSceneId] = useState<number | null>(null);
    const activeSceneIdRef = useRef<number | null>(null);
    activeSceneIdRef.current = activeSceneId;
    const [pendingFocusSceneId, setPendingFocusSceneId] = useState<
        number | null
    >(null);
    const [showVersions, setShowVersions] = useState(false);
    const [isReviewing, setIsReviewing] = useState(false);
    const [compareVersion, setCompareVersion] = useState<ChapterVersion | null>(
        null,
    );

    // The pane is reused across chapter navigation (no remount); reset review
    // overlay so we don't show stale diff content.
    useEffect(() => {
        setIsReviewing(false);
        setCompareVersion(null);
    }, [chapter.id]);

    const showReview = review !== null && review.previous !== null;
    const handleApplied = useCallback(() => {
        setIsReviewing(false);
        onReviewApplied();
    }, [onReviewApplied]);
    const handleCloseDiff = useCallback(() => setIsReviewing(false), []);

    // Desktop opens the diff in its own native window (proper full-bleed
    // side-by-side); web/dev fall back to the in-pane overlay.
    const handleReviewClick = useCallback(async () => {
        if (!review) return;
        if (typeof window !== 'undefined' && window.Native?.on) {
            try {
                const response = await fetch(
                    openDiffWindow.url({
                        book: bookId,
                        chapter: chapter.id,
                        version: review.new.id,
                    }),
                    { method: 'POST', headers: jsonFetchHeaders() },
                );
                if (response.ok) return;
            } catch {
                /* fall through to in-pane overlay */
            }
        }
        setIsReviewing(true);
    }, [bookId, chapter.id, review]);
    const handleCompareApplied = useCallback(() => {
        setCompareVersion(null);
        onVersionsChanged();
    }, [onVersionsChanged]);
    const handleCompareClose = useCallback(() => setCompareVersion(null), []);
    const handleCompareSelect = useCallback((version: ChapterVersion) => {
        setShowVersions(false);
        setCompareVersion(version);
    }, []);

    // Fire a toast once per new AI review. The review state sticks around so
    // handleReviewClick still works if the user clicks "Review changes" later.
    const handleReviewClickRef = useRef(handleReviewClick);
    handleReviewClickRef.current = handleReviewClick;
    useEffect(() => {
        if (!showReview || !review) return;

        const copy = {
            continue_writing: {
                title: 'continueWriting.toast.title',
                description:
                    review.kind === 'continue_writing' && review.addedWords > 0
                        ? 'continueWriting.toast.descriptionWithCount'
                        : 'continueWriting.toast.description',
                action: 'continueWriting.toast.review',
            },
            rewrite_selection: {
                title: 'rewriteSelection.toast.title',
                description: 'rewriteSelection.toast.description',
                action: 'rewriteSelection.toast.review',
            },
        }[review.kind];

        const descriptionArgs =
            review.kind === 'continue_writing' && review.addedWords > 0
                ? { count: review.addedWords }
                : undefined;

        toast(t(copy.title), {
            description: t(copy.description, descriptionArgs),
            action: {
                label: t(copy.action),
                onClick: () => handleReviewClickRef.current(),
            },
        });
    }, [showReview, review, t]);

    const [pendingTitleSelect, setPendingTitleSelect] = useState(
        () => chapter.word_count === 0,
    );
    const prevChapterIdRef = useRef(chapter.id);
    useEffect(() => {
        if (chapter.id !== prevChapterIdRef.current) {
            prevChapterIdRef.current = chapter.id;
            setPendingTitleSelect(chapter.word_count === 0);
        }
    }, [chapter.id, chapter.word_count]);
    const handleTitleSelectHandled = useCallback(
        () => setPendingTitleSelect(false),
        [],
    );

    const editorFont = appSettings.editor_font;
    const editorFontSize = appSettings.editor_font_size;

    // ── Sync state on chapter data change ────────────────────────────────
    useEffect(() => {
        setScenes(chapter.scenes ?? []);
    }, [chapter.id, chapter.scenes]);

    useEffect(() => {
        setChapterTitle(chapter.title);
    }, [chapter.id, chapter.title]);

    // ── Word count ───────────────────────────────────────────────────────
    const wordCount = scenes.reduce((sum, s) => sum + s.word_count, 0);

    const getChapterText = useCallback(
        () =>
            scenes
                .map((s) => htmlBlockText(s.content))
                .filter(Boolean)
                .join('\n\n'),
        [scenes],
    );

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

    // ── Save status reporting ────────────────────────────────────────────
    const handleLocalSaveStatusChange = useCallback(
        (status: SaveStatus) => {
            setSaveStatus(status);
            onSaveStatusChange(status);
        },
        [onSaveStatusChange],
    );

    // ── Active editor reporting ──────────────────────────────────────────
    const handleActiveEditorChange = useCallback(
        (editor: Editor) => {
            setActiveEditor(editor);
            if (isFocused) {
                onActiveEditorChange(editor, activeSceneIdRef.current);
            }
        },
        [isFocused, onActiveEditorChange],
    );

    const handleActiveSceneIdChange = useCallback(
        (sceneId: number) => {
            setActiveSceneId(sceneId);
            if (isFocused && activeEditorRef.current) {
                onActiveEditorChange(activeEditorRef.current, sceneId);
            }
        },
        [isFocused, onActiveEditorChange],
    );

    // Report active editor when focus changes to this pane
    useEffect(() => {
        if (isFocused) {
            onActiveEditorChange(activeEditorRef.current, activeSceneId);
        }
    }, [isFocused, activeSceneId, onActiveEditorChange]);

    // ── Chapter title auto-save ──────────────────────────────────────────
    const titleAbortRef = useRef<AbortController | null>(null);
    const pendingTitleRef = useRef<string | null>(null);

    const flushTitleSave = useCallback(async () => {
        const title = pendingTitleRef.current;
        if (title === null) return;
        pendingTitleRef.current = null;

        titleAbortRef.current?.abort();
        const controller = new AbortController();
        titleAbortRef.current = controller;

        handleLocalSaveStatusChange('saving');

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

            handleLocalSaveStatusChange('saved');
        } catch (e) {
            if ((e as Error).name !== 'AbortError') {
                handleLocalSaveStatusChange('error');
            }
        }
    }, [bookId, chapter.id, handleLocalSaveStatusChange]);

    const handleTitleUpdate = useCallback(
        (title: string) => {
            setChapterTitle(title);
            handleLocalSaveStatusChange('unsaved');
            pendingTitleRef.current = title;
            flushTitleSave();
        },
        [flushTitleSave, handleLocalSaveStatusChange],
    );

    // Flush pending title save on unmount
    const flushTitleRef = useRef(flushTitleSave);
    flushTitleRef.current = flushTitleSave;
    useEffect(() => {
        return () => {
            flushTitleRef.current();
        };
    }, []);

    // ── Flush all pending saves ──────────────────────────────────────────
    const paneRef = useRef<HTMLDivElement>(null);

    const flushAll = useCallback(async () => {
        // Flush title
        await flushTitleSave();
        // Flush all scene editors within this pane's DOM subtree
        const paneEl = paneRef.current;
        if (!paneEl) return;
        const sceneEls = paneEl.querySelectorAll('[id^="scene-"]');
        const flushes = Array.from(sceneEls).map((el) => {
            const flush = (el as unknown as Record<string, () => Promise<void>>)
                .__flush;
            return typeof flush === 'function' ? flush() : Promise.resolve();
        });
        await Promise.all(flushes);
    }, [flushTitleSave]);

    // Expose flushAll + pending-content collector on the pane root div
    const flushAllRef = useRef(flushAll);
    flushAllRef.current = flushAll;
    useEffect(() => {
        const el = paneRef.current;
        if (!el) return;
        (el as unknown as Record<string, unknown>).__flushPane = () =>
            flushAllRef.current();
        (el as unknown as Record<string, unknown>).__getPendingAll = () => {
            const sceneEls = el.querySelectorAll('[id^="scene-"]');
            const pending: { url: string; content: string }[] = [];
            sceneEls.forEach((sceneEl) => {
                const getPending = (
                    sceneEl as unknown as Record<
                        string,
                        () => { url: string; content: string } | null
                    >
                ).__getPending;
                if (typeof getPending === 'function') {
                    const p = getPending();
                    if (p) pending.push(p);
                }
            });
            return pending;
        };
    }, []);

    // ── Derived values ───────────────────────────────────────────────────
    const displayTitle = firstLine(chapterTitle);
    const povCharacterName = chapter.pov_character?.name ?? null;
    const povCharacterId = chapter.pov_character?.id ?? null;
    const timelineLabel = chapter.storyline?.timeline_label ?? null;

    useEffect(() => {
        if (!isFocused) return;

        onChapterMetaChange?.({
            chapterId: chapter.id,
            title: displayTitle,
            wordCount,
        });
    }, [isFocused, chapter.id, displayTitle, wordCount, onChapterMetaChange]);

    return (
        <div
            ref={paneRef}
            data-pane-chapter={chapter.id}
            className={`relative flex min-w-0 flex-1 flex-col transition-opacity duration-200 ${
                isFocused ? 'opacity-100' : 'opacity-75'
            }`}
            onMouseDown={onFocus}
        >
            {/* Anchors VersionHistoryOverlay's `top-full` to the 38px editor bar, not the full-height pane (the pane root is also `relative`, but anchoring there clips the dropdown below the viewport). */}
            <div className="relative">
                <div
                    className={`overflow-hidden transition-[height,opacity] duration-300 ${
                        isFocusMode ? 'h-0 opacity-0' : 'h-[38px]'
                    }`}
                >
                    <div className="flex items-center">
                        <div className="min-w-0 flex-1">
                            <EditorBar
                                chapter={chapter}
                                chapterTitle={displayTitle}
                                storylineName={
                                    chapter.storyline?.name ??
                                    t('show.untitledStoryline')
                                }
                                wordCount={wordCount}
                                saveStatus={saveStatus}
                                getChapterText={getChapterText}
                                onVersionClick={() =>
                                    setShowVersions(!showVersions)
                                }
                            />
                        </div>
                        <button
                            type="button"
                            onMouseDown={(e) => e.stopPropagation()}
                            onClick={(e) => {
                                e.stopPropagation();
                                onClose();
                            }}
                            className="mr-2 flex h-6 w-6 shrink-0 items-center justify-center rounded text-ink-faint transition-colors hover:bg-neutral-bg hover:text-ink"
                        >
                            <X size={14} />
                        </button>
                    </div>
                </div>

                {/* Version history overlay */}
                {showVersions && !isFocusMode && (
                    <VersionHistoryOverlay
                        bookId={bookId}
                        chapterId={chapter.id}
                        onClose={() => setShowVersions(false)}
                        onVersionsChanged={onVersionsChanged}
                        onCompare={handleCompareSelect}
                    />
                )}
            </div>

            {/* Formatting toolbar — only for the focused pane */}
            <div
                className={`h-[38px] transition-opacity duration-200 ${
                    isFocusMode ||
                    appSettings.hide_formatting_toolbar ||
                    !isFocused
                        ? 'pointer-events-none invisible opacity-0'
                        : 'opacity-100'
                }`}
            >
                <FormattingToolbar
                    editor={activeEditor}
                    onToggleFocusMode={() => {
                        /* focus mode is handled by parent */
                    }}
                    isTypewriterMode={isTypewriterMode}
                    onToggleTypewriterMode={onToggleTypewriterMode}
                />
            </div>

            {/* Replaces WritingSurface in this pane only, so other panes keep editing. */}
            {isReviewing && review && review.previous && (
                <DiffView
                    bookId={bookId}
                    chapterId={chapter.id}
                    chapterTitle={firstLine(chapterTitle)}
                    currentVersion={review.previous}
                    pendingVersion={review.new}
                    editorFont={editorFont}
                    mode="refine"
                    onApplied={handleApplied}
                    onClose={handleCloseDiff}
                />
            )}

            {compareVersion && chapter.current_version && (
                <DiffView
                    bookId={bookId}
                    chapterId={chapter.id}
                    chapterTitle={firstLine(chapterTitle)}
                    currentVersion={chapter.current_version}
                    pendingVersion={compareVersion}
                    editorFont={editorFont}
                    mode={
                        compareVersion.status === 'pending'
                            ? 'pending'
                            : 'history'
                    }
                    onApplied={handleCompareApplied}
                    onClose={handleCompareClose}
                />
            )}

            {!isReviewing && !compareVersion && (
                <div className="relative flex min-h-0 flex-1 flex-col">
                    <WritingSurface
                        scenes={scenes}
                        bookId={bookId}
                        chapterId={chapter.id}
                        title={chapterTitle}
                        autoSelectTitle={pendingTitleSelect}
                        onTitleSelectHandled={handleTitleSelectHandled}
                        povCharacterName={povCharacterName}
                        povCharacterId={povCharacterId}
                        timelineLabel={timelineLabel}
                        onTitleUpdate={handleTitleUpdate}
                        activeEditor={activeEditor}
                        onActiveEditorChange={handleActiveEditorChange}
                        onWordCountChange={handleSceneWordCountChange}
                        onSaveStatusChange={handleLocalSaveStatusChange}
                        isTypewriterMode={isTypewriterMode}
                        editorFont={editorFont}
                        editorFontSize={editorFontSize}
                        pendingFocusSceneId={pendingFocusSceneId}
                        onFocusHandled={() => setPendingFocusSceneId(null)}
                        onActiveSceneIdChange={handleActiveSceneIdChange}
                        scenesVisible={scenesVisible}
                        proofreadingConfig={proofreadingConfig}
                        bookLanguage={bookLanguage}
                        spellcheckEnabled={spellcheckEnabled}
                        isLocalFindOpen={isLocalFindOpen}
                        localFindShowReplace={localFindShowReplace}
                        onLocalFindClose={onLocalFindClose}
                        locked={editorLocked}
                        currentVersionId={chapter.current_version?.id ?? null}
                    />
                    {proseRunning && (
                        <div
                            className="absolute inset-0 z-20 flex items-center justify-center bg-surface/60 backdrop-blur-sm"
                            aria-live="polite"
                        >
                            <div className="flex flex-col items-center gap-3">
                                <div className="size-6 animate-spin rounded-full border-2 border-accent border-t-transparent" />
                                <span className="text-sm font-medium text-ink">
                                    {t('proseRevise.overlay.label', {
                                        defaultValue: 'Revising prose…',
                                    })}
                                </span>
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

export type { SaveStatus };
