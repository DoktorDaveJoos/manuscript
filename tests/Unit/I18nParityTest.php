<?php

/**
 * Every i18n namespace must ship the same key set in every locale. A key that
 * exists in one locale but not another silently falls back to English (or the
 * inline defaultValue), which surfaces as untranslated UI.
 */
const I18N_LOCALES = ['en', 'de', 'es'];

function i18nBasePath(): string
{
    return dirname(__DIR__, 2).'/resources/js/i18n';
}

test('every locale ships the same namespace files', function () {
    $base = i18nBasePath();

    $namespaces = [];
    foreach (I18N_LOCALES as $locale) {
        $namespaces[$locale] = collect(glob("{$base}/{$locale}/*.json"))
            ->map(fn (string $path) => basename($path))
            ->sort()
            ->values()
            ->all();
    }

    foreach (I18N_LOCALES as $locale) {
        expect($namespaces[$locale])->toBe($namespaces['en'], "Locale '{$locale}' ships a different set of namespace files than 'en'.");
    }
});

test('locale namespaces expose identical key sets', function () {
    $base = i18nBasePath();

    $failures = [];

    foreach (glob("{$base}/en/*.json") as $enPath) {
        $file = basename($enPath);

        $keys = [];
        foreach (I18N_LOCALES as $locale) {
            $decoded = json_decode((string) file_get_contents("{$base}/{$locale}/{$file}"), true);
            expect($decoded)->toBeArray("{$locale}/{$file} is not valid JSON.");
            $keys[$locale] = array_keys($decoded);
        }

        $union = array_unique(array_merge(...array_values($keys)));

        foreach (I18N_LOCALES as $locale) {
            foreach (array_diff($union, $keys[$locale]) as $missing) {
                $failures[] = "{$locale}/{$file} is missing '{$missing}'";
            }
        }
    }

    expect($failures)->toBe([]);
});
