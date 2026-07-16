import { router } from '@inertiajs/react';
import { Globe } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import Dialog from '@/components/ui/Dialog';
import { RadioGroup, RadioGroupItem } from '@/components/ui/RadioGroup';
import { setAppLanguage } from '@/i18n';
import { saveAppSetting } from '@/lib/utils';

const LANGUAGE_CODES = ['en', 'de', 'es'] as const;
type LanguageCode = (typeof LANGUAGE_CODES)[number];

export default function LanguageSelectionDialog() {
    const { t, i18n } = useTranslation('onboarding');
    const [submitting, setSubmitting] = useState(false);

    const handleSelect = (code: LanguageCode) => {
        void setAppLanguage(code);
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
            title={t('languageSelection.title')}
            width={440}
            backdrop="dark"
            className="overflow-hidden p-0 shadow-xl"
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

            <RadioGroup
                value={i18n.language}
                onValueChange={(value) => handleSelect(value as LanguageCode)}
                className="gap-2.5 px-10 pt-6"
            >
                {LANGUAGE_CODES.map((code) => {
                    const isSelected = i18n.language === code;
                    return (
                        <label
                            key={code}
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
                            <RadioGroupItem value={code} />
                        </label>
                    );
                })}
            </RadioGroup>

            <div className="mt-6 h-px bg-border-light" />

            <div className="flex flex-col items-center px-10 pt-6 pb-8">
                <Button
                    variant="primary"
                    size="lg"
                    disabled={submitting}
                    onClick={handleContinue}
                    className="h-11 w-full"
                >
                    {t('languageSelection.continue')}
                </Button>
            </div>
        </Dialog>
    );
}
