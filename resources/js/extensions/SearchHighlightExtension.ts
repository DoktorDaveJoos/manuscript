import type { Editor } from '@tiptap/core';
import { Extension } from '@tiptap/core';
import type { Node as PmNode } from '@tiptap/pm/model';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import { Decoration, DecorationSet } from '@tiptap/pm/view';

export type SearchHighlight = {
    query: string;
    caseSensitive: boolean;
    wholeWord: boolean;
    regex: boolean;
};

export const searchHighlightKey = new PluginKey('searchHighlight');

export function buildPattern(
    params: Pick<
        SearchHighlight,
        'query' | 'caseSensitive' | 'wholeWord' | 'regex'
    >,
): RegExp | null {
    const { query, caseSensitive, wholeWord, regex } = params;
    if (!query) return null;

    const flags = `g${caseSensitive ? '' : 'i'}`;

    try {
        if (regex) {
            return new RegExp(query, flags);
        }

        const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

        if (wholeWord) {
            return new RegExp(`\\b${escaped}\\b`, flags);
        }

        return new RegExp(escaped, flags);
    } catch {
        return null;
    }
}

function buildDecorations(
    doc: PmNode,
    params: SearchHighlight & { activeFrom: number; activeTo: number },
): DecorationSet {
    const pattern = buildPattern(params);
    if (!pattern) return DecorationSet.empty;

    const decorations: Decoration[] = [];

    doc.descendants((node, pos) => {
        if (!node.isText || !node.text) return;

        let match: RegExpExecArray | null;
        pattern.lastIndex = 0;

        while ((match = pattern.exec(node.text)) !== null) {
            const from = pos + match.index;
            const to = from + match[0].length;
            const isActive =
                from === params.activeFrom && to === params.activeTo;
            decorations.push(
                Decoration.inline(from, to, {
                    class: isActive
                        ? 'search-highlight search-highlight-active'
                        : 'search-highlight',
                }),
            );

            if (match[0].length === 0) break;
        }
    });

    return DecorationSet.create(doc, decorations);
}

/** Update the search highlight state on an editor and trigger a decoration rebuild. */
export function updateSearchHighlight(
    editor: Editor,
    params: SearchHighlight & { activeFrom?: number; activeTo?: number },
): void {
    if (editor.isDestroyed) return;
    const storage = editor.extensionStorage.searchHighlight;
    if (!storage) return;
    storage.query = params.query;
    storage.caseSensitive = params.caseSensitive;
    storage.wholeWord = params.wholeWord;
    storage.regex = params.regex;
    storage.activeFrom = params.activeFrom ?? -1;
    storage.activeTo = params.activeTo ?? -1;
    editor.view.dispatch(
        editor.view.state.tr.setMeta(searchHighlightKey, true),
    );
}

export const SearchHighlightExtension = Extension.create({
    name: 'searchHighlight',

    addStorage() {
        return {
            query: '',
            caseSensitive: false,
            wholeWord: false,
            regex: false,
            activeFrom: -1,
            activeTo: -1,
        };
    },

    addProseMirrorPlugins() {
        const extension = this;

        return [
            new Plugin({
                key: searchHighlightKey,
                state: {
                    init(_, state) {
                        return buildDecorations(state.doc, extension.storage);
                    },
                    apply(tr, old) {
                        if (tr.docChanged || tr.getMeta(searchHighlightKey)) {
                            return buildDecorations(tr.doc, extension.storage);
                        }
                        return old;
                    },
                },
                props: {
                    decorations(state) {
                        return this.getState(state);
                    },
                },
            }),
        ];
    },
});
