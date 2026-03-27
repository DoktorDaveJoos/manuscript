import { Lock } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import { Card, CardContent } from '@/components/ui/Card';
import Dialog from '@/components/ui/Dialog';

const FEATURE_CARDS = [
    {
        titleKey: 'emptyState.features.bigPicture.title',
        descKey: 'emptyState.features.bigPicture.description',
    },
    {
        titleKey: 'emptyState.features.characters.title',
        descKey: 'emptyState.features.characters.description',
    },
    {
        titleKey: 'emptyState.features.pacing.title',
        descKey: 'emptyState.features.pacing.description',
    },
    {
        titleKey: 'emptyState.features.prose.title',
        descKey: 'emptyState.features.prose.description',
    },
] as const;

export default function EditorialReviewEmptyState({
    onStart,
    starting,
    locked = false,
}: {
    onStart: () => void;
    starting: boolean;
    locked?: boolean;
}) {
    const { t } = useTranslation('editorial-review');
    const [showConfirm, setShowConfirm] = useState(false);

    return (
        <>
            <div className="flex flex-1 flex-col items-center gap-12 py-16">
                {/* Hero */}
                <div className="flex max-w-md flex-col items-center gap-4 text-center">
                    <h2 className="font-serif text-[32px] leading-[1.2] font-semibold tracking-[-0.01em] text-ink">
                        {t('emptyState.heading')}
                    </h2>
                    <p className="text-[14px] leading-[1.6] text-ink-muted">
                        {t('emptyState.description')}
                    </p>
                    {locked ? (
                        <Button
                            variant="primary"
                            disabled
                            className="mt-2 text-[13px]"
                        >
                            <Lock size={14} />
                            {t('emptyState.button')}
                        </Button>
                    ) : (
                        <Button
                            variant="primary"
                            onClick={() => setShowConfirm(true)}
                            disabled={starting}
                            className="mt-2 text-[13px]"
                        >
                            {starting
                                ? t('progress.pending')
                                : t('emptyState.button')}
                        </Button>
                    )}
                </div>

                {/* Feature cards */}
                <div className="w-full max-w-4xl">
                    <span className="mb-4 block text-[11px] font-semibold tracking-[0.08em] text-ink-muted uppercase">
                        {t('emptyState.features.title')}
                    </span>
                    <div className="grid grid-cols-4 gap-6">
                        {FEATURE_CARDS.map((card) => (
                            <Card key={card.titleKey}>
                                <CardContent className="p-8">
                                    <h3 className="text-[16px] font-medium text-ink">
                                        {t(card.titleKey)}
                                    </h3>
                                    <p className="mt-2 text-[13px] leading-[1.6] text-ink-muted italic">
                                        {t(card.descKey)}
                                    </p>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </div>
            </div>

            {showConfirm && (
                <Dialog onClose={() => setShowConfirm(false)} width={420}>
                    <div className="flex flex-col gap-6">
                        <div className="flex flex-col gap-2">
                            <h2 className="font-serif text-2xl leading-8 font-semibold tracking-[-0.01em] text-ink">
                                {t('confirm.title')}
                            </h2>
                            <p className="text-sm leading-relaxed text-ink-muted">
                                {t('confirm.description')}
                            </p>
                        </div>
                        <div className="flex items-center justify-end gap-3">
                            <Button
                                variant="secondary"
                                onClick={() => setShowConfirm(false)}
                            >
                                {t('common:cancel')}
                            </Button>
                            <Button
                                variant="primary"
                                onClick={() => {
                                    setShowConfirm(false);
                                    onStart();
                                }}
                                disabled={starting}
                            >
                                {t('common:confirm')}
                            </Button>
                        </div>
                    </div>
                </Dialog>
            )}
        </>
    );
}
