import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import DebugOverlay from '@/components/ui/DebugOverlay';
import UpdateDialog from '@/components/ui/UpdateDialog';
import { checkForUpdates } from '@/hooks/useAutoUpdater';
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
        const settings = props.initialPage.props.app_settings as
            | AppSettings
            | undefined;
        i18n.changeLanguage(settings?.locale ?? 'en');

        const appVersion =
            (props.initialPage.props.app_version as string) ?? '0.0.0';

        if (import.meta.env.VITE_SENTRY_DSN) {
            import('@sentry/react').then((Sentry) => {
                Sentry.init({
                    dsn: import.meta.env.VITE_SENTRY_DSN,
                    release: appVersion,
                    environment: import.meta.env.MODE,
                    tracesSampleRate: 0,
                    beforeSend(event) {
                        const isUnhandled = event.exception?.values?.some(
                            (e) => e.mechanism?.handled === false,
                        );
                        if (isUnhandled) return event;
                        return settings?.send_error_reports ? event : null;
                    },
                });
            });
        }
        const root = createRoot(el);

        root.render(
            <DebugOverlay>
                <App {...props} />
                <UpdateDialog currentVersion={appVersion} />
            </DebugOverlay>,
        );

        // Check for updates every 4 hours (apps that stay open for days would otherwise miss updates)
        setInterval(checkForUpdates, 4 * 60 * 60 * 1000);
    },
    progress: {
        color: 'var(--color-ink-muted)',
    },
});
