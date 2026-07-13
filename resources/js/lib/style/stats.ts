import { tokenizeWords } from '@/lib/spellcheck/tokenize';
import { countWords } from '@/lib/wordCount';
import type { ReadabilityFormula, StylePack, StyleStats } from './types';

// Sentence ends at terminal punctuation (plus closing quotes) or a paragraph
// break — dialogue lines and headings often lack terminal punctuation.
const SENTENCE_SPLIT = /(?<=[.!?…])[)»«„""''‚'"]*\s+|\n+/u;
const VOWEL_GROUPS = /[aeiouyäöüáàâãæéèêëíîïóôõœúùûåøý]+/giu;

function syllableCount(word: string): number {
    return Math.max(1, word.match(VOWEL_GROUPS)?.length ?? 1);
}

function clamp(value: number): number {
    return Math.min(100, Math.max(0, value));
}

function readabilityScore(
    formula: ReadabilityFormula,
    words: string[],
    sentenceCount: number,
): number {
    const avgSentenceLength = words.length / sentenceCount;
    const syllablesPerWord =
        words.reduce((sum, word) => sum + syllableCount(word), 0) /
        words.length;
    switch (formula) {
        case 'flesch':
            return clamp(
                206.835 - 1.015 * avgSentenceLength - 84.6 * syllablesPerWord,
            );
        case 'amstad':
            return clamp(180 - avgSentenceLength - 58.5 * syllablesPerWord);
        case 'kandel':
            return clamp(
                209 - 1.15 * avgSentenceLength - 68 * syllablesPerWord,
            );
        case 'lix': {
            const longWords = words.filter((word) => word.length > 6).length;
            return clamp(avgSentenceLength + (longWords / words.length) * 100);
        }
    }
}

export function computeStats(text: string, pack: StylePack): StyleStats {
    // Spellcheck tokens intentionally omit numbers, acronyms, and some
    // scripts. The visible total must use the editor's canonical counter.
    const wordCount = countWords(text);
    const sentenceLengths: number[] = [];
    const words: string[] = [];
    for (const chunk of text.split(SENTENCE_SPLIT)) {
        const tokens = tokenizeWords(chunk);
        if (tokens.length === 0) continue;
        sentenceLengths.push(tokens.length);
        for (const token of tokens) words.push(token.word);
    }

    const sentenceCount = sentenceLengths.length;
    const formula = pack.readability?.formula ?? 'lix';

    let adjectiveRatio: number | null = null;
    const suffixes = pack.adjectiveSuffixes ?? [];
    if (suffixes.length > 0 && words.length > 0) {
        // Tolerate German-style declension tails after the suffix.
        const adjective = new RegExp(
            `(?:${suffixes.join('|')})(?:e|er|es|em|en|ste?n?)?$`,
            'iu',
        );
        adjectiveRatio =
            words.filter((word) => adjective.test(word)).length / words.length;
    }

    return {
        wordCount,
        sentenceCount,
        avgSentenceLength: sentenceCount > 0 ? words.length / sentenceCount : 0,
        sentenceLengths,
        readability:
            sentenceCount > 0
                ? readabilityScore(formula, words, sentenceCount)
                : null,
        readabilityFormula: sentenceCount > 0 ? formula : null,
        adjectiveRatio,
    };
}
