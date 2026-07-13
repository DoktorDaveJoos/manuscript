import { tokenizeWords } from '@/lib/spellcheck/tokenize';
import { computeStats } from './stats';
import type {
    AnalyzeOptions,
    StyleAnalysis,
    StyleCategory,
    StyleFinding,
    StylePack,
} from './types';

const WORD_LIST_CATEGORIES = [
    ['filler', 'fillers'],
    ['weakVerb', 'weakVerbs'],
    ['filterWord', 'filterWords'],
] as const;

const REPETITION_MIN_LENGTH = 4;
const REPETITION_WINDOW = 50; // tokens

/**
 * Inflection-tolerant equality without a morphological stemmer (hunspell-asm
 * exposes none): exact match, or a shared prefix of ≥4 letters covering at
 * least ⅔ of the longer word — catches leere/leeren and machte/machen while
 * rejecting geschrieben/geschlossen.
 */
function sameStem(a: string, b: string): boolean {
    if (a === b) return true;
    let prefix = 0;
    const max = Math.min(a.length, b.length);
    while (prefix < max && a[prefix] === b[prefix]) prefix++;
    return (
        prefix >= REPETITION_MIN_LENGTH &&
        prefix >= Math.ceil((Math.max(a.length, b.length) * 2) / 3)
    );
}

// JS \b is ASCII-only, so umlauts/accents break it — bound phrases with
// letter lookarounds instead.
const NOT_LETTER_BEFORE = '(?<![\\p{L}\\p{M}])';
const NOT_LETTER_AFTER = '(?![\\p{L}\\p{M}])';

function escapeRegex(literal: string): string {
    return literal.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function phraseRegex(phrase: string): RegExp {
    const body = phrase.trim().split(/\s+/).map(escapeRegex).join('\\s+');
    return new RegExp(`${NOT_LETTER_BEFORE}${body}${NOT_LETTER_AFTER}`, 'giu');
}

function compilePattern(regex: string): RegExp | null {
    try {
        return new RegExp(regex, 'giu');
    } catch {
        return null;
    }
}

function matchAll(
    text: string,
    regex: RegExp,
    emit: (from: number, to: number, match: string) => void,
): void {
    for (const match of text.matchAll(regex)) {
        if (match[0].length === 0) continue;
        emit(match.index, match.index + match[0].length, match[0]);
    }
}

export const EMPTY_PACK: StylePack = {
    version: 0,
    stopwords: [],
    fillers: [],
    weakVerbs: [],
    filterWords: [],
    cliches: [],
    patterns: [],
};

export function emptyStyleAnalysis(): StyleAnalysis {
    return analyzeText('', EMPTY_PACK);
}

export function analyzeText(
    text: string,
    pack: StylePack,
    options: AnalyzeOptions = {},
): StyleAnalysis {
    const enabled = (category: StyleCategory) =>
        options.categories?.[category] !== false;
    const ignored = new Set(
        (options.ignoredWords ?? []).map((word) => word.toLowerCase()),
    );

    const lists = WORD_LIST_CATEGORIES.filter(([category]) =>
        enabled(category),
    ).map(([category, packKey]) => ({
        category,
        words: new Set(pack[packKey].map((word) => word.toLowerCase())),
    }));

    const findings: StyleFinding[] = [];
    const tokens = tokenizeWords(text);
    const wordListFlagged = new Set<number>();
    tokens.forEach((token, index) => {
        const lower = token.word.toLowerCase();
        if (ignored.has(lower)) return;
        for (const { category, words } of lists) {
            if (words.has(lower)) {
                findings.push({
                    category,
                    from: token.from,
                    to: token.to,
                    word: token.word,
                });
                wordListFlagged.add(index);
                break;
            }
        }
    });

    if (enabled('repetition')) {
        const stopwords = new Set(
            pack.stopwords.map((word) => word.toLowerCase()),
        );
        const flaggedAt = new Map<number, StyleFinding>();
        const candidates: Array<{ index: number; lower: string }> = [];
        const occurrence = (index: number) => ({
            from: tokens[index].from,
            to: tokens[index].to,
            word: tokens[index].word,
        });
        const flag = (index: number, partnerIndex: number) => {
            if (flaggedAt.has(index)) return;
            const finding: StyleFinding = {
                category: 'repetition',
                ...occurrence(index),
                partner: occurrence(partnerIndex),
            };
            flaggedAt.set(index, finding);
            findings.push(finding);
        };
        tokens.forEach((token, index) => {
            if (token.word.length < REPETITION_MIN_LENGTH) return;
            const lower = token.word.toLowerCase();
            if (stopwords.has(lower) || ignored.has(lower)) return;
            if (wordListFlagged.has(index)) return;
            for (let i = candidates.length - 1; i >= 0; i--) {
                const prev = candidates[i];
                if (index - prev.index > REPETITION_WINDOW) break;
                if (sameStem(lower, prev.lower)) {
                    flag(prev.index, index);
                    flag(index, prev.index);
                    break;
                }
            }
            candidates.push({ index, lower });
        });
    }

    if (enabled('cliche')) {
        for (const phrase of pack.cliches) {
            matchAll(text, phraseRegex(phrase), (from, to, match) => {
                findings.push({ category: 'cliche', from, to, word: match });
            });
        }
    }

    if (enabled('pattern')) {
        for (const { id, regex } of pack.patterns) {
            const compiled = compilePattern(regex);
            if (!compiled) continue;
            matchAll(text, compiled, (from, to, match) => {
                findings.push({
                    category: 'pattern',
                    from,
                    to,
                    word: match,
                    patternId: id,
                });
            });
        }
    }

    findings.sort((a, b) => a.from - b.from || a.to - b.to);

    return {
        findings,
        stats: computeStats(text, pack),
        available: {
            filler: pack.fillers.length > 0,
            weakVerb: pack.weakVerbs.length > 0,
            filterWord: pack.filterWords.length > 0,
            cliche: pack.cliches.length > 0,
            pattern: pack.patterns.length > 0,
            repetition: true,
        },
    };
}
