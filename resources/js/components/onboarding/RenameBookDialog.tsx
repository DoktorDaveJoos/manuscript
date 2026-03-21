import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { update } from '@/actions/App/Http/Controllers/BookController';
import Button from '@/components/ui/Button';
import Dialog from '@/components/ui/Dialog';
import Input from '@/components/ui/Input';
import type { Book } from '@/types/models';

export default function RenameBookDialog({
    book,
    onClose,
}: {
    book: Book;
    onClose: () => void;
}) {
    const { t } = useTranslation('onboarding');
    const form = useForm({
        title: book.title,
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        form.patch(update.url(book), {
            onSuccess: () => onClose(),
        });
    }

    return (
        <Dialog onClose={onClose} backdrop="none" className="gap-8">
            <form onSubmit={handleSubmit} className="contents">
                <div className="flex flex-col gap-2">
                    <h2 className="font-serif text-[32px] leading-10 tracking-[-0.01em] text-ink">
                        {t('renameBook.title')}
                    </h2>
                    <p className="text-sm leading-[22px] text-ink-muted">
                        {t('renameBook.description')}
                    </p>
                </div>

                <fieldset className="flex flex-col gap-1.5">
                    <label className="text-xs leading-4 font-medium tracking-[0.08em] text-ink-muted uppercase">
                        {t('renameBook.labelTitle')}
                    </label>
                    <Input
                        variant="dialog"
                        type="text"
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                        placeholder={t('renameBook.placeholderTitle')}
                        autoFocus
                    />
                    {form.errors.title && (
                        <span className="text-xs text-red-600">
                            {form.errors.title}
                        </span>
                    )}
                </fieldset>

                <div className="flex items-center justify-end gap-3">
                    <Button
                        variant="ghost"
                        size="lg"
                        type="button"
                        onClick={onClose}
                    >
                        {t('renameBook.cancel')}
                    </Button>
                    <Button
                        variant="primary"
                        size="lg"
                        type="submit"
                        disabled={form.processing}
                    >
                        {t('renameBook.save')}
                    </Button>
                </div>
            </form>
        </Dialog>
    );
}
