import type { Editor } from '@tiptap/core';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { SaveStatus } from '@/components/editor/EditorBar';
import type { SearchHighlight } from '@/extensions/SearchHighlightExtension';
import {
    cancelTypewriterAnimation,
    centerCursorInContainer,
} from '@/extensions/TypewriterScrollExtension';
import type { Scene } from '@/types/models';
import ChapterFindBar from './ChapterFindBar';
import { DEFAULT_FONT_ID, FONTS } from './FontSelector';
import { DEFAULT_FONT_SIZE } from './FontSizeSelector';
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
    onSaveStatusChange,
    isTypewriterMode = false,
    editorFont = DEFAULT_FONT_ID,
    editorFontSize = DEFAULT_FONT_SIZE,
    pendingFocusSceneId,
    onFocusHandled,
    onActiveSceneIdChange,
    scenesVisible = true,
    searchHighlight,
    isLocalFindOpen = false,
    localFindShowReplace = false,
    onLocalFindClose,
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
    onSaveStatusChange?: (status: SaveStatus) => void;
    isTypewriterMode?: boolean;
    editorFont?: string;
    editorFontSize?: number;
    pendingFocusSceneId?: number | null;
    onFocusHandled?: () => void;
    onActiveSceneIdChange?: (sceneId: number) => void;
    scenesVisible?: boolean;
    searchHighlight?: SearchHighlight | null;
    isLocalFindOpen?: boolean;
    localFindShowReplace?: boolean;
    onLocalFindClose?: () => void;
}) {
    const { t } = useTranslation('editor');

    const fontFamily = useMemo(() => {
        return (
            FONTS.find((f) => f.id === editorFont)?.family ?? FONTS[0].family
        );
    }, [editorFont]);

    const titleRef = useRef<HTMLHeadingElement>(null);
    const scrollContainerRef = useRef<HTMLDivElement>(null);

    // Track scroll container height for dynamic padding
    const [containerHeight, setContainerHeight] = useState(0);
    const halfContainer = containerHeight / 2;
    useEffect(() => {
        const el = scrollContainerRef.current;
        if (!el) return;
        const observer = new ResizeObserver((entries) => {
            for (const entry of entries) {
                const height =
                    entry.contentBoxSize?.[0]?.blockSize ??
                    entry.contentRect.height;
                setContainerHeight(Math.round(height));
            }
        });
        observer.observe(el);
        setContainerHeight(Math.round(el.clientHeight));
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
                    editorRegistry.current
                        .get(scenes[0].id)
                        ?.commands.focus('start');
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
                const lineHeight =
                    parseFloat(getComputedStyle(titleRef.current).lineHeight) ||
                    20;

                if (titleRect.bottom - rect.bottom < lineHeight) {
                    e.preventDefault();
                    if (scenes.length > 0) {
                        const firstEditor = editorRegistry.current.get(
                            scenes[0].id,
                        );
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

    // Instant-center when typewriter mode is toggled on, or cancel when off
    const prevTypewriterRef = useRef(isTypewriterMode);
    useEffect(() => {
        const justEnabled = isTypewriterMode && !prevTypewriterRef.current;
        prevTypewriterRef.current = isTypewriterMode;

        if (!isTypewriterMode) {
            cancelTypewriterAnimation();
            return;
        }

        if (!activeEditor?.view || !scrollContainerRef.current) return;

        if (justEnabled) {
            requestAnimationFrame(() => {
                if (!activeEditor?.view || !scrollContainerRef.current) return;
                centerCursorInContainer(
                    activeEditor.view,
                    scrollContainerRef.current,
                    true,
                );
            });
        }
    }, [isTypewriterMode, activeEditor]);

    // Re-center cursor when container resizes (focus mode toggle, window resize)
    useEffect(() => {
        if (
            !isTypewriterMode ||
            !activeEditor?.view ||
            !scrollContainerRef.current
        )
            return;
        requestAnimationFrame(() => {
            if (!activeEditor?.view || !scrollContainerRef.current) return;
            centerCursorInContainer(
                activeEditor.view,
                scrollContainerRef.current,
                true,
            );
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
    if (povCharacterName)
        metadataParts.push(t('writingSurface.pov', { name: povCharacterName }));
    if (timelineLabel)
        metadataParts.push(
            t('writingSurface.timeline', { label: timelineLabel }),
        );

    return (
        <div className="relative flex-1 overflow-hidden">
            {isLocalFindOpen && (
                <ChapterFindBar
                    editorRegistry={editorRegistry}
                    scenes={scenes}
                    scrollContainerRef={scrollContainerRef}
                    showReplace={localFindShowReplace}
                    onClose={onLocalFindClose ?? (() => {})}
                />
            )}
            <div
                ref={scrollContainerRef}
                className="flex size-full items-start justify-center overflow-y-auto"
                style={
                    {
                        '--font-serif': fontFamily,
                        '--editor-font-size': `${editorFontSize}px`,
                        overflowAnchor: 'none',
                    } as React.CSSProperties
                }
            >
                <div
                    className="w-full max-w-[660px] px-[30px]"
                    style={{
                        paddingTop: NORMAL_PADDING_TOP,
                        paddingBottom: halfContainer,
                        minHeight: containerHeight,
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
                                                  const range =
                                                      document.createRange();
                                                  range.selectNodeContents(el);
                                                  range.collapse(false);
                                                  sel.removeAllRanges();
                                                  sel.addRange(range);
                                              }
                                          }
                                        : () => {
                                              const prevId = scenes[i - 1].id;
                                              editorRegistry.current
                                                  .get(prevId)
                                                  ?.commands.focus('end');
                                          }
                                }
                                onExitDown={
                                    i < scenes.length - 1
                                        ? () => {
                                              const nextId = scenes[i + 1].id;
                                              editorRegistry.current
                                                  .get(nextId)
                                                  ?.commands.focus('start');
                                          }
                                        : undefined
                                }
                                onWordCountChange={onWordCountChange}
                                onSaveStatusChange={onSaveStatusChange}
                                scrollContainerRef={scrollContainerRef}
                                typewriterEnabledRef={typewriterEnabledRef}
                                scenesVisible={scenesVisible}
                                searchHighlight={
                                    isLocalFindOpen ? null : searchHighlight
                                }
                            />
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
