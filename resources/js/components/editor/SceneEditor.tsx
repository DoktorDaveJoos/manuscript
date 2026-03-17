import { updateContent } from '@/actions/App/Http/Controllers/SceneController';
import useChapterEditor from '@/hooks/useChapterEditor';
import { jsonFetchHeaders } from '@/lib/utils';
import type { SaveStatus } from '@/components/editor/EditorBar';
import type { Scene } from '@/types/models';
import type { Editor } from '@tiptap/react';
import { EditorContent } from '@tiptap/react';
import type { RefObject } from 'react';
import { useCallback, useEffect, useRef } from 'react';

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
}) {
    // Stable refs for cross-scene navigation callbacks (avoids editor re-creation)
    const onExitUpRef = useRef<(() => void) | null>(onExitUp ?? null);
    onExitUpRef.current = onExitUp ?? null;
    const onExitDownRef = useRef<(() => void) | null>(onExitDown ?? null);
    onExitDownRef.current = onExitDown ?? null;

    // Content auto-save
    const contentAbortRef = useRef<AbortController | null>(null);
    const contentTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const pendingContentRef = useRef<string | null>(null);

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
                updateContent.url({ book: bookId, chapter: chapterId, scene: scene.id }),
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
                const data = await response.json();
                onWordCountChange(scene.id, data.word_count);
            } else {
                onSaveStatusChange?.('error');
            }
        } catch (e) {
            if ((e as Error).name !== 'AbortError') {
                onSaveStatusChange?.('error');
            }
        }
    }, [bookId, chapterId, scene.id, onWordCountChange, onSaveStatusChange]);

    // Expose flush for parent
    const flushRef = useRef({ flushContentSave });
    flushRef.current = { flushContentSave };

    // Attach flush to the DOM node so parent can call it
    const containerRef = useRef<HTMLDivElement>(null);
    useEffect(() => {
        const el = containerRef.current;
        if (el) {
            (el as unknown as Record<string, unknown>).__flush = () => flushRef.current.flushContentSave();
        }

        // Flush pending saves on unmount
        return () => {
            if (contentTimerRef.current) clearTimeout(contentTimerRef.current);
            flushRef.current.flushContentSave();
        };
    }, []);

    const handleEditorUpdate = useCallback(
        (html: string, words: number) => {
            pendingContentRef.current = html;
            onWordCountChange(scene.id, words);
            onSaveStatusChange?.('saving');

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

    return (
        <div ref={containerRef} id={`scene-${scene.id}`}>
            {/* Scene divider (except first) */}
            {!isFirst && scenesVisible && (
                <div className="flex items-center justify-center py-2 select-none">
                    <span className="tracking-[0.6em] text-[9px] text-ink-faint/40">•&nbsp;•&nbsp;•</span>
                </div>
            )}

            {/* Scene header */}
            {scenesVisible && (
                <div className="mb-2">
                    <span className="text-[10px] tracking-[0.04em] text-ink-faint/50 select-none">
                        {scene.title}
                    </span>
                </div>
            )}

            {/* Editor */}
            <EditorContent editor={editor} />
        </div>
    );
}
