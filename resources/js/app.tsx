import { createInertiaApp, router } from '@inertiajs/react';
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
import { setAppLanguage } from './i18n';
import { initAnalytics, screenNameFor, track } from './lib/analytics';
import { getTheme, initTheme } from './lib/theme';
import type { AppSettings, License } from './types/models';

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
        const languageReady = setAppLanguage(settings?.locale ?? 'en');

        const appVersion =
            (props.initialPage.props.app_version as string) ?? '0.0.0';
        const license = props.initialPage.props.license as License | undefined;
        const aiConfigured =
            (props.initialPage.props.ai_configured as boolean | undefined) ??
            false;

        void initAnalytics({
            version: appVersion,
            enabled: settings?.send_analytics ?? true,
        }).then(() => {
            track('app_started', {
                version: appVersion,
                locale: settings?.locale ?? 'en',
                theme: getTheme(),
                license_active: license?.active ?? false,
                ai_configured: aiConfigured,
            });
            track('screen_view', {
                name: screenNameFor(props.initialPage.component),
            });
        });

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

        // Wait for the (local, near-instant) locale chunk before first paint
        // so non-English users never see an English flash. English resolves
        // immediately — it ships in the entry bundle.
        void languageReady.finally(() => {
            root.render(
                <DebugOverlay>
                    <App {...props} />
                    <UpdateDialog currentVersion={appVersion} />
                    <UpdateScheduler />
                    <AnalyticsTracker />
                    {databaseRepaired && <DatabaseRepairedDialog />}
                    <Toaster
                        position="bottom-center"
                        closeButton
                        theme="system"
                    />
                </DebugOverlay>,
            );
        });
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

function AnalyticsTracker() {
    useEffect(() => {
        return router.on('navigate', (event) => {
            track('screen_view', {
                name: screenNameFor(event.detail.page.component),
            });
        });
    }, []);

    return null;
}
