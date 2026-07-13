import Placeholder from '@tiptap/extension-placeholder';
import Typography from '@tiptap/extension-typography';
import { useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import type { RefObject } from 'react';
import { useEffect, useMemo, useRef } from 'react';
import { SceneBridgeExtension } from '@/extensions/SceneBridgeExtension';
import { SearchHighlightExtension } from '@/extensions/SearchHighlightExtension';
import {
    SpellcheckExtension,
    spellcheckPluginKey,
} from '@/extensions/SpellcheckExtension';
import {
    StyleAnalysisExtension,
    styleAnalysisPluginKey,
} from '@/extensions/StyleAnalysisExtension';
import { TypewriterScrollExtension } from '@/extensions/TypewriterScrollExtension';
import type { AnalyzeOptions, StyleAnalysis } from '@/lib/style/types';
import { countWords } from '@/lib/wordCount';

export default function useChapterEditor({
    content,
    onUpdate,
    scrollContainerRef,
    typewriterEnabledRef,
    onExitUpRef,
    onExitDownRef,
    language,
    spellcheckEnabled = true,
    customWords = [],
    onAddToDictionary,
    styleAnalysisActive = false,
    styleAnalyzeOptions,
    onStyleAnalysis,
    onIgnoreStyleWord,
}: {
    content: string;
    onUpdate: (html: string, wordCount: number) => void;
    scrollContainerRef: RefObject<HTMLDivElement | null>;
    typewriterEnabledRef: RefObject<boolean>;
    onExitUpRef: RefObject<(() => void) | null>;
    onExitDownRef: RefObject<(() => void) | null>;
    language?: string;
    spellcheckEnabled?: boolean;
    customWords?: string[];
    onAddToDictionary?: (word: string) => void;
    styleAnalysisActive?: boolean;
    styleAnalyzeOptions?: AnalyzeOptions;
    onStyleAnalysis?: (analysis: StyleAnalysis | null) => void;
    onIgnoreStyleWord?: (word: string) => void;
}) {
    const onUpdateRef = useRef(onUpdate);
    useEffect(() => {
        onUpdateRef.current = onUpdate;
    }, [onUpdate]);

    // Stable refs so the Spellcheck/StyleAnalysis plugins can read the latest
    // values without requiring editor re-creation on toggle.
    const spellcheckEnabledRef = useRef(spellcheckEnabled);
    useEffect(() => {
        spellcheckEnabledRef.current = spellcheckEnabled;
    }, [spellcheckEnabled]);

    const styleActiveRef = useRef(styleAnalysisActive);
    useEffect(() => {
        styleActiveRef.current = styleAnalysisActive;
    }, [styleAnalysisActive]);

    const styleOptionsRef = useRef<AnalyzeOptions>(styleAnalyzeOptions ?? {});
    useEffect(() => {
        styleOptionsRef.current = styleAnalyzeOptions ?? {};
    }, [styleAnalyzeOptions]);

    const editor = useEditor(
        {
            extensions: [
                StarterKit,
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
                StyleAnalysisExtension.configure({
                    language: language ?? 'en',
                    activeRef: styleActiveRef,
                    analyzeOptionsRef: styleOptionsRef,
                    onAnalysis: onStyleAnalysis,
                    onIgnoreWord: onIgnoreStyleWord,
                }),
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
        [content],
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

    // Toggle revision-mode decorations without recreating the editor.
    useEffect(() => {
        if (!editor || editor.isDestroyed) return;
        editor.view.dispatch(
            editor.state.tr
                .setMeta(styleAnalysisPluginKey, {
                    type: 'set-active',
                    active: styleAnalysisActive,
                })
                .setMeta('addToHistory', false),
        );
    }, [editor, styleAnalysisActive]);

    // Category mutes / ignored words changed — re-run the analysis.
    const styleOptionsKey = useMemo(
        () => JSON.stringify(styleAnalyzeOptions ?? {}),
        [styleAnalyzeOptions],
    );
    useEffect(() => {
        if (!editor || editor.isDestroyed) return;
        editor.view.dispatch(
            editor.state.tr
                .setMeta(styleAnalysisPluginKey, { type: 'reanalyze' })
                .setMeta('addToHistory', false),
        );
    }, [editor, styleOptionsKey]);

    return editor;
}
