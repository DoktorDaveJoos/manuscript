import { Extension } from '@tiptap/core';
import type { Node as PMNode } from '@tiptap/pm/model';
import type { EditorState, Transaction } from '@tiptap/pm/state';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import { Mapping } from '@tiptap/pm/transform';
import type { EditorView } from '@tiptap/pm/view';
import { Decoration, DecorationSet } from '@tiptap/pm/view';
import type { RefObject } from 'react';
import { createSpellcheckPopover } from '@/components/editor/SpellcheckPopover';
import type { SpellcheckClient } from '@/lib/spellcheck/client';
import { getSpellcheckClient } from '@/lib/spellcheck/client';
import type { MisspelledRange } from '@/lib/spellcheck/protocol';

const DEBOUNCE_MS = 300;
// Placeholder for non-text leaf nodes (e.g. hardBreak) so text offsets
// stay aligned with document positions. Never matches the word regex.
const LEAF_CHAR = '￼';

interface SpellcheckPluginState {
    decorations: DecorationSet;
    enabled: boolean;
    /** Doc range needing a recheck; -1 = clean. Mapped through edits. */
    dirtyFrom: number;
    dirtyTo: number;
    /**
     * Cumulative step mapping since the last idle reset. Async check
     * results carry the mapping length at request time; slicing from it
     * maps request-time positions to current positions.
     */
    mapping: Mapping;
}

type SpellcheckMeta =
    | {
          type: 'results';
          blockPos: number;
          mappingLength: number;
          text: string;
          ranges: MisspelledRange[];
      }
    | { type: 'set-enabled'; enabled: boolean }
    | { type: 'recheck-all' }
    | { type: 'clear-dirty' }
    | { type: 'reset-mapping' };

export const spellcheckPluginKey = new PluginKey<SpellcheckPluginState>(
    'spellcheck',
);

function blockText(node: PMNode): string {
    return node.textBetween(0, node.content.size, undefined, LEAF_CHAR);
}

function markDirty(
    state: SpellcheckPluginState,
    from: number,
    to: number,
): void {
    state.dirtyFrom =
        state.dirtyFrom < 0 ? from : Math.min(state.dirtyFrom, from);
    state.dirtyTo = Math.max(state.dirtyTo, to);
}

function applyTransaction(
    tr: Transaction,
    prev: SpellcheckPluginState,
): SpellcheckPluginState {
    const next: SpellcheckPluginState = { ...prev };

    if (tr.docChanged) {
        next.decorations = next.decorations.map(tr.mapping, tr.doc);
        next.mapping = new Mapping([...next.mapping.maps, ...tr.mapping.maps]);
        if (next.dirtyFrom >= 0) {
            next.dirtyFrom = tr.mapping.map(next.dirtyFrom, -1);
            next.dirtyTo = tr.mapping.map(next.dirtyTo, 1);
        }
        // Union in this transaction's changed ranges (in final coordinates).
        tr.mapping.maps.forEach((stepMap, index) => {
            const rest = tr.mapping.slice(index + 1);
            stepMap.forEach((_oldStart, _oldEnd, newStart, newEnd) => {
                markDirty(next, rest.map(newStart, -1), rest.map(newEnd, 1));
            });
        });
    }

    const meta = tr.getMeta(spellcheckPluginKey) as SpellcheckMeta | undefined;
    if (!meta) return next;

    switch (meta.type) {
        case 'set-enabled':
            next.enabled = meta.enabled;
            if (meta.enabled) {
                markDirty(next, 0, tr.doc.content.size);
            } else {
                next.decorations = DecorationSet.empty;
                next.dirtyFrom = -1;
                next.dirtyTo = -1;
            }
            break;
        case 'recheck-all':
            if (next.enabled) markDirty(next, 0, tr.doc.content.size);
            break;
        case 'clear-dirty':
            next.dirtyFrom = -1;
            next.dirtyTo = -1;
            break;
        case 'reset-mapping':
            next.mapping = new Mapping();
            break;
        case 'results': {
            if (!next.enabled) break;
            // Map the request-time block position to the present, then
            // verify the block still holds the exact text that was checked.
            // If it changed, that edit already re-marked the block dirty —
            // dropping the stale result is safe.
            const mapped = next.mapping
                .slice(meta.mappingLength)
                .map(meta.blockPos, -1);
            const node = tr.doc.nodeAt(mapped);
            if (!node || !node.isTextblock || blockText(node) !== meta.text) {
                break;
            }
            const contentStart = mapped + 1;
            const contentEnd = contentStart + node.content.size;
            next.decorations = next.decorations.remove(
                next.decorations.find(contentStart, contentEnd),
            );
            next.decorations = next.decorations.add(
                tr.doc,
                meta.ranges.map((range) =>
                    Decoration.inline(
                        contentStart + range.from,
                        contentStart + range.to,
                        { class: 'spell-error' },
                    ),
                ),
            );
            break;
        }
    }
    return next;
}

class SpellcheckView {
    private timer: number | null = null;
    private destroyed = false;
    private engineOk = false;
    private inflight = 0;
    private readonly unsubscribeWordsChanged: () => void;

    constructor(
        private readonly view: EditorView,
        private readonly client: SpellcheckClient,
        customWords: string[],
    ) {
        this.unsubscribeWordsChanged = client.onWordsChanged(() => {
            if (this.destroyed || !this.engineOk) return;
            this.dispatchMeta({ type: 'recheck-all' });
        });
        void this.boot(customWords);
    }

    private async boot(customWords: string[]): Promise<void> {
        this.engineOk = await this.client.whenReady;
        if (!this.engineOk || this.destroyed) return;
        this.client.setCustomWords(customWords);
        this.schedule();
    }

