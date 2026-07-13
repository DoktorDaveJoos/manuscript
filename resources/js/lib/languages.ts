/**
 * Supported book languages and their Hunspell locale mappings.
 * Used by both the book creation form and the spell-check engine.
 */

export const BOOK_LANGUAGES = [
    { value: 'ca', labelKey: 'languages.ca', locale: 'ca_ES' },
    { value: 'cs', labelKey: 'languages.cs', locale: 'cs_CZ' },
    { value: 'da', labelKey: 'languages.da', locale: 'da_DK' },
    { value: 'de', labelKey: 'languages.de', locale: 'de_DE' },
    { value: 'en', labelKey: 'languages.en', locale: 'en_US' },
    { value: 'es', labelKey: 'languages.es', locale: 'es_ES' },
    { value: 'et', labelKey: 'languages.et', locale: 'et_EE' },
    { value: 'fr', labelKey: 'languages.fr', locale: 'fr_FR' },
    { value: 'ga', labelKey: 'languages.ga', locale: 'ga_IE' },
    { value: 'hr', labelKey: 'languages.hr', locale: 'hr_HR' },
    { value: 'hu', labelKey: 'languages.hu', locale: 'hu_HU' },
    { value: 'is', labelKey: 'languages.is', locale: 'is_IS' },
    { value: 'it', labelKey: 'languages.it', locale: 'it_IT' },
    { value: 'lb', labelKey: 'languages.lb', locale: 'lb_LU' },
    { value: 'lt', labelKey: 'languages.lt', locale: 'lt_LT' },
    { value: 'lv', labelKey: 'languages.lv', locale: 'lv_LV' },
    { value: 'nb', labelKey: 'languages.nb', locale: 'nb_NO' },
    { value: 'nl', labelKey: 'languages.nl', locale: 'nl_NL' },
    { value: 'nn', labelKey: 'languages.nn', locale: 'nn_NO' },
    { value: 'pl', labelKey: 'languages.pl', locale: 'pl_PL' },
    { value: 'pt', labelKey: 'languages.pt', locale: 'pt_PT' },
    { value: 'ro', labelKey: 'languages.ro', locale: 'ro_RO' },
    { value: 'sk', labelKey: 'languages.sk', locale: 'sk_SK' },
    { value: 'sl', labelKey: 'languages.sl', locale: 'sl_SI' },
    { value: 'sv', labelKey: 'languages.sv', locale: 'sv_SE' },
    { value: 'tr', labelKey: 'languages.tr', locale: 'tr_TR' },
] as const;

export type BookLanguage = (typeof BOOK_LANGUAGES)[number]['value'];

export const LOCALE_MAP: Record<string, string> = Object.fromEntries(
    BOOK_LANGUAGES.map(({ value, locale }) => [value, locale]),
);
