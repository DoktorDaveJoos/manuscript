import { Extension } from '@tiptap/core';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import type { RefObject } from 'react';
import { createSpellcheckPopover } from '@/components/editor/SpellcheckPopover';

export interface SpellcheckContextMenuOptions {
    enabledRef?: RefObject<boolean>;
}

/**
 * Shows a floating context menu with spellcheck suggestions when the user
 * right-clicks a misspelled word. Uses Electron's `window.Spellcheck`
 * bridge (webFrame API) for spell checking and a React-rendered popover
 * for the UI — avoiding the contextBridge serialization issues that
 * prevent native Electron menus from receiving click callbacks.
 */
export const SpellcheckContextMenu =
    Extension.create<SpellcheckContextMenuOptions>({
        name: 'spellcheckContextMenu',

        addOptions() {
            return { enabledRef: undefined };
        },

        addProseMirrorPlugins() {
            const { enabledRef } = this.options;
            let activePopover: { destroy: () => void } | null = null;

            return [
                new Plugin({
                    key: new PluginKey('spellcheckContextMenu'),
                    props: {
                        handleDOMEvents: {
                            contextmenu(view, event) {
                                // Close any existing popover first
                                activePopover?.destroy();
                                activePopover = null;

                                if (enabledRef && !enabledRef.current)
                                    return false;
                                if (!window.Spellcheck) return false;

                                const pos = view.posAtCoords({
                                    left: event.clientX,
                                    top: event.clientY,
                                });
                                if (!pos) return false;

                                // Get the word under the cursor
                                const { doc } = view.state;
                                const resolved = doc.resolve(pos.pos);
                                const textNode = resolved.parent;
                                if (!textNode.isTextblock) return false;

                                const text = textNode.textContent;
                                const offset = resolved.parentOffset;

                                // Find word boundaries around the cursor position
                                const wordRe = /[\p{L}'\u2019]+/gu;
                                let match: RegExpExecArray | null;
                                let word = '';
                                let wordStart = 0;
                                let wordEnd = 0;

                                while ((match = wordRe.exec(text)) !== null) {
                                    const start = match.index;
                                    const end = start + match[0].length;
                                    if (offset >= start && offset <= end) {
                                        word = match[0];
                                        wordStart = resolved.start() + start;
                                        wordEnd = resolved.start() + end;
                                        break;
                                    }
                                }

                                if (
                                    !word ||
                                    !window.Spellcheck.isWordMisspelled(word)
                                ) {
                                    return false;
                                }

                                event.preventDefault();

                                const suggestions =
                                    window.Spellcheck.getWordSuggestions(word);

                                activePopover = createSpellcheckPopover({
                                    suggestions,
                                    position: {
                                        x: event.clientX,
                                        y: event.clientY,
                                    },
                                    onReplace: (replacement) => {
                                        // Validate positions are still valid
                                        const { doc: currentDoc, tr } =
                                            view.state;
                                        const currentText =
                                            currentDoc.textBetween(
                                                wordStart,
                                                wordEnd,
                                            );
                                        if (currentText !== word) return;
                                        tr.insertText(
                                            replacement,
                                            wordStart,
                                            wordEnd,
                                        );
                                        view.dispatch(tr);
                                    },
                                    onAddToDictionary: () => {
                                        window.Spellcheck!.addToDictionary(
                                            word,
                                        );
                                        // Force spellcheck re-evaluation
                                        view.dispatch(view.state.tr);
                                    },
                                });

                                return true;
                            },
                        },
                    },
                    view() {
                        return {
                            destroy() {
                                activePopover?.destroy();
                                activePopover = null;
                            },
                        };
                    },
                }),
            ];
        },
    });
