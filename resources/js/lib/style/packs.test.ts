import { readdirSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { describe, expect, test } from 'vitest';
import type { StylePack } from './types';

const PACKS_DIR = path.resolve(
    import.meta.dirname,
    '../../../../public/style-packs',
);
const ALL_LANGUAGES = ['de', 'en', 'es', 'fr', 'it', 'nl', 'pt', 'sv'];
/** Languages shipping full rule packs; the rest are stopword-only. */
const LAUNCH_LANGUAGES = ['de', 'en', 'fr', 'sv'];
const READABILITY_FORMULAS = ['flesch', 'amstad', 'kandel', 'lix'];

function loadPack(language: string): StylePack {
    return JSON.parse(
        readFileSync(path.join(PACKS_DIR, `${language}.json`), 'utf-8'),
    ) as StylePack;
}

test('every supported language ships a pack', () => {
    const shipped = readdirSync(PACKS_DIR)
        .filter((file) => file.endsWith('.json'))
        .map((file) => file.replace('.json', ''))
        .sort();
    expect(shipped).toEqual(ALL_LANGUAGES);
});

describe.each(ALL_LANGUAGES)('pack %s', (language) => {
    const pack = () => loadPack(language);

    test('has a valid shape', () => {
        const p = pack();
        expect(typeof p.version).toBe('number');
        for (const key of [
            'stopwords',
            'fillers',
            'weakVerbs',
            'filterWords',
            'cliches',
        ] as const) {
            expect(Array.isArray(p[key]), `${key} must be an array`).toBe(true);
            for (const entry of p[key]) {
                expect(typeof entry).toBe('string');
                expect(entry.trim()).toBe(entry);
                expect(entry.length).toBeGreaterThan(0);
            }
        }
        expect(Array.isArray(p.patterns)).toBe(true);
    });

    test('word lists are lowercase and free of duplicates', () => {
        const p = pack();
        for (const key of [
            'stopwords',
            'fillers',
            'weakVerbs',
            'filterWords',
            'cliches',
        ] as const) {
            const entries = p[key];
            expect(new Set(entries).size, `${key} has duplicates`).toBe(
                entries.length,
            );
            for (const entry of entries) {
                expect(entry, `${key} entry not lowercase`).toBe(
                    entry.toLowerCase(),
                );
            }
        }
    });

    test('has a usable stopword list for the repetition check', () => {
        expect(pack().stopwords.length).toBeGreaterThanOrEqual(50);
    });

    test('every pattern regex compiles with giu flags', () => {
        for (const { id, regex } of pack().patterns) {
            expect(typeof id).toBe('string');
            expect(
                () => new RegExp(regex, 'giu'),
                `pattern ${id}`,
            ).not.toThrow();
        }
    });
});

describe.each(LAUNCH_LANGUAGES)('launch pack %s', (language) => {
    test('ships substantial rule lists', () => {
        const p = loadPack(language);
        expect(p.fillers.length).toBeGreaterThanOrEqual(30);
        expect(p.filterWords.length).toBeGreaterThanOrEqual(25);
        expect(p.weakVerbs.length).toBeGreaterThanOrEqual(5);
        expect(p.cliches.length).toBeGreaterThanOrEqual(15);
        expect(p.patterns.length).toBeGreaterThanOrEqual(2);
        expect(p.adjectiveSuffixes?.length ?? 0).toBeGreaterThanOrEqual(4);
        expect(READABILITY_FORMULAS).toContain(p.readability?.formula);
    });
});
