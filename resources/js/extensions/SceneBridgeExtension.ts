import { Extension } from '@tiptap/core';
import type { RefObject } from 'react';

export const SceneBridgeExtension = Extension.create<{
    onExitUp: RefObject<(() => void) | null>;
    onExitDown: RefObject<(() => void) | null>;
}>({
    name: 'sceneBridge',

    addOptions() {
        return {
            onExitUp: { current: null },
            onExitDown: { current: null },
        };
    },

    addKeyboardShortcuts() {
        const { onExitUp, onExitDown } = this.options;

        return {
            ArrowUp: ({ editor }) => {
                if (editor.view.endOfTextblock('up') && onExitUp.current) {
                    const { $head } = editor.state.selection;
                    // endOfTextblock('up') fires at the top of ANY block, not just the first
                    if ($head.before(1) === 0) {
                        onExitUp.current();
                        return true;
                    }
                }
                return false;
            },
            ArrowDown: ({ editor }) => {
                if (editor.view.endOfTextblock('down') && onExitDown.current) {
                    const { $head } = editor.state.selection;
                    // endOfTextblock('down') fires at the bottom of ANY block, not just the last
                    if ($head.after(1) === editor.state.doc.content.size) {
                        onExitDown.current();
                        return true;
                    }
                }
                return false;
            },
        };
    },
});
