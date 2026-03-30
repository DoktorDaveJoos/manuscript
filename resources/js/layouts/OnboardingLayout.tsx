import { Head, Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';
import CrashReportDialog from '@/components/onboarding/CrashReportDialog';
import LanguageSelectionDialog from '@/components/onboarding/LanguageSelectionDialog';
import type { AppSettings } from '@/types/models';

export default function OnboardingLayout({
    children,
    title,
}: PropsWithChildren<{ title?: string }>) {
    const { t } = useTranslation();
    const { app_settings } = usePage<{ app_settings: AppSettings }>().props;

    return (
        <>
            <Head title={title} />
            {!app_settings.language_prompted && <LanguageSelectionDialog />}
            {app_settings.language_prompted &&
                !app_settings.crash_report_prompted && <CrashReportDialog />}
            <div className="flex min-h-screen flex-col bg-surface">
                <header className="flex items-center justify-between px-10 py-4">
                    <span className="text-[13px] font-semibold tracking-[0.08em] text-ink uppercase">
                        Manuscript
                    </span>
                    <Link
                        href="/settings"
                        className="text-[13px] text-ink-muted hover:text-ink"
                    >
                        {t('settings')}
                    </Link>
                </header>

                <main className="flex flex-1 flex-col">{children}</main>
            </div>
        </>
    );
}
