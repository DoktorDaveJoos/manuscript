import {
    cancelTypewriterAnimation,
    centerCursorInContainer,
} from '@/extensions/TypewriterScrollExtension';
import type { Scene } from '@/types/models';
import type { Editor } from '@tiptap/core';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { DEFAULT_FONT_ID, FONTS } from './FontSelector';
import SceneEditor from './SceneEditor';

const NORMAL_PADDING_TOP = 48; // Tailwind pt-12

function titleToHtml(title: string): string {
    return title
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\n/g, '<br>');
}

export default function WritingSurface({
    scenes,
    bookId,
    chapterId,
    title,
    povCharacterName,
    timelineLabel,
    onTitleUpdate,
    activeEditor,
    onActiveEditorChange,
    onWordCountChange,
    isTypewriterMode = false,
    editorFont = DEFAULT_FONT_ID,
    pendingFocusSceneId,
    onFocusHandled,
    onActiveSceneIdChange,
    scenesVisible = true,
}: {
    scenes: Scene[];
    bookId: number;
    chapterId: number;
    title: string;
    povCharacterName?: string | null;
    timelineLabel?: string | null;
    onTitleUpdate: (title: string) => void;
    activeEditor: Editor | null;
    onActiveEditorChange: (editor: Editor) => void;
    onWordCountChange: (sceneId: number, count: number) => void;
    isTypewriterMode?: boolean;
    editorFont?: string;
    pendingFocusSceneId?: number | null;
    onFocusHandled?: () => void;
    onActiveSceneIdChange?: (sceneId: number) => void;
    scenesVisible?: boolean;
}) {
    const fontFamily = useMemo(() => {
        return FONTS.find((f) => f.id === editorFont)?.family ?? FONTS[0].family;
    }, [editorFont]);

    const titleRef = useRef<HTMLHeadingElement>(null);
    const scrollContainerRef = useRef<HTMLDivElement>(null);

    // Track scroll container height for dynamic padding
    const [containerHeight, setContainerHeight] = useState(0);
    const halfContainer = containerHeight / 2;
    const halfContainerRef = useRef(halfContainer);
    halfContainerRef.current = halfContainer;

    useEffect(() => {
        const el = scrollContainerRef.current;
        if (!el) return;
        const observer = new ResizeObserver((entries) => {
            for (const entry of entries) {
                const height = entry.contentBoxSize?.[0]?.blockSize ?? entry.contentRect.height;
                setContainerHeight(height);
            }
        });
        observer.observe(el);
        setContainerHeight(el.clientHeight);
        return () => observer.disconnect();
    }, []);

    // Editor registry for cross-scene navigation
    const editorRegistry = useRef<Map<number, Editor>>(new Map());

    const handleEditorReady = useCallback((sceneId: number, editor: Editor) => {
        editorRegistry.current.set(sceneId, editor);
        const cleanup = () => {
            editorRegistry.current.delete(sceneId);
        };
        editor.on('destroy', cleanup);
    }, []);

    // Sync title prop into contentEditable only when not actively editing
    useEffect(() => {
        const el = titleRef.current;
        if (!el || el === document.activeElement) return;
        el.innerHTML = titleToHtml(title);
    }, [title]);

    const handleTitleKeyDown = useCallback(
        (e: React.KeyboardEvent) => {
            if (e.key === 'Enter' && e.shiftKey) {
                e.preventDefault();
                if (scenes.length > 0) {
                    editorRegistry.current.get(scenes[0].id)?.commands.focus('start');
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                document.execCommand('insertLineBreak');
            } else if (e.key === 'ArrowDown') {
                const sel = window.getSelection();
                if (!sel || !titleRef.current) return;

                // Check if cursor is on the last visual line of the title
                const range = sel.getRangeAt(0);
                const rect = range.getBoundingClientRect();
                const titleRect = titleRef.current.getBoundingClientRect();
                const lineHeight = parseFloat(getComputedStyle(titleRef.current).lineHeight) || 20;

                if (titleRect.bottom - rect.bottom < lineHeight) {
                    e.preventDefault();
                    if (scenes.length > 0) {
                        const firstEditor = editorRegistry.current.get(scenes[0].id);
                        firstEditor?.commands.focus('start');
                    }
                }
            }
        },
        [scenes],
    );

    const handleTitleInput = useCallback(() => {
        const text = titleRef.current?.innerText ?? '';
        onTitleUpdate(text);
    }, [onTitleUpdate]);

    const handleTitlePaste = useCallback((e: React.ClipboardEvent) => {
        e.preventDefault();
        const text = e.clipboardData.getData('text/plain');
        document.execCommand('insertText', false, text);
    }, []);

    // Track isTypewriterMode in a ref so the ProseMirror plugin can read it
    const typewriterEnabledRef = useRef(isTypewriterMode);
    useEffect(() => {
        typewriterEnabledRef.current = isTypewriterMode;
    }, [isTypewriterMode]);

    // Previous typewriter state for detecting transitions
    const prevTypewriterRef = useRef(isTypewriterMode);

    // Initial centering when typewriter mode activates or active editor changes
    useEffect(() => {
        const justEnabled = isTypewriterMode && !prevTypewriterRef.current;
        const justDisabled = !isTypewriterMode && prevTypewriterRef.current;
        prevTypewriterRef.current = isTypewriterMode;

        if (justDisabled) {
            cancelTypewriterAnimation();
            const container = scrollContainerRef.current;
            if (container) {
                container.scrollTop = Math.max(
                    0,
                    container.scrollTop - (halfContainerRef.current - NORMAL_PADDING_TOP),
                );
            }
            return;
        }

        if (!isTypewriterMode || !activeEditor) {
            if (!isTypewriterMode) {
                cancelTypewriterAnimation();
            }
            return;
        }

        if (justEnabled) {
            const container = scrollContainerRef.current;
            if (container) {
                container.scrollTop += halfContainerRef.current - NORMAL_PADDING_TOP;
            }
        }

        // Center the cursor (instant on mode activation)
        requestAnimationFrame(() => {
            if (!activeEditor?.view || !scrollContainerRef.current) return;
            centerCursorInContainer(activeEditor.view, scrollContainerRef.current, justEnabled);
        });
    }, [isTypewriterMode, activeEditor]);

    // Re-center cursor when container resizes (focus mode toggle, window resize)
    useEffect(() => {
        if (!isTypewriterMode || !activeEditor?.view || !scrollContainerRef.current) return;
        requestAnimationFrame(() => {
            if (!activeEditor?.view || !scrollContainerRef.current) return;
            centerCursorInContainer(activeEditor.view, scrollContainerRef.current, true);
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only re-center on actual resize
    }, [containerHeight]);

    useEffect(() => {
        if (!pendingFocusSceneId) return;
        requestAnimationFrame(() => {
            editorRegistry.current.get(pendingFocusSceneId)?.commands.focus();
            onFocusHandled?.();
        });
    }, [pendingFocusSceneId, onFocusHandled]);

    const metadataParts: string[] = [];
    if (povCharacterName) metadataParts.push(`POV: ${povCharacterName}`);
    if (timelineLabel) metadataParts.push(`Timeline: ${timelineLabel}`);

    return (
        <div
            ref={scrollContainerRef}
            className="flex flex-1 items-start justify-center overflow-y-auto"
            style={{ '--font-serif': fontFamily } as React.CSSProperties}
        >
            <div
                className="w-full max-w-[660px] px-[30px]"
                style={{
                    paddingTop: isTypewriterMode ? halfContainer : NORMAL_PADDING_TOP,
                    paddingBottom: halfContainer,
                    minHeight: containerHeight + halfContainer,
                }}
            >
                <h1
                    ref={titleRef}
                    contentEditable
                    suppressContentEditableWarning
                    onKeyDown={handleTitleKeyDown}
                    onInput={handleTitleInput}
                    onPaste={handleTitlePaste}
                    className="mb-0 font-serif text-[32px] leading-[1.3] font-semibold tracking-[-0.01em] text-ink outline-none"
                />
                {metadataParts.length > 0 && (
                    <p className="mt-2 mb-0 font-sans text-sm tracking-wide text-ink-muted">
                        {metadataParts.join(' · ')}
                    </p>
                )}
                <div className="mt-8">
                    {scenes.map((scene, i) => (
                        <SceneEditor
                            key={scene.id}
                            scene={scene}
                            bookId={bookId}
                            chapterId={chapterId}
                            isFirst={i === 0}
                            onFocus={(editor) => {
                                onActiveEditorChange(editor);
                                onActiveSceneIdChange?.(scene.id);
                            }}
                            onEditorReady={handleEditorReady}
                            onExitUp={
                                i === 0
                                    ? () => {
                                          const el = titleRef.current;
                                          if (!el) return;
                                          el.focus();
                                          // Place cursor at end of title
                                          const sel = window.getSelection();
                                          if (sel) {
                                              const range = document.createRange();
                                              range.selectNodeContents(el);
                                              range.collapse(false);
                                              sel.removeAllRanges();
                                              sel.addRange(range);
                                          }
                                      }
                                    : () => {
                                          const prevId = scenes[i - 1].id;
                                          editorRegistry.current.get(prevId)?.commands.focus('end');
                                      }
                            }
                            onExitDown={
                                i < scenes.length - 1
                                    ? () => {
                                          const nextId = scenes[i + 1].id;
                                          editorRegistry.current.get(nextId)?.commands.focus('start');
                                      }
                                    : undefined
                            }
                            onWordCountChange={onWordCountChange}
                            scrollContainerRef={scrollContainerRef}
                            typewriterEnabledRef={typewriterEnabledRef}
                            scenesVisible={scenesVisible}
                        />
                    ))}
                </div>
            </div>
        </div>
    );
}
