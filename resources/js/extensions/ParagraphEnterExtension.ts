import { Extension } from '@tiptap/core';

/**
 * Remaps Enter to insert a line break (<br>) within paragraphs.
 * Double Enter (Enter after a trailing <br>) creates a new paragraph.
 *
 * This gives a book-like editing model:
 *   Enter        → new line (same paragraph, no indent)
 *   Enter + Enter → new paragraph (indented)
 */
export const ParagraphEnterExtension = Extension.create({
    name: 'paragraphEnter',
    priority: 150,

    addKeyboardShortcuts() {
        return {
            Enter: ({ editor }) => {
                const { state } = editor.view;
                const { $from, empty } = state.selection;

                // Only intercept inside paragraphs
                if ($from.parent.type.name !== 'paragraph') {
                    return false;
                }

                // Preserve default list Enter behavior (new item / lift on empty)
                if ($from.depth > 1 && $from.node($from.depth - 1).type.name === 'listItem') {
                    return false;
                }

                // Non-collapsed selection: delete then insert line break
                if (!empty) {
                    return editor.chain().deleteSelection().setHardBreak().run();
                }

                const parentNode = $from.parent;

                // Empty paragraph → new paragraph (so Enter on blank line still works)
                if (parentNode.content.size === 0) {
                    return editor.commands.splitBlock();
                }

                // Double Enter: cursor at end of paragraph, preceded by a hardBreak
                const atEnd = $from.parentOffset === parentNode.content.size;
                const nodeBefore = $from.nodeBefore;

                if (atEnd && nodeBefore?.type.name === 'hardBreak') {
                    return editor
                        .chain()
                        .command(({ tr }) => {
                            const pos = tr.selection.$from.pos;
                            const nb = tr.selection.$from.nodeBefore;
                            if (nb?.type.name === 'hardBreak') {
                                tr.delete(pos - nb.nodeSize, pos);
                            }
                            return true;
                        })
                        .splitBlock()
                        .run();
                }

                // Single Enter: line break within the same paragraph
                return editor.commands.setHardBreak();
            },
        };
    },
});
