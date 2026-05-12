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
import { SpellcheckContextMenu } from '@/extensions/SpellcheckContextMenu';
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
    language,
    spellcheckEnabled = true,
}: {
    content: string;
    onUpdate: (html: string, wordCount: number) => void;
    scrollContainerRef: RefObject<HTMLDivElement | null>;
    typewriterEnabledRef: RefObject<boolean>;
    onExitUpRef: RefObject<(() => void) | null>;
    onExitDownRef: RefObject<(() => void) | null>;
    proofreadingConfig?: ProofreadingConfig;
    language?: string;
    spellcheckEnabled?: boolean;
}) {
    const onUpdateRef = useRef(onUpdate);
    useEffect(() => {
        onUpdateRef.current = onUpdate;
    }, [onUpdate]);

    // Stable ref so the SpellcheckContextMenu plugin can read the latest
    // value without requiring editor re-creation on toggle.
    const spellcheckEnabledRef = useRef(spellcheckEnabled);
    useEffect(() => {
        spellcheckEnabledRef.current = spellcheckEnabled;
    }, [spellcheckEnabled]);

    const proofreadingKey = useMemo(
        () => JSON.stringify({ config: proofreadingConfig ?? null, language }),
        [proofreadingConfig, language],
    );

    const editor = useEditor(
        {
            extensions: [
                StarterKit,
                Placeholder.configure({ placeholder: 'Start writing...' }),
                CharacterCount,
                Typography,
                TypewriterScrollExtension.configure({
                    isEnabled: () => typewriterEnabledRef.current,
                    getScrollContainer: () => scrollContainerRef.current,
                }),
                SceneBridgeExtension.configure({
                    onExitUp: onExitUpRef,
                    onExitDown: onExitDownRef,
                }),
                SearchHighlightExtension,
                SpellcheckContextMenu.configure({
                    enabledRef: spellcheckEnabledRef,
                }),

                ...(proofreadingConfig
                    ? [
                          ProofreadExtension.configure({
                              config: proofreadingConfig,
                              language: language ?? 'en',
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

    // Toggle the spellcheck DOM attribute without recreating the editor
    useEffect(() => {
        if (!editor || editor.isDestroyed) return;
        editor.view.dom.setAttribute(
            'spellcheck',
            spellcheckEnabled ? 'true' : 'false',
        );
    }, [editor, spellcheckEnabled]);

    // prosemirror-proofread builds a decoration update from a snapshotted
    // doc and dispatches after async grammar checks. During high-throughput
    // inserts (continue-writing SSE), the snapshot races with new
    // transactions and ProseMirror throws "Applying a mismatched transaction".
    // The stale decorations are safe to drop — the next check cycle recomputes.
    useEffect(() => {
        if (!editor || editor.isDestroyed) return;
        const view = editor.view;
        const originalDispatch = view.dispatch.bind(view);
        view.dispatch = (tr) => {
            try {
                originalDispatch(tr);
            } catch (e) {
                if (
                    e instanceof RangeError &&
                    /mismatched transaction/i.test(e.message) &&
                    tr.getMeta('proofread')
                ) {
                    return;
                }
                throw e;
            }
        };
        return () => {
            if (!editor.isDestroyed) {
                view.dispatch = originalDispatch;
            }
        };
    }, [editor]);

    return editor;
}
