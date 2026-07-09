export interface WordToken {
    word: string;
    from: number; // offset within the text
    to: number;
}

const WORD_RE = /[\p{L}\p{M}'’]+/gu;

/**
 * Split text into checkable word tokens with offsets.
 * Skips: tokens attached to digits ("2nd" -> "nd"), ALL-CAPS words
 * (acronyms / shouting), and bare apostrophes.
 */
export function tokenizeWords(text: string): WordToken[] {
    const tokens: WordToken[] = [];
    for (const match of text.matchAll(WORD_RE)) {
        let word = match[0];
        let from = match.index;
        while (word.length > 0 && /^['’]/.test(word)) {
            word = word.slice(1);
            from++;
        }
        while (word.length > 0 && /['’]$/.test(word)) {
            word = word.slice(0, -1);
        }
        if (word.length === 0) continue;

        const before = text[from - 1];
        const after = text[from + word.length];
        if ((before && /\d/.test(before)) || (after && /\d/.test(after))) {
            continue;
        }
        if (word.length > 1 && word === word.toUpperCase()) continue;

        tokens.push({ word, from, to: from + word.length });
    }
    return tokens;
}
