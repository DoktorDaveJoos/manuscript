import { FileSearch } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import Dialog from '@/components/ui/Dialog';

export default function EditorialReviewEmptyState({
    onStart,
    starting,
}: {
    onStart: () => void;
    starting: boolean;
}) {
    const { t } = useTranslation('editorial-review');
    const [showConfirm, setShowConfirm] = useState(false);

    return (
        <>
            <div className="flex flex-1 flex-col items-center justify-center gap-6 px-12 py-10">
                <div className="flex size-12 items-center justify-center rounded-full bg-neutral-bg">
                    <FileSearch size={24} className="text-ink-muted" />
                </div>
                <div className="flex max-w-md flex-col items-center gap-3 text-center">
                    <h2 className="text-base font-semibold text-ink">
                        {t('emptyState.heading')}
                    </h2>
                    <p className="text-[13px] leading-relaxed text-ink-muted">
                        {t('emptyState.description')}
                    </p>
                </div>
                <Button
                    variant="primary"
                    onClick={() => setShowConfirm(true)}
                    disabled={starting}
                >
                    {starting ? t('progress.pending') : t('emptyState.button')}
                </Button>
            </div>

            {showConfirm && (
                <Dialog onClose={() => setShowConfirm(false)} width={420}>
                    <div className="flex flex-col gap-6">
                        <div className="flex flex-col gap-2">
                            <h2 className="font-serif text-2xl leading-8 font-normal tracking-[-0.01em] text-ink">
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
