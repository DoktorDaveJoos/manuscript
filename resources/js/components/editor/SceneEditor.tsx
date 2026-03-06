import { updateContent, updateTitle } from '@/actions/App/Http/Controllers/SceneController';
import useChapterEditor from '@/hooks/useChapterEditor';
import { getXsrfToken } from '@/lib/csrf';
import type { Scene } from '@/types/models';
import type { Editor } from '@tiptap/react';
import { EditorContent } from '@tiptap/react';
import { useCallback, useEffect, useRef, useState } from 'react';

export default function SceneEditor({
    scene,
    bookId,
    chapterId,
    isFirst,
    onFocus,
    onWordCountChange,
    onAddScene,
    onDeleteScene,
    canDelete,
}: {
    scene: Scene;
    bookId: number;
    chapterId: number;
    isFirst: boolean;
    onFocus: (editor: Editor) => void;
    onWordCountChange: (sceneId: number, count: number) => void;
    onAddScene: (afterPosition: number) => void;
    onDeleteScene: (sceneId: number) => void;
    canDelete: boolean;
}) {
    const [showActions, setShowActions] = useState(false);
    const titleRef = useRef<HTMLSpanElement>(null);

    // Content auto-save
    const contentAbortRef = useRef<AbortController | null>(null);
    const contentTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const pendingContentRef = useRef<string | null>(null);

    // Title auto-save
    const titleTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const pendingTitleRef = useRef<string | null>(null);

    const flushContentSave = useCallback(async () => {
        if (contentTimerRef.current) {
            clearTimeout(contentTimerRef.current);
            contentTimerRef.current = null;
        }

        const content = pendingContentRef.current;
        if (content === null) return;
        pendingContentRef.current = null;

        contentAbortRef.current?.abort();
        const controller = new AbortController();
        contentAbortRef.current = controller;

        try {
            const response = await fetch(
                updateContent.url({ book: bookId, chapter: chapterId, scene: scene.id }),
                {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                    },
                    body: JSON.stringify({ content }),
                    signal: controller.signal,
                },
            );

            if (response.ok) {
                const data = await response.json();
                onWordCountChange(scene.id, data.word_count);
            }
        } catch {
            // Ignore abort errors
        }
    }, [bookId, chapterId, scene.id, onWordCountChange]);

    const flushTitleSave = useCallback(async () => {
        if (titleTimerRef.current) {
            clearTimeout(titleTimerRef.current);
            titleTimerRef.current = null;
        }

        const title = pendingTitleRef.current;
        if (title === null) return;
        pendingTitleRef.current = null;

        try {
            await fetch(updateTitle.url({ book: bookId, chapter: chapterId, scene: scene.id }), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                body: JSON.stringify({ title }),
            });
        } catch {
            // Ignore errors
        }
    }, [bookId, chapterId, scene.id]);

    // Expose flush for parent
    const flushRef = useRef({ flushContentSave, flushTitleSave });
    flushRef.current = { flushContentSave, flushTitleSave };

    // Attach flush to the DOM node so parent can call it
    const containerRef = useRef<HTMLDivElement>(null);
    useEffect(() => {
        const el = containerRef.current;
        if (el) {
            (el as unknown as Record<string, unknown>).__flush = () =>
                Promise.all([flushRef.current.flushContentSave(), flushRef.current.flushTitleSave()]);
        }
    }, []);

    const handleEditorUpdate = useCallback(
        (html: string, words: number) => {
            pendingContentRef.current = html;
            onWordCountChange(scene.id, words);

            if (contentTimerRef.current) {
                clearTimeout(contentTimerRef.current);
            }
            contentTimerRef.current = setTimeout(() => {
                flushContentSave();
            }, 1500);
        },
        [scene.id, onWordCountChange, flushContentSave],
    );

    const editor = useChapterEditor({
        content: scene.content ?? '',
        onUpdate: handleEditorUpdate,
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

    const handleTitleInput = useCallback(() => {
        const text = titleRef.current?.textContent ?? '';
        pendingTitleRef.current = text;

        if (titleTimerRef.current) {
            clearTimeout(titleTimerRef.current);
        }
        titleTimerRef.current = setTimeout(() => {
            flushTitleSave();
        }, 1500);
    }, [flushTitleSave]);

    const handleTitleKeyDown = useCallback(
        (e: React.KeyboardEvent) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                editor?.commands.focus('start');
            }
        },
        [editor],
    );

    return (
        <div
            ref={containerRef}
            id={`scene-${scene.id}`}
            className="relative"
            onMouseEnter={() => setShowActions(true)}
            onMouseLeave={() => setShowActions(false)}
        >
            {/* Scene divider (except first) */}
            {!isFirst && (
                <div className="flex items-center justify-center py-6 text-ink-faint select-none">
                    <span className="tracking-[0.3em] text-xs">•&nbsp;&nbsp;•&nbsp;&nbsp;•</span>
                </div>
            )}

            {/* Scene header */}
            <div className="mb-3 flex items-center gap-2">
                <span
                    ref={titleRef}
                    contentEditable
                    suppressContentEditableWarning
                    onInput={handleTitleInput}
                    onKeyDown={handleTitleKeyDown}
                    className="text-xs font-medium uppercase tracking-[0.06em] text-ink-faint outline-none"
                    dangerouslySetInnerHTML={{ __html: scene.title }}
                />

                {showActions && (
                    <div className="flex items-center gap-1">
                        <button
                            type="button"
                            onClick={() => onAddScene(scene.sort_order + 1)}
                            title="Add scene below"
                            className="flex h-5 w-5 items-center justify-center rounded text-ink-faint transition-colors hover:text-ink"
                        >
                            <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                        </button>
                        {canDelete && (
                            <button
                                type="button"
                                onClick={() => onDeleteScene(scene.id)}
                                title="Delete scene"
                                className="flex h-5 w-5 items-center justify-center rounded text-ink-faint transition-colors hover:text-red-600"
                            >
                                <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        )}
                    </div>
                )}
            </div>

            {/* Editor */}
            <EditorContent editor={editor} />
        </div>
    );
}
