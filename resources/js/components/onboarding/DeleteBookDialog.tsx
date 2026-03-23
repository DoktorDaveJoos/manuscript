import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Trans, useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import Dialog from '@/components/ui/Dialog';
import FormField from '@/components/ui/FormField';
import Input from '@/components/ui/Input';
import type { Book } from '@/types/models';
import { destroy } from '@/actions/App/Http/Controllers/BookController';

export default function DeleteBookDialog({
    book,
    onClose,
}: {
    book: Book;
    onClose: () => void;
}) {
    const { t } = useTranslation('onboarding');
    const [confirmation, setConfirmation] = useState('');
    const [processing, setProcessing] = useState(false);

    const isConfirmed = confirmation === book.title;

    function handleDelete() {
        if (!isConfirmed) return;

        setProcessing(true);
        router.delete(destroy.url(book), {
            onSuccess: () => onClose(),
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <Dialog onClose={onClose} backdrop="dark" className="gap-6">
            <div className="flex flex-col gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-delete-bg">
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
                <h2 className="font-serif text-2xl leading-8 tracking-[-0.01em] text-ink">
                    {t('deleteBook.title')}
                </h2>
                <p className="text-sm leading-[22px] text-ink-muted">
                    <Trans
                        i18nKey="deleteBook.description"
                        ns="onboarding"
                        values={{ title: book.title }}
                        components={{
                            strong: <span className="font-medium text-ink" />,
                        }}
                    />
                </p>
            </div>

            <FormField label={t('deleteBook.confirmLabel')}>
                <Input
                    variant="dialog"
                    type="text"
                    value={confirmation}
                    onChange={(e) => setConfirmation(e.target.value)}
                    placeholder={book.title}
                    autoFocus
                />
            </FormField>

            <div className="flex items-center justify-end gap-3">
                <Button
                    variant="ghost"
                    size="lg"
                    type="button"
                    onClick={onClose}
                >
                    {t('deleteBook.cancel')}
                </Button>
                <Button
                    variant="danger"
                    size="lg"
                    type="button"
                    disabled={!isConfirmed || processing}
                    onClick={handleDelete}
                >
                    {t('deleteBook.confirm')}
                </Button>
            </div>
        </Dialog>
    );
}
