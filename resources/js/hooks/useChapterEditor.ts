import CharacterCount from '@tiptap/extension-character-count';
import Placeholder from '@tiptap/extension-placeholder';
import TextAlign from '@tiptap/extension-text-align';
import Typography from '@tiptap/extension-typography';
import Underline from '@tiptap/extension-underline';
import { useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { useRef } from 'react';

export default function useChapterEditor({
    content,
    onUpdate,
}: {
    content: string;
    onUpdate: (html: string, wordCount: number) => void;
}) {
    const onUpdateRef = useRef(onUpdate);
    onUpdateRef.current = onUpdate;

    const editor = useEditor(
        {
            extensions: [
                StarterKit,
                Placeholder.configure({ placeholder: 'Start writing...' }),
                CharacterCount,
                Typography,
                Underline,
                TextAlign.configure({ types: ['heading', 'paragraph'] }),
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
