import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { Toaster } from 'sonner';
import BootErrorScreen from '@/components/BootErrorScreen';
import DatabaseRepairedDialog from '@/components/DatabaseRepairedDialog';
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
        const bootError = props.initialPage.props.boot_error as
            | boolean
            | undefined;
        const databaseRepaired = props.initialPage.props.database_repaired as
            | boolean
            | undefined;

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

        // If the database is completely unavailable, show a standalone error
        // screen that bypasses Inertia page resolution entirely.
        if (bootError) {
            root.render(<BootErrorScreen />);
            return;
        }

        root.render(
            <DebugOverlay>
                <App {...props} />
                <UpdateDialog currentVersion={appVersion} />
                <UpdateScheduler />
                {databaseRepaired && <DatabaseRepairedDialog />}
                <Toaster
                    position="top-right"
                    closeButton
                    richColors
                    theme="system"
                />
            </DebugOverlay>,
        );
    },
    progress: {
        color: 'var(--color-ink-muted)',
    },
});

// Polls for app updates every 4 hours. Lives inside the React tree so its
// interval is cleaned up if the renderer ever full-reloads — module-level
// setInterval would stack a new timer on each boot.
function UpdateScheduler() {
    useEffect(() => {
        const id = window.setInterval(checkForUpdates, 4 * 60 * 60 * 1000);
        return () => window.clearInterval(id);
    }, []);
    return null;
}
