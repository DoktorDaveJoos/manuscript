import { expect, test } from 'vitest';
import { analyzeText } from './analyze';
import type { StylePack } from './types';

function pack(overrides: Partial<StylePack> = {}): StylePack {
    return {
        version: 1,
        stopwords: [],
        fillers: [],
        weakVerbs: [],
        filterWords: [],
        cliches: [],
        patterns: [],
        ...overrides,
    };
}

test('flags filler words with offsets', () => {
    const text = 'Die Ideen sprudelten eigentlich immer.';
    const { findings } = analyzeText(text, pack({ fillers: ['eigentlich'] }));
    expect(findings).toEqual([
        { category: 'filler', from: 21, to: 31, word: 'eigentlich' },
    ]);
});

test('list matching is case-insensitive', () => {
    const { findings } = analyzeText(
        'Eigentlich war es gut.',
        pack({ fillers: ['eigentlich'] }),
    );
    expect(findings).toHaveLength(1);
    expect(findings[0]).toMatchObject({
        category: 'filler',
        from: 0,
        to: 10,
        word: 'Eigentlich',
    });
});

test('flags weak verbs and filter words as their own categories', () => {
    const text = 'Er machte etwas. Er dachte nach.';
    const { findings } = analyzeText(
        text,
        pack({ weakVerbs: ['machte'], filterWords: ['dachte'] }),
    );
    expect(findings).toEqual([
        { category: 'weakVerb', from: 3, to: 9, word: 'machte' },
        { category: 'filterWord', from: 20, to: 26, word: 'dachte' },
    ]);
});

test('a word in several lists is only flagged once (filler wins)', () => {
    const { findings } = analyzeText(
        'Er machte weiter.',
        pack({ fillers: ['machte'], weakVerbs: ['machte'] }),
    );
    expect(findings).toHaveLength(1);
    expect(findings[0].category).toBe('filler');
});

test('ignored words are not flagged', () => {
    const { findings } = analyzeText(
        'Das war eigentlich gut.',
        pack({ fillers: ['eigentlich'] }),
        { ignoredWords: ['eigentlich'] },
    );
    expect(findings).toEqual([]);
});

test('flags multi-word cliché phrases with offsets, case-insensitively', () => {
    const text = 'Seine geschlossene Faust schlug auf den Tisch.';
    const { findings } = analyzeText(
        text,
        pack({ cliches: ['geschlossene faust'] }),
    );
    expect(findings).toEqual([
        { category: 'cliche', from: 6, to: 24, word: 'geschlossene Faust' },
    ]);
});

test('cliché phrases only match on word boundaries', () => {
    const { findings } = analyzeText(
        'Oder Hasen liefen über das Feld.',
        pack({ cliches: ['der hasen'] }),
    );
    expect(findings).toEqual([]);
});

test('cliché boundaries handle non-ASCII letters', () => {
    const { findings } = analyzeText(
        'Er lief darüber hinweg.',
        pack({ cliches: ['über hinweg'] }),
    );
    expect(findings).toEqual([]);
});

test('flags pack patterns with their id', () => {
    const { findings } = analyzeText(
        'Es gibt viele Gründe dafür.',
        pack({ patterns: [{ id: 'esGibt', regex: '\\bes gibt\\b' }] }),
    );
    expect(findings).toEqual([
        {
            category: 'pattern',
            from: 0,
            to: 7,
            word: 'Es gibt',
            patternId: 'esGibt',
        },
    ]);
});

test('an invalid pack regex is skipped, not thrown', () => {
    const { findings } = analyzeText(
        'Ganz normaler Text.',
        pack({ patterns: [{ id: 'broken', regex: '([' }] }),
    );
    expect(findings).toEqual([]);
});

test('findings are sorted by position', () => {
    const text = 'Es gibt eigentlich nichts.';
    const { findings } = analyzeText(
        text,
        pack({
            fillers: ['eigentlich'],
            patterns: [{ id: 'esGibt', regex: '\\bes gibt\\b' }],
        }),
    );
    expect(findings.map((f) => f.category)).toEqual(['pattern', 'filler']);
});

test('flags exact word repetition within the window, linking the pair', () => {
    const text = 'Der Drucker streikte. Der Drucker war alt.';
    const { findings } = analyzeText(text, pack());
    expect(findings).toEqual([
        {
            category: 'repetition',
            from: 4,
            to: 11,
            word: 'Drucker',
            partner: { from: 26, to: 33, word: 'Drucker' },
        },
        {
            category: 'repetition',
            from: 26,
            to: 33,
            word: 'Drucker',
            partner: { from: 4, to: 11, word: 'Drucker' },
        },
    ]);
});

