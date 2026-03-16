import UpdateDialog from '@/components/ui/UpdateDialog';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import '../css/app.css';
import i18n from './i18n';
import { initTheme } from './lib/theme';
import type { AppSettings } from './types/models';

initTheme();

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const locale = (props.initialPage.props.locale as string) ?? 'en';
        i18n.changeLanguage(locale);

        const settings = props.initialPage.props.app_settings as AppSettings | undefined;
        if (import.meta.env.VITE_SENTRY_ELECTRON_DSN && settings?.send_error_reports) {
            import('@sentry/electron/renderer').then((Sentry) => {
                Sentry.init({
                    dsn: import.meta.env.VITE_SENTRY_ELECTRON_DSN,
                    tracesSampleRate: 0,
                });
            });
        }

        const appVersion = (props.initialPage.props.app_version as string) ?? '0.0.0';
        const root = createRoot(el);

        root.render(
            <>
                <App {...props} />
                <UpdateDialog currentVersion={appVersion} />
            </>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});
