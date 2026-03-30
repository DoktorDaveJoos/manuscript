import { router } from '@inertiajs/react';
import { Globe } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Dialog from '@/components/ui/Dialog';
import { saveAppSetting } from '@/lib/utils';

const LANGUAGE_CODES = ['en', 'de', 'es'] as const;
type LanguageCode = (typeof LANGUAGE_CODES)[number];

export default function LanguageSelectionDialog() {
    const { t, i18n } = useTranslation('onboarding');
    const [submitting, setSubmitting] = useState(false);

    const handleSelect = (code: LanguageCode) => {
        i18n.changeLanguage(code);
    };

    const handleContinue = () => {
        if (submitting) return;
        setSubmitting(true);

        Promise.all([
            saveAppSetting('locale', i18n.language),
            saveAppSetting('language_prompted', true),
        ])
            .then(() => router.reload({ only: ['app_settings'] }))
            .catch(() => setSubmitting(false));
    };

    return (
        <Dialog
            onClose={() => {}}
            width={440}
            backdrop="dark"
            className="overflow-hidden rounded-xl p-0 shadow-[0_16px_48px_-4px_rgba(0,0,0,0.15),0_4px_12px_rgba(0,0,0,0.05)]"
        >
            <div className="flex flex-col items-center gap-4 px-10 pt-8">
                <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-accent-light">
                    <Globe className="h-7 w-7 text-accent" />
                </div>
                <h2 className="text-xl font-semibold tracking-[-0.3px] text-ink">
                    {t('languageSelection.title')}
                </h2>
                <p className="text-center text-sm leading-[1.55] whitespace-pre-line text-ink-soft">
                    {t('languageSelection.description')}
                </p>
            </div>

            <div className="flex flex-col gap-2.5 px-10 pt-6">
                {LANGUAGE_CODES.map((code) => {
                    const isSelected = i18n.language === code;
                    return (
                        <button
                            key={code}
                            type="button"
                            onClick={() => handleSelect(code)}
                            className={`flex w-full items-center justify-between rounded-lg px-4 py-3.5 transition-colors ${
                                isSelected
                                    ? 'border-[1.5px] border-accent bg-accent-light'
                                    : 'border border-border bg-surface-card'
                            }`}
                        >
                            <div className="flex flex-col items-start gap-0.5">
                                <span
                                    className={`text-sm ${isSelected ? 'font-semibold' : 'font-medium'} text-ink`}
                                >
                                    {t(`languageSelection.${code}`)}
                                </span>
                                <span className="text-xs text-ink-muted">
                                    {t(`languageSelection.${code}Sub`)}
                                </span>
                            </div>
                            <div
                                className={`flex h-5 w-5 items-center justify-center rounded-full border-[1.5px] ${
                                    isSelected
                                        ? 'border-accent'
                                        : 'border-border-dashed'
                                }`}
                            >
                                {isSelected && (
                                    <div className="h-2.5 w-2.5 rounded-full bg-accent" />
                                )}
                            </div>
                        </button>
                    );
                })}
            </div>

            <div className="mt-6 h-px bg-border-light" />

            <div className="flex flex-col items-center px-10 pt-6 pb-8">
                <button
                    type="button"
                    disabled={submitting}
                    onClick={handleContinue}
                    className="flex h-11 w-full items-center justify-center rounded-lg bg-ink text-sm font-semibold text-surface shadow-[0_1px_3px_rgba(0,0,0,0.1)] disabled:opacity-50"
                >
                    {t('languageSelection.continue')}
                </button>
            </div>
        </Dialog>
    );
}
