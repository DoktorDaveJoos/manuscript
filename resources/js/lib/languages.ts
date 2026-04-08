/**
 * Supported book languages and their Hunspell locale mappings.
 * Used by both the book creation form and the spell-check engine.
 */

export const BOOK_LANGUAGES = [
    { value: 'de', label: 'Deutsch', locale: 'de_DE' },
    { value: 'en', label: 'English', locale: 'en_US' },
    { value: 'es', label: 'Español', locale: 'es_ES' },
    { value: 'fr', label: 'Français', locale: 'fr_FR' },
    { value: 'it', label: 'Italiano', locale: 'it_IT' },
    { value: 'nl', label: 'Nederlands', locale: 'nl_NL' },
    { value: 'pt', label: 'Português', locale: 'pt_PT' },
] as const;

export type BookLanguage = (typeof BOOK_LANGUAGES)[number]['value'];

export const LOCALE_MAP: Record<string, string> = Object.fromEntries(
    BOOK_LANGUAGES.map(({ value, locale }) => [value, locale]),
);
