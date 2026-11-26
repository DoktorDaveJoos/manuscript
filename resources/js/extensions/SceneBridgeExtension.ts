import { Extension } from '@tiptap/core';

export const SceneBridgeExtension = Extension.create<{
    getOnExitUp: () => (() => void) | null;
    getOnExitDown: () => (() => void) | null;
}>({
    name: 'sceneBridge',

    addOptions() {
        return {
            getOnExitUp: () => null,
            getOnExitDown: () => null,
        };
    },

    addKeyboardShortcuts() {
        const { getOnExitUp, getOnExitDown } = this.options;

        return {
            ArrowUp: ({ editor }) => {
                const onExitUp = getOnExitUp();
                if (editor.view.endOfTextblock('up') && onExitUp) {
                    const { $head } = editor.state.selection;
                    // endOfTextblock('up') fires at the top of ANY block, not just the first
                    if ($head.before(1) === 0) {
                        onExitUp();
                        return true;
                    }
                }
                return false;
            },
            ArrowDown: ({ editor }) => {
                const onExitDown = getOnExitDown();
                if (editor.view.endOfTextblock('down') && onExitDown) {
                    const { $head } = editor.state.selection;
                    // endOfTextblock('down') fires at the bottom of ANY block, not just the last
                    if ($head.after(1) === editor.state.doc.content.size) {
                        onExitDown();
                        return true;
                    }
                }
                return false;
            },
        };
    },
});
