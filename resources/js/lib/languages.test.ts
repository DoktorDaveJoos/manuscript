import { existsSync, readFileSync, statSync } from 'node:fs';
import { resolve } from 'node:path';
import { loadModule } from 'hunspell-asm';
import { describe, expect, it } from 'vitest';
import i18n, { setAppLanguage } from '@/i18n';
import deCommon from '@/i18n/de/common.json';
import enCommon from '@/i18n/en/common.json';
import esCommon from '@/i18n/es/common.json';
import { BOOK_LANGUAGES, LOCALE_MAP } from './languages';

const translations: Record<string, Record<string, string>> = {
    de: deCommon,
    en: enCommon,
    es: esCommon,
};

const expectedLanguages = [
    'ca',
    'cs',
    'da',
    'de',
    'en',
    'es',
    'et',
    'fr',
    'ga',
    'hr',
    'hu',
    'is',
    'it',
    'lb',
    'lt',
    'lv',
    'nb',
    'nl',
    'nn',
    'pl',
    'pt',
    'ro',
    'sk',
    'sl',
    'sv',
    'tr',
];

describe('book languages', () => {
    it('exposes the supported Latin-script European languages', () => {
        expect(BOOK_LANGUAGES.map(({ value }) => value)).toEqual(
            expectedLanguages,
        );
    });

    it('maps every selectable language to shipped Hunspell files', () => {
        for (const { value, locale } of BOOK_LANGUAGES) {
            expect(LOCALE_MAP[value]).toBe(locale);

            for (const extension of ['aff', 'dic']) {
                const dictionaryPath = resolve(
                    `public/dictionaries/${value}/${locale}.${extension}`,
                );

                expect(existsSync(dictionaryPath), dictionaryPath).toBe(true);
                expect(
                    statSync(dictionaryPath).size,
                    dictionaryPath,
                ).toBeGreaterThan(0);
            }
        }
    });

    it('translates every language label in every app locale', () => {
        for (const [appLocale, messages] of Object.entries(translations)) {
            for (const { labelKey } of BOOK_LANGUAGES) {
                expect(
                    messages[labelKey],
                    `${appLocale}:${labelKey}`,
                ).toBeTruthy();
            }
        }

        expect(deCommon['languages.sv']).toBe('Schwedisch');
    });

    it('uses the active app locale for language labels', async () => {
        await setAppLanguage('de');

        expect(i18n.t('languages.sv')).toBe('Schwedisch');

        await setAppLanguage('en');
    });

    it('loads every shipped dictionary in Hunspell', async () => {
        const factory = await loadModule();

        for (const { value, locale } of BOOK_LANGUAGES) {
            const affixPath = factory.mountBuffer(
                readFileSync(
                    resolve(`public/dictionaries/${value}/${locale}.aff`),
                ),
                `${locale}.aff`,
            );
            const dictionaryPath = factory.mountBuffer(
                readFileSync(
                    resolve(`public/dictionaries/${value}/${locale}.dic`),
                ),
                `${locale}.dic`,
            );
            const hunspell = factory.create(affixPath, dictionaryPath);

            expect(hunspell.spell('zzqxmanuscriptzzqx'), value).toBe(false);

            hunspell.dispose();
            factory.unmount(affixPath);
            factory.unmount(dictionaryPath);
        }
    });
});
