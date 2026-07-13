import { Extension } from '@tiptap/core';
import type { Node as PMNode } from '@tiptap/pm/model';
import type { EditorState, Transaction } from '@tiptap/pm/state';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import type { EditorView } from '@tiptap/pm/view';
import { Decoration, DecorationSet } from '@tiptap/pm/view';
import type { RefObject } from 'react';
import { createStylePopover } from '@/components/editor/StylePopover';
import type { SpellcheckClient } from '@/lib/spellcheck/client';
import { getSpellcheckClient } from '@/lib/spellcheck/client';
import type {
    AnalyzeOptions,
    StyleAnalysis,
    StyleCategory,
    StyleFinding,
} from '@/lib/style/types';

const DEBOUNCE_MS = 500;
// Same placeholder trick as SpellcheckExtension: keeps text offsets aligned
// with document positions across non-text leaves (e.g. hardBreak).
const LEAF_CHAR = '￼';
const BLOCK_JOIN = '\n\n';

const CATEGORY_CLASS: Record<StyleCategory, string> = {
    filler: 'style-filler',
    weakVerb: 'style-weak-verb',
    filterWord: 'style-filter-word',
    cliche: 'style-cliche',
    pattern: 'style-pattern',
    repetition: 'style-repetition',
};

/** Word-level categories where per-word ignore makes sense. */
const IGNORABLE_CATEGORIES: ReadonlySet<StyleCategory> = new Set([
    'filler',
    'weakVerb',
    'filterWord',
    'repetition',
]);

interface DocFinding {
    from: number; // document position
    to: number;
    finding: StyleFinding;
}

interface StylePluginState {
    decorations: DecorationSet;
    active: boolean;
    /** Increments on every doc change; stale analysis results are dropped. */
    version: number;
    dirty: boolean;
}

type StyleMeta =
    | { type: 'set-active'; active: boolean }
    | { type: 'reanalyze' }
    | { type: 'clear-dirty' }
    | { type: 'results'; version: number; docFindings: DocFinding[] };

export const styleAnalysisPluginKey = new PluginKey<StylePluginState>(
    'styleAnalysis',
);

function blockText(node: PMNode): string {
    return node.textBetween(0, node.content.size, undefined, LEAF_CHAR);
}

function applyTransaction(
    tr: Transaction,
    prev: StylePluginState,
): StylePluginState {
    const next: StylePluginState = { ...prev };

    if (tr.docChanged) {
        next.version = prev.version + 1;
        if (next.active) {
            next.decorations = next.decorations.map(tr.mapping, tr.doc);
            next.dirty = true;
        }
    }

    const meta = tr.getMeta(styleAnalysisPluginKey) as StyleMeta | undefined;
    if (!meta) return next;

    switch (meta.type) {
        case 'set-active':
            next.active = meta.active;
            next.decorations = DecorationSet.empty;
            next.dirty = meta.active;
            break;
        case 'reanalyze':
            if (next.active) next.dirty = true;
            break;
        case 'clear-dirty':
            next.dirty = false;
            break;
        case 'results': {
            // Analysis of a stale doc — an edit already re-marked dirty.
            if (!next.active || meta.version !== next.version) break;
            next.decorations = DecorationSet.create(
                tr.doc,
                meta.docFindings.map(({ from, to, finding }) =>
                    Decoration.inline(
                        from,
                        to,
                        { class: CATEGORY_CLASS[finding.category] },
                        { finding },
                    ),
                ),
            );
            break;
        }
    }
    return next;
}

class StyleAnalysisView {
    private timer: number | null = null;
    private destroyed = false;
    private wasActive: boolean;

    constructor(
        private readonly view: EditorView,
        private readonly client: SpellcheckClient,
        private readonly optionsRef: RefObject<AnalyzeOptions> | undefined,
        private readonly onAnalysis?: (analysis: StyleAnalysis | null) => void,
    ) {
        this.wasActive =
            styleAnalysisPluginKey.getState(view.state)?.active ?? false;
        if (this.wasActive) this.schedule();
    }

    update(): void {
        const state = styleAnalysisPluginKey.getState(this.view.state);
        if (!state) return;
        if (this.wasActive && !state.active) this.notifyDeactivated();
        this.wasActive = state.active;
        if (state.active && state.dirty) this.schedule();
    }

    private schedule(): void {
        if (this.timer !== null) window.clearTimeout(this.timer);
        this.timer = window.setTimeout(() => {
            this.timer = null;
            this.flush();
        }, DEBOUNCE_MS);
    }

    private dispatchMeta(meta: StyleMeta): void {
        const tr = this.view.state.tr
            .setMeta(styleAnalysisPluginKey, meta)
            .setMeta('addToHistory', false);
        this.view.dispatch(tr);
    }

    notifyDeactivated(): void {
        if (this.timer !== null) window.clearTimeout(this.timer);
        this.timer = null;
        this.onAnalysis?.(null);
    }

