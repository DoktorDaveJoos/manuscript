import Placeholder from '@tiptap/extension-placeholder';
import TextAlign from '@tiptap/extension-text-align';
import Typography from '@tiptap/extension-typography';
import { useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import type { RefObject } from 'react';
import { useEffect, useMemo, useRef } from 'react';
import { ProofreadExtension } from '@/extensions/ProofreadExtension';
import { SceneBridgeExtension } from '@/extensions/SceneBridgeExtension';
import { SearchHighlightExtension } from '@/extensions/SearchHighlightExtension';
import {
    SpellcheckExtension,
    spellcheckPluginKey,
} from '@/extensions/SpellcheckExtension';
import { TypewriterScrollExtension } from '@/extensions/TypewriterScrollExtension';
import { countWords } from '@/lib/wordCount';
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
    customWords = [],
    onAddToDictionary,
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
    customWords?: string[];
    onAddToDictionary?: (word: string) => void;
}) {
    const onUpdateRef = useRef(onUpdate);
    useEffect(() => {
        onUpdateRef.current = onUpdate;
    }, [onUpdate]);

    // Stable ref so the SpellcheckExtension plugin can read the latest
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
                // No defaultAlignment: unaligned blocks carry no inline style,
                // so saved HTML stays clean and downstream surfaces (export,
                // book preview) keep control over default alignment.
                TextAlign.configure({
                    types: ['heading', 'paragraph'],
                    alignments: ['left', 'center', 'right'],
                }),
                Placeholder.configure({ placeholder: 'Start writing...' }),
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
                SpellcheckExtension.configure({
                    language: language ?? 'en',
                    enabledRef: spellcheckEnabledRef,
                    customWords,
                    onAddToDictionary,
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
                    spellcheck: 'false',
                },
            },
            onUpdate: ({ editor }) => {
                const html = editor.getHTML();
                const words = countWords(
                    editor.state.doc.textBetween(
                        0,
                        editor.state.doc.content.size,
                        ' ',
                        ' ',
                    ),
                );
                onUpdateRef.current(html, words);
            },
        },
        [content, proofreadingKey],
    );

    // Toggle spellcheck decorations without recreating the editor.
    useEffect(() => {
        if (!editor || editor.isDestroyed) return;
        editor.view.dispatch(
            editor.state.tr
                .setMeta(spellcheckPluginKey, {
                    type: 'set-enabled',
                    enabled: spellcheckEnabled,
                })
                .setMeta('addToHistory', false),
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
        // Intentionally monkey-patch the ProseMirror view's dispatch to swallow
        // the documented stale-proofread race described above; it cannot be
        // moved into the editor hook since it patches a third-party view.
        // eslint-disable-next-line react-hooks/immutability
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
