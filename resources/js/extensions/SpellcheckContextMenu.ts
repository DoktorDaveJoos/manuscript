import { Extension } from '@tiptap/core';
import { Plugin, PluginKey } from '@tiptap/pm/state';

/**
 * Shows a native Electron context menu with spellcheck suggestions
 * when the user right-clicks a misspelled word. Requires the
 * `window.Spellcheck` and `window.Native` bridges from the preload.
 */
export const SpellcheckContextMenu = Extension.create({
    name: 'spellcheckContextMenu',

    addProseMirrorPlugins() {
        return [
            new Plugin({
                key: new PluginKey('spellcheckContextMenu'),
                props: {
                    handleDOMEvents: {
                        contextmenu(view, event) {
                            if (!window.Spellcheck || !window.Native)
                                return false;

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

                            const menuTemplate: Array<{
                                label?: string;
                                type?: 'separator';
                                enabled?: boolean;
                                click?: () => void;
                            }> = [];

                            if (suggestions.length > 0) {
                                for (const suggestion of suggestions.slice(
                                    0,
                                    5,
                                )) {
                                    menuTemplate.push({
                                        label: suggestion,
                                        click: () => {
                                            // Validate positions are still valid
                                            const { doc, tr } = view.state;
                                            const currentText = doc.textBetween(
                                                wordStart,
                                                wordEnd,
                                            );
                                            if (currentText !== word) return;
                                            tr.insertText(
                                                suggestion,
                                                wordStart,
                                                wordEnd,
                                            );
                                            view.dispatch(tr);
                                        },
                                    });
                                }
                                menuTemplate.push({ type: 'separator' });
                            } else {
                                menuTemplate.push({
                                    label: 'No suggestions',
                                    enabled: false,
                                });
                                menuTemplate.push({ type: 'separator' });
                            }

                            menuTemplate.push({
                                label: 'Add to Dictionary',
                                click: () => {
                                    window.Spellcheck!.addToDictionary(word);
                                    // Force spellcheck re-evaluation by dispatching a no-op
                                    view.dispatch(view.state.tr);
                                },
                            });

                            window.Native.contextMenu(menuTemplate);
                            return true;
                        },
                    },
                },
            }),
        ];
    },
});
