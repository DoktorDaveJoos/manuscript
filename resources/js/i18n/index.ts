import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

import deAiDashboard from './de/ai-dashboard.json';
import deAi from './de/ai.json';
import deCommon from './de/common.json';
import deDashboard from './de/dashboard.json';
import deEditor from './de/editor.json';
import deEditorialReview from './de/editorial-review.json';
import deExport from './de/export.json';
import deOnboarding from './de/onboarding.json';
import dePlot from './de/plot.json';
import dePublish from './de/publish.json';
import deSettings from './de/settings.json';
import deWiki from './de/wiki.json';
import enAiDashboard from './en/ai-dashboard.json';
import enAi from './en/ai.json';
import enCommon from './en/common.json';
import enDashboard from './en/dashboard.json';
import enEditor from './en/editor.json';
import enEditorialReview from './en/editorial-review.json';
import enExport from './en/export.json';
import enOnboarding from './en/onboarding.json';
import enPlot from './en/plot.json';
import enPublish from './en/publish.json';
import enSettings from './en/settings.json';
import enWikiPanel from './en/wiki-panel.json';
import enWiki from './en/wiki.json';

import esAiDashboard from './es/ai-dashboard.json';
import esAi from './es/ai.json';
import esCommon from './es/common.json';
import esDashboard from './es/dashboard.json';
import esEditor from './es/editor.json';
import esEditorialReview from './es/editorial-review.json';
import esExport from './es/export.json';
import esOnboarding from './es/onboarding.json';
import esPlot from './es/plot.json';
import esPublish from './es/publish.json';
import esSettings from './es/settings.json';
import esWiki from './es/wiki.json';

i18n.use(initReactI18next).init({
    resources: {
        en: {
            common: enCommon,
            editor: enEditor,
            dashboard: enDashboard,
            settings: enSettings,
            onboarding: enOnboarding,
            plot: enPlot,
            publish: enPublish,
            wiki: enWiki,
            'wiki-panel': enWikiPanel,
            ai: enAi,
            export: enExport,
            'editorial-review': enEditorialReview,
            'ai-dashboard': enAiDashboard,
        },
        de: {
            common: deCommon,
            editor: deEditor,
            dashboard: deDashboard,
            settings: deSettings,
            onboarding: deOnboarding,
            plot: dePlot,
            publish: dePublish,
            wiki: deWiki,
            ai: deAi,
            export: deExport,
            'editorial-review': deEditorialReview,
            'ai-dashboard': deAiDashboard,
        },
        es: {
            common: esCommon,
            editor: esEditor,
            dashboard: esDashboard,
            settings: esSettings,
            onboarding: esOnboarding,
            plot: esPlot,
            publish: esPublish,
            wiki: esWiki,
            ai: esAi,
            export: esExport,
            'editorial-review': esEditorialReview,
            'ai-dashboard': esAiDashboard,
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
        'publish',
        'wiki',
        'wiki-panel',
        'ai',
        'export',
        'editorial-review',
        'ai-dashboard',
    ],
    interpolation: {
        escapeValue: false,
    },
});

export default i18n;
