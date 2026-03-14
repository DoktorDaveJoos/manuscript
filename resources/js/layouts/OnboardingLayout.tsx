import CrashReportDialog from '@/components/onboarding/CrashReportDialog';
import LanguageSelector from '@/components/ui/LanguageSelector';
import type { AppSettings } from '@/types/models';
import { Head, Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';

export default function OnboardingLayout({ children, title }: PropsWithChildren<{ title?: string }>) {
    const { t } = useTranslation();
    const { app_settings } = usePage<{ app_settings: AppSettings }>().props;

    return (
        <>
            <Head title={title} />
            {!app_settings.crash_report_prompted && <CrashReportDialog />}
            <div className="flex min-h-screen flex-col bg-surface">
                <header className="flex items-center justify-between px-10 py-4">
                    <span className="text-[13px] font-semibold uppercase tracking-[0.08em] text-ink">
                        Manuscript
                    </span>
                    <div className="flex items-center gap-3">
                        <LanguageSelector />
                        <Link href="/settings" className="text-[13px] text-ink-muted hover:text-ink">{t('settings')}</Link>
                    </div>
                </header>

                <main className="flex flex-1 flex-col">{children}</main>
            </div>
        </>
    );
}
