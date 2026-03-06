import type { Scene } from '@/types/models';
import type { Editor } from '@tiptap/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import SceneEditor from './SceneEditor';

export default function WritingSurface({
    scenes,
    bookId,
    chapterId,
    title,
    povCharacterName,
    timelineLabel,
    onTitleUpdate,
    onActiveEditorChange,
    onWordCountChange,
    onAddScene,
    onDeleteScene,
    isTypewriterMode = false,
}: {
    scenes: Scene[];
    bookId: number;
    chapterId: number;
    title: string;
    povCharacterName?: string | null;
    timelineLabel?: string | null;
    onTitleUpdate: (title: string) => void;
    onActiveEditorChange: (editor: Editor) => void;
    onWordCountChange: (sceneId: number, count: number) => void;
    onAddScene: (afterPosition: number) => void;
    onDeleteScene: (sceneId: number) => void;
    isTypewriterMode?: boolean;
}) {
    const titleRef = useRef<HTMLHeadingElement>(null);
    const scrollContainerRef = useRef<HTMLDivElement>(null);
    const [activeEditor, setActiveEditor] = useState<Editor | null>(null);

    const handleTitleKeyDown = useCallback((e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const firstScene = document.querySelector('[id^="scene-"] .ProseMirror');
            if (firstScene instanceof HTMLElement) firstScene.focus();
        }
    }, []);

    const handleTitleInput = useCallback(() => {
        const text = titleRef.current?.textContent ?? '';
        onTitleUpdate(text);
    }, [onTitleUpdate]);

    const handleFocus = useCallback(
        (editor: Editor) => {
            setActiveEditor(editor);
            onActiveEditorChange(editor);
        },
        [onActiveEditorChange],
    );

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
        <div ref={scrollContainerRef} className="flex flex-1 justify-center overflow-y-auto">
            <div className={`w-full max-w-[660px] px-[30px] ${isTypewriterMode ? 'py-[50vh]' : 'py-12'}`}>
                <h1
                    ref={titleRef}
                    contentEditable
                    suppressContentEditableWarning
                    onKeyDown={handleTitleKeyDown}
                    onInput={handleTitleInput}
                    className="mb-0 font-serif text-[32px] leading-[1.3] font-semibold tracking-[-0.01em] text-ink outline-none"
                    dangerouslySetInnerHTML={{ __html: title }}
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
                            onFocus={handleFocus}
                            onWordCountChange={onWordCountChange}
                            onAddScene={onAddScene}
                            onDeleteScene={onDeleteScene}
                            canDelete={scenes.length > 1}
                        />
                    ))}
                </div>
                {scenes.length > 0 && (
                    <button
                        type="button"
                        onClick={() => onAddScene(scenes.length)}
                        className="mt-8 text-xs text-ink-faint transition-colors hover:text-ink"
                    >
                        + Add scene
                    </button>
                )}
            </div>
        </div>
    );
}
