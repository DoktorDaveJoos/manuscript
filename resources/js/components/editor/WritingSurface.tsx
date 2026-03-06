import type { Scene } from '@/types/models';
import type { Editor } from '@tiptap/react';
import { useCallback, useEffect, useMemo, useRef } from 'react';
import { DEFAULT_FONT_ID, FONTS } from './FontSelector';
import SceneEditor from './SceneEditor';

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
}) {
    const fontFamily = useMemo(() => {
        return FONTS.find((f) => f.id === editorFont)?.family ?? FONTS[0].family;
    }, [editorFont]);

    const titleRef = useRef<HTMLHeadingElement>(null);
    const scrollContainerRef = useRef<HTMLDivElement>(null);

    // Sync title prop into contentEditable only when not actively editing
    useEffect(() => {
        const el = titleRef.current;
        if (!el || el === document.activeElement) return;
        el.innerHTML = titleToHtml(title);
    }, [title]);

    const handleTitleKeyDown = useCallback((e: React.KeyboardEvent) => {
        if (e.key === 'Enter' && e.shiftKey) {
            e.preventDefault();
            const firstScene = document.querySelector('[id^="scene-"] .ProseMirror');
            if (firstScene instanceof HTMLElement) firstScene.focus();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            document.execCommand('insertLineBreak');
        }
    }, []);

    const handleTitleInput = useCallback(() => {
        const text = titleRef.current?.innerText ?? '';
        onTitleUpdate(text);
    }, [onTitleUpdate]);

    const handleTitlePaste = useCallback((e: React.ClipboardEvent) => {
        e.preventDefault();
        const text = e.clipboardData.getData('text/plain');
        document.execCommand('insertText', false, text);
    }, []);

    // Typewriter scrolling: keep cursor vertically centered
    useEffect(() => {
        if (!activeEditor || !isTypewriterMode) return;

        const handleSelectionUpdate = () => {
            const container = scrollContainerRef.current;
            if (!container) return;

            const { from } = activeEditor.state.selection;
            const coords = activeEditor.view.coordsAtPos(from);
            const containerRect = container.getBoundingClientRect();
            const cursorRelativeY = coords.top - containerRect.top;
            const targetY = containerRect.height / 2;
            const scrollDelta = cursorRelativeY - targetY;

            container.scrollBy({ top: scrollDelta, behavior: 'smooth' });
        };

        activeEditor.on('selectionUpdate', handleSelectionUpdate);
        return () => {
            activeEditor.off('selectionUpdate', handleSelectionUpdate);
        };
    }, [activeEditor, isTypewriterMode]);

    const metadataParts: string[] = [];
    if (povCharacterName) metadataParts.push(`POV: ${povCharacterName}`);
    if (timelineLabel) metadataParts.push(`Timeline: ${timelineLabel}`);

    return (
        <div
            ref={scrollContainerRef}
            className="flex flex-1 justify-center overflow-y-auto"
            style={{ '--font-serif': fontFamily } as React.CSSProperties}
        >
            <div className={`w-full max-w-[660px] px-[30px] ${isTypewriterMode ? 'py-[50vh]' : 'py-12'}`}>
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
                            onFocus={onActiveEditorChange}
                            onWordCountChange={onWordCountChange}
                        />
                    ))}
                </div>
            </div>
        </div>
    );
}
