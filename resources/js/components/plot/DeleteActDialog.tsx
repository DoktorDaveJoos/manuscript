import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Trans, useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import Dialog from '@/components/ui/Dialog';
import type { Act } from '@/types/models';

export default function DeleteActDialog({
    bookId,
    act,
    onClose,
}: {
    bookId: number;
    act: Act;
    onClose: () => void;
}) {
    const { t } = useTranslation('plot');
    const [processing, setProcessing] = useState(false);

    function handleDelete() {
        setProcessing(true);
        router.delete(`/books/${bookId}/acts/${act.id}`, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <Dialog
            onClose={onClose}
            width={440}
            backdrop="light"
            className="gap-7"
        >
            <div className="flex flex-col gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-[10px] bg-delete-bg">
                    <svg
                        className="h-5 w-5 text-delete"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        strokeWidth={2}
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
                        />
                    </svg>
                </div>
                <h2 className="font-serif text-[32px] leading-10 tracking-[-0.01em] text-ink">
                    {t('deleteAct.title')}
                </h2>
                <p className="text-sm leading-[22px] text-ink-muted">
                    <Trans
                        i18nKey="deleteAct.description"
                        ns="plot"
                        values={{ title: act.title }}
                    >
                        This will permanently delete{' '}
                        <span className="font-medium text-ink">
                            {'{{title}}'}
                        </span>{' '}
                        and all its plot points and beats. This action cannot be
                        undone.
                    </Trans>
                </p>
            </div>

            <div className="flex items-center justify-end gap-3">
                <Button
                    variant="ghost"
                    size="lg"
                    type="button"
                    onClick={onClose}
                >
                    {t('deleteAct.cancel')}
                </Button>
                <Button
                    variant="destructive"
                    size="lg"
                    type="button"
                    disabled={processing}
                    onClick={handleDelete}
                >
                    {t('deleteAct.confirm')}
                </Button>
            </div>
        </Dialog>
    );
}
