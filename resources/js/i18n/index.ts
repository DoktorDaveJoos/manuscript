import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

import deAi from './de/ai.json';
import deCommon from './de/common.json';
import deDashboard from './de/dashboard.json';
import deEditor from './de/editor.json';
import deOnboarding from './de/onboarding.json';
import dePlot from './de/plot.json';
import deSettings from './de/settings.json';
import deWiki from './de/wiki.json';
import enAi from './en/ai.json';
import enCommon from './en/common.json';
import enDashboard from './en/dashboard.json';
import enEditor from './en/editor.json';
import enOnboarding from './en/onboarding.json';
import enPlot from './en/plot.json';
import enSettings from './en/settings.json';
import enWiki from './en/wiki.json';

import esAi from './es/ai.json';
import esCommon from './es/common.json';
import esDashboard from './es/dashboard.json';
import esEditor from './es/editor.json';
import esOnboarding from './es/onboarding.json';
import esPlot from './es/plot.json';
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
            wiki: enWiki,
            ai: enAi,
        },
        de: {
            common: deCommon,
            editor: deEditor,
            dashboard: deDashboard,
            settings: deSettings,
            onboarding: deOnboarding,
            plot: dePlot,
            wiki: deWiki,
            ai: deAi,
        },
        es: {
            common: esCommon,
            editor: esEditor,
            dashboard: esDashboard,
            settings: esSettings,
            onboarding: esOnboarding,
            plot: esPlot,
            wiki: esWiki,
            ai: esAi,
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
        'wiki',
        'ai',
    ],
    interpolation: {
        escapeValue: false,
    },
});

export default i18n;
