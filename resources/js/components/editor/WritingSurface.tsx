import type { Editor } from '@tiptap/react';
import { EditorContent } from '@tiptap/react';
import { useCallback, useEffect, useRef } from 'react';

export default function WritingSurface({
    editor,
    title,
    povCharacterName,
    timelineLabel,
    onTitleUpdate,
}: {
    editor: Editor | null;
    title: string;
    povCharacterName?: string | null;
    timelineLabel?: string | null;
    onTitleUpdate: (title: string) => void;
}) {
    const titleRef = useRef<HTMLHeadingElement>(null);

    const handleTitleKeyDown = useCallback(
        (e: React.KeyboardEvent) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                editor?.commands.focus('start');
            }
        },
        [editor],
    );

    const handleTitleInput = useCallback(() => {
        const text = titleRef.current?.textContent ?? '';
        onTitleUpdate(text);
    }, [onTitleUpdate]);

    useEffect(() => {
        return () => {
            editor?.destroy();
        };
    }, [editor]);

    const metadataParts: string[] = [];
    if (povCharacterName) {
        metadataParts.push(`POV: ${povCharacterName}`);
    }
    if (timelineLabel) {
        metadataParts.push(`Timeline: ${timelineLabel}`);
    }

    return (
        <div className="flex flex-1 justify-center overflow-y-auto">
            <div className="w-full max-w-[660px] px-[30px] py-12">
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
                    <EditorContent editor={editor} />
                </div>
            </div>
        </div>
    );
}
