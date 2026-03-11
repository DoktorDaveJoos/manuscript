import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

import enCommon from './en/common.json';
import enEditor from './en/editor.json';
import enDashboard from './en/dashboard.json';
import enSettings from './en/settings.json';
import enOnboarding from './en/onboarding.json';
import enPlot from './en/plot.json';
import enWiki from './en/wiki.json';
import enAi from './en/ai.json';

import deCommon from './de/common.json';
import deEditor from './de/editor.json';
import deDashboard from './de/dashboard.json';
import deSettings from './de/settings.json';
import deOnboarding from './de/onboarding.json';
import dePlot from './de/plot.json';
import deWiki from './de/wiki.json';
import deAi from './de/ai.json';

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
    },
    lng: 'en',
    fallbackLng: 'en',
    defaultNS: 'common',
    ns: ['common', 'editor', 'dashboard', 'settings', 'onboarding', 'plot', 'wiki', 'ai'],
    interpolation: {
        escapeValue: false,
    },
});

export default i18n;
