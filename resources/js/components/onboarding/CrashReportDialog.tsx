import { router } from '@inertiajs/react';
import { Check, ShieldCheck, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import Dialog from '@/components/ui/Dialog';
import { saveAppSetting } from '@/lib/utils';

export default function CrashReportDialog() {
    const { t } = useTranslation('onboarding');
    const [submitting, setSubmitting] = useState(false);

    const dismiss = (enableReports: boolean) => {
        if (submitting) return;
        setSubmitting(true);

        const saves = [saveAppSetting('crash_report_prompted', true)];
        if (enableReports) {
            saves.push(saveAppSetting('send_error_reports', true));
        }

        Promise.all(saves)
            .then(() => router.reload({ only: ['app_settings'] }))
            .catch(() => setSubmitting(false));
    };

    return (
        <Dialog
            onClose={() => {}}
            title={t('crashReport.title')}
            width={440}
            backdrop="dark"
            className="overflow-hidden p-0 shadow-xl"
        >
            {/* Header */}
            <div className="flex flex-col items-center gap-4 px-10 pt-8">
                <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-b from-accent-light to-surface-warm">
                    <ShieldCheck className="h-7 w-7 text-accent" />
                </div>
                <h2 className="text-xl font-semibold tracking-[-0.01em] text-ink">
                    {t('crashReport.title')}
                </h2>
                <p className="text-center text-sm leading-[1.55] text-ink-muted">
                    {t('crashReport.description')}
                </p>
            </div>

            {/* Body */}
            <div className="flex flex-col gap-4 px-10 pt-6">
                {/* What we send */}
                <div className="flex flex-col gap-2.5">
                    <span className="text-[11px] font-semibold tracking-[1.2px] text-ink-faint">
                        {t('crashReport.whatWeSend')}
                    </span>
                    <InfoRow icon="check" text={t('crashReport.send1')} />
                    <InfoRow icon="check" text={t('crashReport.send2')} />
                    <InfoRow icon="check" text={t('crashReport.send3')} />
                </div>

                {/* What we never send */}
                <div className="flex flex-col gap-2.5">
                    <span className="text-[11px] font-semibold tracking-[1.2px] text-ink-faint">
                        {t('crashReport.whatWeNeverSend')}
                    </span>
                    <InfoRow icon="x" text={t('crashReport.never1')} />
                    <InfoRow icon="x" text={t('crashReport.never2')} />
                    <InfoRow icon="x" text={t('crashReport.never3')} />
                </div>
            </div>

            {/* Divider */}
            <div className="mt-6 h-px bg-border-light" />

            {/* Footer */}
            <div className="flex flex-col items-center gap-4 px-10 pt-6 pb-8">
                <div className="flex w-full flex-col gap-3">
                    <Button
                        variant="primary"
                        size="lg"
                        disabled={submitting}
                        onClick={() => dismiss(true)}
                        className="h-11 w-full"
                    >
                        {t('crashReport.enable')}
                    </Button>
                    <Button
                        variant="secondary"
                        size="lg"
                        disabled={submitting}
                        onClick={() => dismiss(false)}
                        className="h-11 w-full"
                    >
                        {t('crashReport.notNow')}
                    </Button>
                </div>
                <span className="text-center text-[12px] text-ink-faint">
                    {t('crashReport.settingsNote')}
                </span>
            </div>
        </Dialog>
    );
}

function InfoRow({ icon, text }: { icon: 'check' | 'x'; text: string }) {
    return (
        <div className="flex items-center gap-2.5">
            {icon === 'check' ? (
                <Check className="h-4 w-4 shrink-0 text-status-final" />
            ) : (
                <X className="h-4 w-4 shrink-0 text-danger" />
            )}
            <span className="text-[13px] text-ink-soft">{text}</span>
        </div>
    );
}
