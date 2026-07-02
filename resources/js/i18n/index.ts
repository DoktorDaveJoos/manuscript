import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

import enAi from './en/ai.json';
import enCommon from './en/common.json';
import enDashboard from './en/dashboard.json';
import enDesign from './en/design.json';
import enEditor from './en/editor.json';
import enEditorialReview from './en/editorial-review.json';
import enExport from './en/export.json';
import enOnboarding from './en/onboarding.json';
import enPlotCoach from './en/plot-coach.json';
import enPlotPanel from './en/plot-panel.json';
import enPlot from './en/plot.json';
import enPublish from './en/publish.json';
import enSettings from './en/settings.json';
import enWikiPanel from './en/wiki-panel.json';
import enWiki from './en/wiki.json';

// Only the fallback locale ships in the entry bundle. The other locales are
// code-split by Vite via the lazy import.meta.glob below and fetched from the
// local server the first time setAppLanguage() switches to them — keeping
// ~2/3 of the locale JSON out of the main-thread parse on every window open.
const lazyLocaleLoaders: Record<
    string,
    Record<string, () => Promise<unknown>>
> = {
    de: import.meta.glob('./de/*.json'),
    es: import.meta.glob('./es/*.json'),
};

i18n.use(initReactI18next).init({
    resources: {
        en: {
            common: enCommon,
            editor: enEditor,
            dashboard: enDashboard,
            settings: enSettings,
            onboarding: enOnboarding,
            plot: enPlot,
            'plot-coach': enPlotCoach,
            'plot-panel': enPlotPanel,
            publish: enPublish,
            wiki: enWiki,
            'wiki-panel': enWikiPanel,
            ai: enAi,
            export: enExport,
            design: enDesign,
            'editorial-review': enEditorialReview,
        },
    },
    lng: 'en',
    fallbackLng: 'en',
    defaultNS: 'common',
    ns: [
        'common',
        'editor',
        'dashboard',
        'settings',
        'onboarding',
        'plot',
        'plot-coach',
        'plot-panel',
        'publish',
        'wiki',
        'wiki-panel',
        'ai',
        'export',
        'design',
        'editorial-review',
    ],
    interpolation: {
        escapeValue: false,
    },
});

/**
 * Switch the app language, lazy-loading the locale's namespaces first when
 * they aren't bundled. Loading is best-effort: if a chunk fails, the switch
 * still happens and missing keys fall back to English.
 */
export async function setAppLanguage(locale: string): Promise<void> {
    const loaders = lazyLocaleLoaders[locale];

    if (loaders && !i18n.hasResourceBundle(locale, 'common')) {
        try {
            await Promise.all(
                Object.entries(loaders).map(async ([path, load]) => {
                    const namespace = path
                        .split('/')
                        .pop()!
                        .replace(/\.json$/, '');
                    const module = (await load()) as { default: object };
                    i18n.addResourceBundle(locale, namespace, module.default);
                }),
            );
        } catch (error) {
            console.error(`[i18n] Failed to load locale "${locale}":`, error);
        }
    }

    await i18n.changeLanguage(locale);
}

export default i18n;
