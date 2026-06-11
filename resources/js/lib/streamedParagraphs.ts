import type { Editor } from '@tiptap/react';
import { escapeHtml } from '@/lib/utils';

/**
 * Insert streamed plain text at the cursor, turning newline runs into
 * paragraph splits.
 *
 * Each paragraph runs in its own chain on purpose: Tiptap's splitBlock maps
 * the current selection through the chain's accumulated step mapping, so
 * chaining it after insertContent double-counts the just-inserted text and
 * resolves a position past the end of the document (RangeError: Position N
 * out of range). With splitBlock first in a fresh chain the mapping is empty
 * and the split lands at the actual cursor.
 */
export function insertStreamedParagraphs(editor: Editor, text: string): void {
    text.split(/\n+/).forEach((paragraph, index) => {
        if (index === 0 && paragraph === '') {
            return;
        }
        const chain = editor.chain();
        if (index > 0) {
            chain.splitBlock();
        }
        if (paragraph !== '') {
            chain.insertContent(escapeHtml(paragraph));
        }
        chain.run();
    });
}