test('flags inflected repetition via shared prefix', () => {
    const text = 'Die leere Seite starrte zurück. Er sah nur leeren Raum.';
    const { findings } = analyzeText(text, pack());
    expect(findings.map((f) => f.word)).toEqual(['leere', 'leeren']);
});

test('repetition beyond the 50-word window is not flagged', () => {
    const alphabet = 'abcdefghijklmnopqrstuvwxyz';
    const distinct = (count: number) =>
        Array.from(
            { length: count },
            (_, i) => `q${alphabet[Math.floor(i / 26)]}${alphabet[i % 26]}o`,
        ).join(' ');
    const near = `Tasse ${distinct(40)} Tasse.`;
    const far = `Tasse ${distinct(55)} Tasse.`;
    expect(analyzeText(near, pack()).findings).toHaveLength(2);
    expect(analyzeText(far, pack()).findings).toHaveLength(0);
});

test('repetition skips stopwords, ignored and short words', () => {
    const text = 'Eine Tasse und noch eine Tasse, sah er, sah sie.';
    const { findings } = analyzeText(text, pack({ stopwords: ['eine'] }));
    expect(findings.map((f) => f.word)).toEqual(['Tasse', 'Tasse']);
    expect(
        analyzeText(text, pack({ stopwords: ['eine'] }), {
            ignoredWords: ['tasse'],
        }).findings,
    ).toEqual([]);
});

test('a word flagged by a word list is not double-flagged as repetition', () => {
    const text = 'Das war eigentlich klar. Es war eigentlich offensichtlich.';
    const { findings } = analyzeText(text, pack({ fillers: ['eigentlich'] }));
    expect(findings).toHaveLength(2);
    expect(findings.every((f) => f.category === 'filler')).toBe(true);
});

test('computes word and sentence stats', () => {
    const { stats } = analyzeText(
        'Der Mann schlief. Die Frau las ein Buch.',
        pack(),
    );
    expect(stats.wordCount).toBe(8);
    expect(stats.sentenceCount).toBe(2);
    expect(stats.sentenceLengths).toEqual([3, 5]);
    expect(stats.avgSentenceLength).toBeCloseTo(4);
});

test('uses the editor word-count rules for style statistics', () => {
    const { stats } = analyzeText(
        'NASA launched 2 rockets -- well-known. \u4f60\u597d',
        pack(),
    );

    expect(stats.wordCount).toBe(7);
});

test('defaults to LIX readability when the pack names no formula', () => {
    const { stats } = analyzeText('Der Hund läuft schnell.', pack());
    // LIX = avg sentence length + % of words longer than 6 chars = 4 + 25
    expect(stats.readability).toBeCloseTo(29);
    expect(stats.readabilityFormula).toBe('lix');
});

test('computes Amstad readability for German packs, clamped to 0-100', () => {
    const { stats } = analyzeText(
        'Der Hund läuft. Die Katze schläft.',
        pack({ readability: { formula: 'amstad' } }),
    );
    expect(stats.readability).toBe(100);
});

test('computes adjective ratio from pack suffixes, tolerating inflection', () => {
    const { stats } = analyzeText(
        'Der freundliche Hund war ruhig und glücklich.',
        pack({ adjectiveSuffixes: ['lich', 'ig'] }),
    );
    expect(stats.adjectiveRatio).toBeCloseTo(3 / 7);
});

test('empty text yields empty findings and null readability', () => {
    const { findings, stats } = analyzeText('', pack());
    expect(findings).toEqual([]);
    expect(stats.wordCount).toBe(0);
    expect(stats.sentenceCount).toBe(0);
    expect(stats.readability).toBeNull();
    expect(stats.adjectiveRatio).toBeNull();
});

test('reports which categories the pack can flag', () => {
    const { available } = analyzeText('Text.', pack({ fillers: ['nur'] }));
    expect(available).toEqual({
        filler: true,
        weakVerb: false,
        filterWord: false,
        cliche: false,
        pattern: false,
        repetition: true,
    });
});

test('disabled categories are skipped', () => {
    const { findings } = analyzeText(
        'Das war eigentlich gut, dachte er.',
        pack({ fillers: ['eigentlich'], filterWords: ['dachte'] }),
        { categories: { filler: false } },
    );
    expect(findings).toHaveLength(1);
    expect(findings[0].category).toBe('filterWord');
});
