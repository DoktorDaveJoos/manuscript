import CharacterCount from '@tiptap/extension-character-count';
import Placeholder from '@tiptap/extension-placeholder';
import Typography from '@tiptap/extension-typography';
import { useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import type { RefObject } from 'react';
import { useEffect, useMemo, useRef } from 'react';
import { ProofreadExtension } from '@/extensions/ProofreadExtension';
import { SceneBridgeExtension } from '@/extensions/SceneBridgeExtension';
import { SearchHighlightExtension } from '@/extensions/SearchHighlightExtension';
import { TypewriterScrollExtension } from '@/extensions/TypewriterScrollExtension';
import type { ProofreadingConfig } from '@/types/models';

export default function useChapterEditor({
    content,
    onUpdate,
    scrollContainerRef,
    typewriterEnabledRef,
    onExitUpRef,
    onExitDownRef,
    proofreadingConfig,
    customDictionaryRef,
    onAddToDictionary,
}: {
    content: string;
    onUpdate: (html: string, wordCount: number) => void;
    scrollContainerRef: RefObject<HTMLDivElement | null>;
    typewriterEnabledRef: RefObject<boolean>;
    onExitUpRef: RefObject<(() => void) | null>;
    onExitDownRef: RefObject<(() => void) | null>;
    proofreadingConfig?: ProofreadingConfig;
    customDictionaryRef?: RefObject<string[]>;
    onAddToDictionary?: (word: string) => void;
}) {
    const onUpdateRef = useRef(onUpdate);
    useEffect(() => {
        onUpdateRef.current = onUpdate;
    }, [onUpdate]);

    // Stable key for proofreading config — only config changes re-create the editor.
    // Dictionary uses a ref so adding words doesn't destroy cursor/undo state.
    const proofreadingKey = useMemo(
        () => JSON.stringify(proofreadingConfig ?? null),
        [proofreadingConfig],
    );

    const editor = useEditor(
        {
            extensions: [
                StarterKit,
                Placeholder.configure({ placeholder: 'Start writing...' }),
                CharacterCount,
                Typography,
                TypewriterScrollExtension.configure({
                    scrollContainerRef,
                    enabledRef: typewriterEnabledRef,
                }),
                SceneBridgeExtension.configure({
                    onExitUp: onExitUpRef,
                    onExitDown: onExitDownRef,
                }),
                SearchHighlightExtension,

                ...(proofreadingConfig
                    ? [
                          ProofreadExtension.configure({
                              config: proofreadingConfig,
                              customDictionaryRef: customDictionaryRef ?? {
                                  current: [],
                              },
                              onAddToDictionary,
                          }),
                      ]
                    : []),
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
        [content, proofreadingKey],
    );

    return editor;
}
