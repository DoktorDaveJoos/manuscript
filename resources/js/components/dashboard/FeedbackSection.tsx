import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';

export default function FeedbackSection() {
    const { t } = useTranslation('dashboard');

    return (
        <div className="flex flex-col items-center gap-4 rounded-lg border border-border-subtle bg-surface p-8 text-center">
            <h2 className="font-serif text-xl font-normal tracking-[-0.01em] text-ink">
                {t('feedback.heading')}
            </h2>
            <p className="max-w-md text-sm text-ink-soft">
                {t('feedback.description')}
            </p>
            <Button
                variant="primary"
                onClick={() =>
                    window.open('https://manuscript.canny.io', '_blank')
                }
            >
                {t('feedback.cta')}
            </Button>
        </div>
    );
}
