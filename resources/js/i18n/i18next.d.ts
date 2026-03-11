import 'i18next';

import type enCommon from './en/common.json';
import type enEditor from './en/editor.json';
import type enDashboard from './en/dashboard.json';
import type enSettings from './en/settings.json';
import type enOnboarding from './en/onboarding.json';
import type enPlot from './en/plot.json';
import type enWiki from './en/wiki.json';
import type enAi from './en/ai.json';

declare module 'i18next' {
    interface CustomTypeOptions {
        defaultNS: 'common';
        resources: {
            common: typeof enCommon;
            editor: typeof enEditor;
            dashboard: typeof enDashboard;
            settings: typeof enSettings;
            onboarding: typeof enOnboarding;
            plot: typeof enPlot;
            wiki: typeof enWiki;
            ai: typeof enAi;
        };
    }
}
