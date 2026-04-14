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
    const contentTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const pendingContentRef = useRef<string | null>(null);
    const retryCountRef = useRef(0);
    const retryTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const mountedRef = useRef(true);

    const flushContentSave = useCallback(async () => {
        if (contentTimerRef.current) {
            clearTimeout(contentTimerRef.current);
            contentTimerRef.current = null;
        }

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
                    body: JSON.stringify({ content }),
                    signal: controller.signal,
                },
            );

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
                    flushContentSave();
                }, delay);
            } else {
                retryCountRef.current = 0;
                onSaveStatusChange?.('error');
            }
        }
    }, [bookId, chapterId, scene.id, onSaveStatusChange]);

    // Expose flush for parent
    const flushRef = useRef({ flushContentSave });
    useEffect(() => {
        flushRef.current = { flushContentSave };
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
                return { url: saveUrlRef.current, content };
            };
        }

        // Flush pending saves on unmount
        return () => {
            mountedRef.current = false;
            if (contentTimerRef.current) clearTimeout(contentTimerRef.current);
            if (retryTimerRef.current) clearTimeout(retryTimerRef.current);
            flushRef.current.flushContentSave();
        };
    }, []);

    const handleEditorUpdate = useCallback(
        (html: string, words: number) => {
            pendingContentRef.current = html;
            onWordCountChange(scene.id, words);
            onSaveStatusChange?.('saving');

            // Cancel any in-flight retry — the new debounce supersedes it.
            // Preserve retryCountRef so sustained outages actually back off
            // instead of restarting a 3-attempt cycle on every keystroke.
            // The counter resets on successful save (above) or on exhaustion.
            if (retryTimerRef.current) {
                clearTimeout(retryTimerRef.current);
                retryTimerRef.current = null;
            }

            if (contentTimerRef.current) {
                clearTimeout(contentTimerRef.current);
            }
            contentTimerRef.current = setTimeout(() => {
                flushContentSave();
            }, 1500);
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
