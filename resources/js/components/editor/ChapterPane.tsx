import type { Editor } from '@tiptap/react';
import { X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { ChapterData } from '@/hooks/useChapterData';
import { useProofreading } from '@/hooks/useProofreading';
import { jsonFetchHeaders } from '@/lib/utils';
import { DEFAULT_PROOFREADING_CONFIG } from '@/types/models';
import type { AppSettings, Scene } from '@/types/models';
import EditorBar from './EditorBar';
import type { SaveStatus } from './EditorBar';
import FormattingToolbar from './FormattingToolbar';
import VersionHistoryOverlay from './VersionHistoryOverlay';
import WritingSurface from './WritingSurface';
import { updateTitle } from '@/actions/App/Http/Controllers/ChapterController';

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
    onSaveStatusChange,
    spellcheckEnabled,
    scenesVisible,
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
    onSaveStatusChange: (status: SaveStatus) => void;
    spellcheckEnabled: boolean;
    scenesVisible: boolean;
}) {
    const { t } = useTranslation('editor');
    const {
        chapter,
        versionCount,
        proofreadingConfig: initialProofreadingConfig,
    } = chapterData;

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

    // Typewriter mode — per-pane, initialized from app settings
    const [isTypewriterMode, setIsTypewriterMode] = useState(
        appSettings.typewriter_mode,
    );
    const toggleTypewriterMode = useCallback(() => {
        setIsTypewriterMode((prev) => !prev);
    }, []);

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

            if (titleTimerRef.current) {
                clearTimeout(titleTimerRef.current);
            }

            titleTimerRef.current = setTimeout(() => {
                flushTitleSave();
            }, 1500);
        },
        [flushTitleSave, handleLocalSaveStatusChange],
    );

    // Flush pending title save on unmount
    const flushTitleRef = useRef(flushTitleSave);
    flushTitleRef.current = flushTitleSave;
    useEffect(() => {
        return () => {
            if (titleTimerRef.current) clearTimeout(titleTimerRef.current);
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

    // Expose flushAll on the pane root div for the parent to call
    const flushAllRef = useRef(flushAll);
    flushAllRef.current = flushAll;
    useEffect(() => {
        const el = paneRef.current;
        if (el) {
            (el as unknown as Record<string, unknown>).__flushPane = () =>
                flushAllRef.current();
        }
    }, []);

    // ── Derived values ───────────────────────────────────────────────────
    const displayTitle = firstLine(chapterTitle);
    const povCharacterName = chapter.pov_character?.name ?? null;
    const timelineLabel = chapter.storyline?.timeline_label ?? null;

    return (
        <div
            ref={paneRef}
            data-pane-chapter={chapter.id}
            className={`relative flex min-w-[400px] flex-1 flex-col transition-opacity duration-200 ${
                isFocused
                    ? 'border-t-2 border-t-accent opacity-100'
                    : 'border-t-2 border-t-transparent opacity-75'
            }`}
            onMouseDown={onFocus}
        >
            {/* Editor bar with close button */}
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
                            versionCount={versionCount}
                            onVersionClick={() =>
                                setShowVersions(!showVersions)
                            }
                        />
                    </div>
                    <button
                        type="button"
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
                />
            )}

            {/* Formatting toolbar — only for the focused pane */}
            <div
                className={`transition-[height,opacity] duration-300 ${
                    isFocusMode ||
                    appSettings.hide_formatting_toolbar ||
                    !isFocused
                        ? 'h-0 overflow-hidden opacity-0'
                        : 'h-[38px]'
                }`}
            >
                <FormattingToolbar
                    editor={activeEditor}
                    onToggleFocusMode={() => {
                        /* focus mode is handled by parent */
                    }}
                    isTypewriterMode={isTypewriterMode}
                    onToggleTypewriterMode={toggleTypewriterMode}
                />
            </div>

            {/* Writing surface */}
            <WritingSurface
                scenes={scenes}
                bookId={bookId}
                chapterId={chapter.id}
                title={chapterTitle}
                autoSelectTitle={pendingTitleSelect}
                onTitleSelectHandled={handleTitleSelectHandled}
                povCharacterName={povCharacterName}
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
            />
        </div>
    );
}

export type { SaveStatus };
