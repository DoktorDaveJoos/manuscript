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
                    onExitUp.current();
                    return true;
                }
                return false;
            },
            ArrowDown: ({ editor }) => {
                if (editor.view.endOfTextblock('down') && onExitDown.current) {
                    onExitDown.current();
                    return true;
                }
                return false;
            },
        };
    },
});
