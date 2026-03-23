import CharacterCount from '@tiptap/extension-character-count';
import Placeholder from '@tiptap/extension-placeholder';
import TextAlign from '@tiptap/extension-text-align';
import Typography from '@tiptap/extension-typography';
import { useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import type { RefObject } from 'react';
import { useEffect, useRef } from 'react';
import { ParagraphEnterExtension } from '@/extensions/ParagraphEnterExtension';
import { SceneBridgeExtension } from '@/extensions/SceneBridgeExtension';
import { SearchHighlightExtension } from '@/extensions/SearchHighlightExtension';
import { TypewriterScrollExtension } from '@/extensions/TypewriterScrollExtension';

export default function useChapterEditor({
    content,
    onUpdate,
    scrollContainerRef,
    typewriterEnabledRef,
    onExitUpRef,
    onExitDownRef,
}: {
    content: string;
    onUpdate: (html: string, wordCount: number) => void;
    scrollContainerRef: RefObject<HTMLDivElement | null>;
    typewriterEnabledRef: RefObject<boolean>;
    onExitUpRef: RefObject<(() => void) | null>;
    onExitDownRef: RefObject<(() => void) | null>;
}) {
    const onUpdateRef = useRef(onUpdate);
    useEffect(() => {
        onUpdateRef.current = onUpdate;
    }, [onUpdate]);

    const editor = useEditor(
        {
            extensions: [
                StarterKit,
                Placeholder.configure({ placeholder: 'Start writing...' }),
                CharacterCount,
                Typography,
                TextAlign.configure({ types: ['heading', 'paragraph'] }),
                ParagraphEnterExtension,
                TypewriterScrollExtension.configure({
                    scrollContainerRef,
                    enabledRef: typewriterEnabledRef,
                }),
                SceneBridgeExtension.configure({
                    onExitUp: onExitUpRef,
                    onExitDown: onExitDownRef,
                }),
                SearchHighlightExtension,
            ],
            content,
            editorProps: {
                attributes: {
                    class: 'editor-prose',
                },
            },
            onUpdate: ({ editor }) => {
                const html = editor.getHTML();
                const words = editor.storage.characterCount.words();
                onUpdateRef.current(html, words);
            },
        },
        [content],
    );

    return editor;
}
