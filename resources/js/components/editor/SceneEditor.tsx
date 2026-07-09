import type { Editor } from '@tiptap/react';
import { EditorContent } from '@tiptap/react';
import type { RefObject } from 'react';
import { useCallback, useEffect, useRef } from 'react';
import { updateContent } from '@/actions/App/Http/Controllers/SceneController';
import type { SaveStatus } from '@/components/editor/EditorBar';
import type { SearchHighlight } from '@/extensions/SearchHighlightExtension';
import { updateSearchHighlight } from '@/extensions/SearchHighlightExtension';
import useChapterEditor from '@/hooks/useChapterEditor';
import { jsonFetchHeaders } from '@/lib/utils';
import type { ProofreadingConfig, Scene } from '@/types/models';

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
    proofreadingConfig,
    bookLanguage,
    spellcheckEnabled,
    locked = false,
    currentVersionId = null,
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
    proofreadingConfig?: ProofreadingConfig;
    bookLanguage?: string;
    spellcheckEnabled?: boolean;
    /** Reject user input while an AI flow writes to this chapter (programmatic inserts still work). */
    locked?: boolean;
    /** Chapter version this editor's content belongs to — saves carry it so the server can refuse stale writes. */
    currentVersionId?: number | null;
}) {
    // Stable refs for cross-scene navigation callbacks (avoids editor re-creation)
    const onExitUpRef = useRef<(() => void) | null>(onExitUp ?? null);
    const onExitDownRef = useRef<(() => void) | null>(onExitDown ?? null);
    useEffect(() => {
        onExitUpRef.current = onExitUp ?? null;
        onExitDownRef.current = onExitDown ?? null;
    }, [onExitUp, onExitDown]);

    // Content auto-save
    const contentAbortRef = useRef<AbortController | null>(null);
    const pendingContentRef = useRef<string | null>(null);
    const retryCountRef = useRef(0);
    const retryTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const mountedRef = useRef(true);
    // Declared before flushContentSave so scheduleRetry can re-invoke it via
    // the ref without referencing the const before its initializer completes.
    const flushSelfRef = useRef<() => void | Promise<void>>(() => {});

    // Read at flush time so retries carry the freshest version id.
    const currentVersionIdRef = useRef(currentVersionId);
    currentVersionIdRef.current = currentVersionId;

    const dropPendingSave = useCallback(() => {
        pendingContentRef.current = null;
        retryCountRef.current = 0;
        if (retryTimerRef.current) {
            clearTimeout(retryTimerRef.current);
            retryTimerRef.current = null;
        }
        contentAbortRef.current?.abort();
    }, []);

    // The server replaced this scene's content out from under the editor
    // (AI revision applied, version restored/accepted). Any buffered or
    // retrying save was composed against the old content — flushing it would
    // resurrect pre-revision text over the newer server state.
    const prevServerContentRef = useRef(scene.content);
    useEffect(() => {
        if (scene.content === prevServerContentRef.current) return;
        prevServerContentRef.current = scene.content;
        dropPendingSave();
        onSaveStatusChange?.('saved');
    }, [scene.content, dropPendingSave, onSaveStatusChange]);

    const flushContentSave = useCallback(async () => {
        const content = pendingContentRef.current;
        if (content === null) return;

        contentAbortRef.current?.abort();
        const controller = new AbortController();
        contentAbortRef.current = controller;

        onSaveStatusChange?.('saving');

        try {
            const response = await fetch(
                updateContent.url({
                    book: bookId,
                    chapter: chapterId,
                    scene: scene.id,
                }),
                {
                    method: 'PUT',
                    headers: jsonFetchHeaders(),
                    body: JSON.stringify({
                        content,
                        expected_current_version_id:
                            currentVersionIdRef.current,
                    }),
                    signal: controller.signal,
                },
            );

            if (response.status === 409) {
                // The chapter moved to a new version while this save was
                // buffered — the content is superseded, retrying would only
                // overwrite newer server state. The version-change broadcast
                // refreshes the pane with the authoritative content.
                dropPendingSave();
                onSaveStatusChange?.('saved');
                return;
            }

            if (response.ok) {
                // Only clear if content hasn't changed during the in-flight save
                if (pendingContentRef.current === content) {
                    pendingContentRef.current = null;
                    onSaveStatusChange?.('saved');
                }
                retryCountRef.current = 0;
                if (retryTimerRef.current) {
                    clearTimeout(retryTimerRef.current);
                    retryTimerRef.current = null;
                }
            } else {
                scheduleRetry();
            }
        } catch (e) {
            if ((e as Error).name !== 'AbortError') {
                scheduleRetry();
            }
        }

        function scheduleRetry() {
            if (!mountedRef.current) return;
            if (retryCountRef.current < SAVE_RETRY_DELAYS.length) {
                const delay = SAVE_RETRY_DELAYS[retryCountRef.current]!;
                retryCountRef.current += 1;
                onSaveStatusChange?.('saving');
                retryTimerRef.current = setTimeout(() => {
                    retryTimerRef.current = null;
                    flushSelfRef.current();
                }, delay);
            } else {
                retryCountRef.current = 0;
                onSaveStatusChange?.('error');
            }
        }
    }, [bookId, chapterId, scene.id, onSaveStatusChange, dropPendingSave]);

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
                };
            };
        }

        // Flush pending saves on unmount
        return () => {
            mountedRef.current = false;
            if (retryTimerRef.current) clearTimeout(retryTimerRef.current);
            flushRef.current.flushContentSave();
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

            flushContentSave();
        },
        [scene.id, onWordCountChange, onSaveStatusChange, flushContentSave],
    );

    const editor = useChapterEditor({
        content: scene.content ?? '',
        onUpdate: handleEditorUpdate,
        scrollContainerRef,
        typewriterEnabledRef,
        onExitUpRef,
        onExitDownRef,
        proofreadingConfig,
        language: bookLanguage,
        spellcheckEnabled,
    });

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
        updateSearchHighlight(editor, {
            query: searchHighlight?.query ?? '',
            caseSensitive: searchHighlight?.caseSensitive ?? false,
            wholeWord: searchHighlight?.wholeWord ?? false,
            regex: searchHighlight?.regex ?? false,
        });
    }, [
        editor,
        searchHighlight?.query,
        searchHighlight?.caseSensitive,
        searchHighlight?.wholeWord,
        searchHighlight?.regex,
    ]);

    return (
        <div ref={containerRef} id={`scene-${scene.id}`}>
            {/* Scene divider (except first) */}
            {!isFirst && scenesVisible && (
                <div className="flex items-center justify-center py-2 select-none">
                    <span className="text-[9px] tracking-[0.6em] text-ink-faint/40">
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