    update(): void {
        const state = spellcheckPluginKey.getState(this.view.state);
        if (state && state.enabled && state.dirtyFrom >= 0) this.schedule();
    }

    private schedule(): void {
        if (!this.engineOk) return;
        if (this.timer !== null) window.clearTimeout(this.timer);
        this.timer = window.setTimeout(() => {
            this.timer = null;
            this.flush();
        }, DEBOUNCE_MS);
    }

    private dispatchMeta(meta: SpellcheckMeta): void {
        const tr = this.view.state.tr
            .setMeta(spellcheckPluginKey, meta)
            .setMeta('addToHistory', false);
        this.view.dispatch(tr);
    }

    private flush(): void {
        if (this.destroyed || !this.engineOk) return;
        const state = spellcheckPluginKey.getState(this.view.state);
        if (!state || !state.enabled || state.dirtyFrom < 0) return;

        const doc = this.view.state.doc;
        const from = Math.max(0, Math.min(state.dirtyFrom, doc.content.size));
        const to = Math.max(from, Math.min(state.dirtyTo, doc.content.size));
        const mappingLength = state.mapping.maps.length;

        // Snapshot the blocks BEFORE clearing dirty, then clear synchronously
        // so edits arriving while checks are in flight re-dirty cleanly.
        const blocks: Array<{ pos: number; text: string }> = [];
        doc.nodesBetween(from, to, (node, pos) => {
            if (node.isTextblock) {
                blocks.push({ pos, text: blockText(node) });
                return false;
            }
            return true;
        });
        this.dispatchMeta({ type: 'clear-dirty' });

        for (const block of blocks) {
            this.inflight++;
            void this.client.check(block.text).then((ranges) => {
                this.inflight--;
                if (this.destroyed) return;
                this.dispatchMeta({
                    type: 'results',
                    blockPos: block.pos,
                    mappingLength,
                    text: block.text,
                    ranges,
                });
                // With no checks in flight the accumulated mapping can be
                // dropped — keeps memory flat over long sessions.
                const current = spellcheckPluginKey.getState(this.view.state);
                if (
                    this.inflight === 0 &&
                    current &&
                    current.dirtyFrom < 0 &&
                    current.mapping.maps.length > 0
                ) {
                    this.dispatchMeta({ type: 'reset-mapping' });
                }
            });
        }
    }

    destroy(): void {
        this.destroyed = true;
        if (this.timer !== null) window.clearTimeout(this.timer);
        this.unsubscribeWordsChanged();
        // The worker is a shared per-language singleton — never terminated here.
    }
}

export interface SpellcheckOptions {
    language: string;
    enabledRef?: RefObject<boolean>;
    customWords: string[];
    onAddToDictionary?: (word: string) => void;
}

export const SpellcheckExtension = Extension.create<SpellcheckOptions>({
    name: 'spellcheck',

    addOptions() {
        return {
            language: 'en',
            enabledRef: undefined,
            customWords: [],
            onAddToDictionary: undefined,
        };
    },

    addProseMirrorPlugins() {
        const { language, enabledRef, customWords, onAddToDictionary } =
            this.options;

        const client = getSpellcheckClient(language);
        if (!client) return [];

        let activePopover: { destroy: () => void } | null = null;

        return [
            new Plugin<SpellcheckPluginState>({
                key: spellcheckPluginKey,
                state: {
                    init: (_config, state: EditorState) => ({
                        decorations: DecorationSet.empty,
                        enabled: enabledRef?.current ?? true,
                        dirtyFrom: 0,
                        dirtyTo: state.doc.content.size,
                        mapping: new Mapping(),
                    }),
                    apply: applyTransaction,
                },
                props: {
                    decorations(state) {
                        return spellcheckPluginKey.getState(state)?.decorations;
                    },
                    handleDOMEvents: {
                        contextmenu(view, event) {
                            activePopover?.destroy();
                            activePopover = null;

                            const pluginState = spellcheckPluginKey.getState(
                                view.state,
                            );
                            if (!pluginState?.enabled) return false;

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

                            const word = view.state.doc.textBetween(
                                deco.from,
                                deco.to,
                            );
                            event.preventDefault();

                            void client.suggest(word).then((suggestions) => {
                                activePopover = createSpellcheckPopover({
                                    suggestions,
                                    position: {
                                        x: event.clientX,
                                        y: event.clientY,
                                    },
                                    onReplace: (replacement) => {
                                        const { doc, tr } = view.state;
                                        if (
                                            doc.textBetween(
                                                deco.from,
                                                deco.to,
                                            ) !== word
                                        ) {
                                            return;
                                        }
                                        tr.insertText(
                                            replacement,
                                            deco.from,
                                            deco.to,
                                        );
                                        view.dispatch(tr);
                                    },
                                    onAddToDictionary: () => {
                                        // client.addWord notifies every
                                        // SpellcheckView subscribed via
                                        // onWordsChanged (including this
                                        // one), which dispatches
                                        // recheck-all — no need to do it
                                        // here too.
                                        client.addWord(word);
                                        onAddToDictionary?.(word);
                                    },
                                });
                            });
                            return true;
                        },
                    },
                },
                view: (editorView) => {
                    const spellcheckView = new SpellcheckView(
                        editorView,
                        client,
                        customWords,
                    );
                    return {
                        update: () => spellcheckView.update(),
                        destroy: () => {
                            spellcheckView.destroy();
                            activePopover?.destroy();
                            activePopover = null;
                        },
                    };
                },
            }),
        ];
    },
});
