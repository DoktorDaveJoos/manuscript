import type { Editor } from '@tiptap/react';
import { EditorContent } from '@tiptap/react';
import type { RefObject } from 'react';
import { useCallback, useEffect, useRef } from 'react';
import { updateContent } from '@/actions/App/Http/Controllers/SceneController';
import type { SaveStatus } from '@/components/editor/EditorBar';
import type { SearchHighlight } from '@/extensions/SearchHighlightExtension';
import { updateSearchHighlight } from '@/extensions/SearchHighlightExtension';
import useChapterEditor from '@/hooks/useChapterEditor';
import type { StyleAnalysis, StyleAnalysisBridge } from '@/lib/style/types';
import { jsonFetchHeaders } from '@/lib/utils';
import type { Scene } from '@/types/models';

const SAVE_RETRY_DELAYS = [1000, 3000, 7000];

export default function SceneEditor({
    scene,
    bookId,
    chapterId,
    isFirst,
    onFocus,
    onEditorReady,
    onExitUp,
    onExitDown,
    onWordCountChange,
    onSaveStatusChange,
    scrollContainerRef,
    typewriterEnabledRef,
    scenesVisible = true,
    searchHighlight,
    bookLanguage,
    spellcheckEnabled,
    customWords,
    onAddToDictionary,
    styleAnalysis,
    locked = false,
    currentVersionId = null,
    onContentConflict,
}: {
    scene: Scene;
    bookId: number;
    chapterId: number;
    isFirst: boolean;
    onFocus: (editor: Editor) => void;
    onEditorReady?: (sceneId: number, editor: Editor) => void;
    onExitUp?: () => void;
    onExitDown?: () => void;
    onWordCountChange: (sceneId: number, count: number) => void;
    onSaveStatusChange?: (status: SaveStatus) => void;
    scrollContainerRef: RefObject<HTMLDivElement | null>;
    typewriterEnabledRef: RefObject<boolean>;
    scenesVisible?: boolean;
    searchHighlight?: SearchHighlight | null;
    bookLanguage?: string;
    spellcheckEnabled?: boolean;
    customWords?: string[];
    onAddToDictionary?: (word: string) => void;
    styleAnalysis?: StyleAnalysisBridge;
    /** Reject user input while an AI flow writes to this chapter (programmatic inserts still work). */
    locked?: boolean;
    /** Chapter version this editor's content belongs to — saves carry it so the server can refuse stale writes. */
    currentVersionId?: number | null;
    /** Refetch authoritative chapter data when the server rejects a stale scene save. */
    onContentConflict?: () => void;
}) {
    // Stable refs for cross-scene navigation callbacks (avoids editor re-creation)
    const onExitUpRef = useRef<(() => void) | null>(onExitUp ?? null);
    const onExitDownRef = useRef<(() => void) | null>(onExitDown ?? null);
    useEffect(() => {
        onExitUpRef.current = onExitUp ?? null;
        onExitDownRef.current = onExitDown ?? null;
    }, [onExitUp, onExitDown]);

    // Content auto-save
    const AUTOSAVE_DEBOUNCE_MS = 500;
    const contentAbortRef = useRef<AbortController | null>(null);
    const saveInFlightRef = useRef(false);
    const saveCompletionRef = useRef<Promise<void>>(Promise.resolve());
    const pendingContentRef = useRef<string | null>(null);
    const saveDebounceTimerRef = useRef<ReturnType<typeof setTimeout> | null>(
        null,
    );
    const retryCountRef = useRef(0);
    const retryTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const mountedRef = useRef(true);
    const drainingOnUnmountRef = useRef(false);
    // Declared before flushContentSave so scheduleRetry can re-invoke it via
    // the ref without referencing the const before its initializer completes.
    const flushSelfRef = useRef<
        (opts?: { keepalive?: boolean }) => Promise<void>
    >(async () => {});

    // Read at flush time so retries carry the freshest version id.
    const currentVersionIdRef = useRef(currentVersionId);
    currentVersionIdRef.current = currentVersionId;
    const contentVersionRef = useRef(scene.content_version ?? 0);
    const editorInstanceRef = useRef<Editor | null>(null);

    const dropPendingSave = useCallback(() => {
        pendingContentRef.current = null;
        retryCountRef.current = 0;
        if (saveDebounceTimerRef.current) {
            clearTimeout(saveDebounceTimerRef.current);
            saveDebounceTimerRef.current = null;
        }
        if (retryTimerRef.current) {
            clearTimeout(retryTimerRef.current);
            retryTimerRef.current = null;
        }
        contentAbortRef.current?.abort();
    }, []);

    // Only treat server prop updates as authoritative external replacements
    // (AI revision applied, version restored/accepted, global replaceAll) when
    // the server's content_version is strictly newer than what this editor has
    // already persisted. Ignoring <= versions prevents softRefresh() on pane
    // refocus from wiping undo history or dropping in-flight keystrokes.
    useEffect(() => {
        const incomingVersion = scene.content_version ?? 0;
        if (incomingVersion <= contentVersionRef.current) {
            return;
        }
        contentVersionRef.current = incomingVersion;
        dropPendingSave();
        const nextContent = scene.content ?? '';
        const ed = editorInstanceRef.current;
        if (ed && !ed.isDestroyed && ed.getHTML() !== nextContent) {
            ed.commands.setContent(nextContent, { emitUpdate: false });
        }
        onSaveStatusChange?.('saved');
    }, [
        scene.id,
        scene.content,
        scene.content_version,
        dropPendingSave,
        onSaveStatusChange,
    ]);

    const flushContentSave = useCallback(
        async (opts?: { keepalive?: boolean }) => {
            if (saveDebounceTimerRef.current) {
                clearTimeout(saveDebounceTimerRef.current);
                saveDebounceTimerRef.current = null;
            }

            const useKeepalive =
                opts?.keepalive === true || drainingOnUnmountRef.current;

            if (pendingContentRef.current === null) {
                if (saveInFlightRef.current) {
                    await saveCompletionRef.current;
                }
                return;
            }

            // Coalesce concurrent callers onto the active drain loop rather
            // than spawning recursive waiters per keystroke.
            if (saveInFlightRef.current) {
                await saveCompletionRef.current;
                if (pendingContentRef.current !== null) {
                    await flushSelfRef.current({ keepalive: useKeepalive });
                }
                return;
            }

            let resolveSaveCompletion: () => void = () => {};
            saveCompletionRef.current = new Promise<void>((resolve) => {
                resolveSaveCompletion = resolve;
            });

            saveInFlightRef.current = true;

            try {
                while (pendingContentRef.current !== null) {
                    const content: string = pendingContentRef.current;
                    const isUnmounting =
                        useKeepalive || drainingOnUnmountRef.current;
                    const body = JSON.stringify({
                        content,
                        expected_current_version_id:
                            currentVersionIdRef.current,
                        expected_content_version: contentVersionRef.current,
                    });
                    // Fetch enforces a 64 KiB ceiling on keepalive payloads;
                    // fall back to a regular un-aborted fetch for large scenes
                    // so SPA unmounts (chapter switch, pane close) never drop edits.
                    const keepalive =
                        isUnmounting && new Blob([body]).size <= 60_000;
                    const controller = new AbortController();
                    contentAbortRef.current = controller;

                    if (mountedRef.current) {
                        onSaveStatusChange?.('saving');
                    }

                    const response = await fetch(
                        updateContent.url({
                            book: bookId,
                            chapter: chapterId,
                            scene: scene.id,
                        }),
                        {
                            method: 'PUT',
                            headers: jsonFetchHeaders(),
                            body,
                            signal: isUnmounting
                                ? undefined
                                : controller.signal,
                            keepalive,
                        },
                    );

                    if (contentAbortRef.current === controller) {
                        contentAbortRef.current = null;
                    }

                    if (response.status === 409) {
                        const payload = (await response.json()) as {
                            conflict?: string;
                        };
                        dropPendingSave();
                        if (mountedRef.current) {
                            onSaveStatusChange?.('saved');
                            if (payload.conflict === 'content_version') {
                                onContentConflict?.();
                            }
                        }
                        return;
                    }

                    if (response.ok) {
                        const payload = (await response.json()) as {
                            content_version?: number;
                        };
                        if (typeof payload.content_version === 'number') {
                            contentVersionRef.current = payload.content_version;
                        }

                        retryCountRef.current = 0;
                        if (retryTimerRef.current) {
                            clearTimeout(retryTimerRef.current);
                            retryTimerRef.current = null;
                        }

                        if (pendingContentRef.current === content) {
                            pendingContentRef.current = null;
                            if (mountedRef.current) {
                                onSaveStatusChange?.('saved');
                            }
                        }
                        // Otherwise newer edits arrived during the PUT; the
                        // while-loop drains the latest snapshot immediately.
                    } else {
                        scheduleRetry();
                        return;
                    }
                }
            } catch (e) {
                if ((e as Error).name !== 'AbortError') {
                    scheduleRetry();
                }
            } finally {
                contentAbortRef.current = null;
                saveInFlightRef.current = false;
                resolveSaveCompletion();
            }

            function scheduleRetry() {
                if (!mountedRef.current) return;
                if (retryCountRef.current < SAVE_RETRY_DELAYS.length) {
                    const delay = SAVE_RETRY_DELAYS[retryCountRef.current]!;
                    retryCountRef.current += 1;
                    onSaveStatusChange?.('saving');
                    retryTimerRef.current = setTimeout(() => {
                        retryTimerRef.current = null;
                        void flushSelfRef.current();
                    }, delay);
                } else {
                    retryCountRef.current = 0;
                    onSaveStatusChange?.('error');
                }
            }
        },
        [
            bookId,
            chapterId,
            scene.id,
            onSaveStatusChange,
            onContentConflict,
            dropPendingSave,
        ],
    );

    // Expose flush for parent + keep flushSelfRef pointed at the latest closure.
    const flushRef = useRef({ flushContentSave });
    useEffect(() => {
        flushRef.current = { flushContentSave };
        flushSelfRef.current = flushContentSave;
    }, [flushContentSave]);

    // Attach flush + pending-content accessor to the DOM node so parent can call it
    const containerRef = useRef<HTMLDivElement>(null);
    const saveUrl = updateContent.url({
        book: bookId,
        chapter: chapterId,
        scene: scene.id,
    });
    const saveUrlRef = useRef(saveUrl);
    saveUrlRef.current = saveUrl;

    useEffect(() => {
        mountedRef.current = true;
        drainingOnUnmountRef.current = false;

        const el = containerRef.current;
        if (el) {
            (el as unknown as Record<string, unknown>).__flush = () =>
                flushRef.current.flushContentSave();
            (el as unknown as Record<string, unknown>).__getPending = () => {
                const content = pendingContentRef.current;
                if (content === null) return null;
                return {
                    url: saveUrlRef.current,
                    content,
                    expectedCurrentVersionId: currentVersionIdRef.current,
                    expectedContentVersion: contentVersionRef.current,
                };
            };
        }

        // Flush pending saves on unmount (including edits typed while a prior
        // PUT was still in-flight) using keepalive when within the 64 KiB cap.
        return () => {
            mountedRef.current = false;
            drainingOnUnmountRef.current = true;
            if (saveDebounceTimerRef.current) {
                clearTimeout(saveDebounceTimerRef.current);
                saveDebounceTimerRef.current = null;
            }
            if (retryTimerRef.current) {
                clearTimeout(retryTimerRef.current);
                retryTimerRef.current = null;
            }
            void flushRef.current.flushContentSave({ keepalive: true });
        };
    }, []);

    const handleEditorUpdate = useCallback(
        (html: string, words: number) => {
            pendingContentRef.current = html;
            onWordCountChange(scene.id, words);
            onSaveStatusChange?.('saving');

            // Cancel any in-flight retry — the new content supersedes it.
            // Preserve retryCountRef so sustained outages actually back off
            // instead of restarting a 3-attempt cycle on every keystroke.
            // The counter resets on successful save (above) or on exhaustion.
            if (retryTimerRef.current) {
                clearTimeout(retryTimerRef.current);
                retryTimerRef.current = null;
            }

            if (saveDebounceTimerRef.current) {
                clearTimeout(saveDebounceTimerRef.current);
            }
            saveDebounceTimerRef.current = setTimeout(() => {
                saveDebounceTimerRef.current = null;
                void flushSelfRef.current();
            }, AUTOSAVE_DEBOUNCE_MS);
        },
        [scene.id, onWordCountChange, onSaveStatusChange],
    );

    // The extension captures these callbacks at editor creation — keep the
    // identities stable and route through refs for the latest bridge.
    const styleBridgeRef = useRef(styleAnalysis);
    useEffect(() => {
        styleBridgeRef.current = styleAnalysis;
    }, [styleAnalysis]);
    const handleStyleAnalysis = useCallback(
        (analysis: StyleAnalysis | null) =>
            styleBridgeRef.current?.onSceneAnalysis(scene.id, analysis),
        [scene.id],
    );
    const handleIgnoreStyleWord = useCallback(
        (word: string) => styleBridgeRef.current?.onIgnoreWord(word),
        [],
    );

    const editor = useChapterEditor({
        content: scene.content ?? '',
        onUpdate: handleEditorUpdate,
        scrollContainerRef,
        typewriterEnabledRef,
        onExitUpRef,
        onExitDownRef,
        language: bookLanguage,
        spellcheckEnabled,
        customWords,
        onAddToDictionary,
        styleAnalysisActive: styleAnalysis?.active ?? false,
        styleAnalyzeOptions: styleAnalysis?.options,
        onStyleAnalysis: handleStyleAnalysis,
        onIgnoreStyleWord: handleIgnoreStyleWord,
    });
    editorInstanceRef.current = editor;

    // setEditable blocks keyboard/DOM input only — AI streams insert via
    // commands, which still dispatch. Re-applied after every editor
    // re-creation ([editor] dep) so a mid-lock content sync can't unlock.
    useEffect(() => {
        if (!editor || editor.isDestroyed) return;
        editor.setEditable(!locked);
    }, [editor, locked]);

    // Notify parent when this editor gains focus
    useEffect(() => {
        if (!editor) return;

        const handleFocus = () => onFocus(editor);
        editor.on('focus', handleFocus);
        return () => {
            editor.off('focus', handleFocus);
        };
    }, [editor, onFocus]);

    // Register editor with parent for cross-scene navigation
    useEffect(() => {
        if (!editor || !onEditorReady) return;
        onEditorReady(scene.id, editor);
    }, [editor, scene.id, onEditorReady]);

    useEffect(() => {
        if (!editor) return;
        const activeMatchIndex =
            searchHighlight?.activeSceneId === scene.id
                ? (searchHighlight.activeMatchIndex ?? -1)
                : -1;

        updateSearchHighlight(editor, {
            query: searchHighlight?.query ?? '',
            caseSensitive: searchHighlight?.caseSensitive ?? false,
            wholeWord: searchHighlight?.wholeWord ?? false,
            regex: searchHighlight?.regex ?? false,
            activeMatchIndex,
        });

        if (activeMatchIndex < 0) return;

        const frame = requestAnimationFrame(() => {
            containerRef.current
                ?.querySelector<HTMLElement>('.search-highlight-active')
                ?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        });

        return () => cancelAnimationFrame(frame);
    }, [
        editor,
        scene.id,
        searchHighlight?.query,
        searchHighlight?.caseSensitive,
        searchHighlight?.wholeWord,
        searchHighlight?.regex,
        searchHighlight?.activeSceneId,
        searchHighlight?.activeMatchIndex,
    ]);

    return (
        <div ref={containerRef} id={`scene-${scene.id}`}>
            {/* Scene divider (except first) */}
            {!isFirst && scenesVisible && (
                <div className="flex items-center justify-center py-2 select-none">
                    <span className="text-[11px] tracking-[0.6em] text-ink-faint/40">
                        •&nbsp;•&nbsp;•
                    </span>
                </div>
            )}

            {/* Scene header */}
            {scenesVisible && (
                <div className="mb-2">
                    <span className="text-[11px] tracking-[0.04em] text-ink-faint/50 select-none">
                        {scene.title}
                    </span>
                </div>
            )}

            {/* Editor */}
            <EditorContent editor={editor} />
        </div>
    );
}