    private flush(): void {
        if (this.destroyed) return;
        const state = styleAnalysisPluginKey.getState(this.view.state);
        if (!state || !state.active || !state.dirty) return;

        const doc = this.view.state.doc;
        const version = state.version;

        // Flatten the scene into one string so repetition windows and
        // sentence stats can span paragraphs; remember where each block
        // lands so findings map back to document positions.
        const blocks: Array<{
            flatFrom: number;
            docContentStart: number;
            length: number;
        }> = [];
        const parts: string[] = [];
        let flatLength = 0;
        doc.descendants((node, pos) => {
            if (!node.isTextblock) return true;
            const text = blockText(node);
            if (parts.length > 0) flatLength += BLOCK_JOIN.length;
            blocks.push({
                flatFrom: flatLength,
                docContentStart: pos + 1,
                length: text.length,
            });
            parts.push(text);
            flatLength += text.length;
            return false;
        });
        const flat = parts.join(BLOCK_JOIN);

        this.dispatchMeta({ type: 'clear-dirty' });

        const options = this.optionsRef?.current ?? {};
        void this.client.analyzeStyle(flat, options).then((analysis) => {
            if (this.destroyed) return;
            const docFindings: DocFinding[] = [];
            const toDoc = (offset: number): number | null => {
                const block = blocks.findLast((b) => offset >= b.flatFrom);
                if (!block || offset > block.flatFrom + block.length) {
                    return null;
                }
                return block.docContentStart + (offset - block.flatFrom);
            };
            for (const finding of analysis.findings) {
                const from = toDoc(finding.from);
                const to = toDoc(finding.to);
                // Drop the rare phrase match that crosses a paragraph break.
                if (from === null || to === null || to <= from) continue;
                docFindings.push({ from, to, finding });
            }
            this.dispatchMeta({ type: 'results', version, docFindings });
            this.onAnalysis?.(analysis);
        });
    }

    destroy(): void {
        this.destroyed = true;
        if (this.timer !== null) window.clearTimeout(this.timer);
    }
}

export interface StyleAnalysisOptions {
    language: string;
    activeRef?: RefObject<boolean>;
    /** Latest category gates + ignored words without recreating the editor. */
    analyzeOptionsRef?: RefObject<AnalyzeOptions>;
    /** Per-scene analysis bridge for the Style panel; null = mode left. */
    onAnalysis?: (analysis: StyleAnalysis | null) => void;
    onIgnoreWord?: (word: string) => void;
}

export const StyleAnalysisExtension = Extension.create<StyleAnalysisOptions>({
    name: 'styleAnalysis',

    addOptions() {
        return {
            language: 'en',
            activeRef: undefined,
            analyzeOptionsRef: undefined,
            onAnalysis: undefined,
            onIgnoreWord: undefined,
        };
    },

    addProseMirrorPlugins() {
        const {
            language,
            activeRef,
            analyzeOptionsRef,
            onAnalysis,
            onIgnoreWord,
        } = this.options;

        const client = getSpellcheckClient(language);
        if (!client) return [];

        let activePopover: { destroy: () => void } | null = null;

        return [
            new Plugin<StylePluginState>({
                key: styleAnalysisPluginKey,
                state: {
                    init: (_config, _state: EditorState) => ({
                        decorations: DecorationSet.empty,
                        active: activeRef?.current ?? false,
                        version: 0,
                        dirty: activeRef?.current ?? false,
                    }),
                    apply: applyTransaction,
                },
                props: {
                    decorations(state) {
                        return styleAnalysisPluginKey.getState(state)
                            ?.decorations;
                    },
                    handleClick(view, _pos, event) {
                        activePopover?.destroy();
                        activePopover = null;

                        const pluginState = styleAnalysisPluginKey.getState(
                            view.state,
                        );
                        if (!pluginState?.active) return false;

                        const pos = view.posAtCoords({
                            left: event.clientX,
                            top: event.clientY,
                        });
                        if (!pos) return false;

                        const deco = pluginState.decorations.find(
                            pos.pos,
                            pos.pos,
                        )[0];
                        if (!deco) return false;

                        const finding = (deco.spec as { finding: StyleFinding })
                            .finding;
                        const word = view.state.doc.textBetween(
                            deco.from,
                            deco.to,
                        );

                        activePopover = createStylePopover({
                            finding,
                            position: { x: event.clientX, y: event.clientY },
                            onIgnoreWord:
                                onIgnoreWord &&
                                IGNORABLE_CATEGORIES.has(finding.category)
                                    ? () => onIgnoreWord(word)
                                    : undefined,
                            onDeleteWord:
                                finding.category === 'filler'
                                    ? () => {
                                          const { doc, tr } = view.state;
                                          if (
                                              doc.textBetween(
                                                  deco.from,
                                                  deco.to,
                                              ) !== word
                                          ) {
                                              return;
                                          }
                                          // Take a following space along so
                                          // no double gap is left behind.
                                          const after = doc.textBetween(
                                              deco.to,
                                              Math.min(
                                                  deco.to + 1,
                                                  doc.content.size,
                                              ),
                                          );
                                          tr.delete(
                                              deco.from,
                                              after === ' '
                                                  ? deco.to + 1
                                                  : deco.to,
                                          );
                                          view.dispatch(tr);
                                      }
                                    : undefined,
                        });
                        return false;
                    },
                },
                view: (editorView) => {
                    const styleView = new StyleAnalysisView(
                        editorView,
                        client,
                        analyzeOptionsRef,
                        onAnalysis,
                    );
                    return {
                        update: () => styleView.update(),
                        destroy: () => {
                            styleView.destroy();
                            activePopover?.destroy();
                            activePopover = null;
                        },
                    };
                },
            }),
        ];
    },
});
