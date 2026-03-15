import { update } from '@/actions/App/Http/Controllers/BookController';
import type { Book } from '@/types/models';
import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';

export default function RenameBookDialog({ book, onClose }: { book: Book; onClose: () => void }) {
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
        <div className="fixed inset-0 z-50 flex items-center justify-center">
            <div className="absolute inset-0" onClick={onClose} />
            <form
                onSubmit={handleSubmit}
                className="relative z-10 flex w-[480px] flex-col gap-8 rounded-xl bg-surface-card p-10 shadow-[0_8px_40px_rgba(0,0,0,0.08)]"
            >
                <div className="flex flex-col gap-2">
                    <h2 className="font-serif text-[32px] leading-10 tracking-[-0.01em] text-ink">{t('renameBook.title')}</h2>
                    <p className="text-sm leading-[22px] text-ink-muted">{t('renameBook.description')}</p>
                </div>

                <fieldset className="flex flex-col gap-1.5">
                    <label className="text-xs font-medium uppercase leading-4 tracking-[0.08em] text-ink-muted">
                        {t('renameBook.labelTitle')}
                    </label>
                    <input
                        type="text"
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                        placeholder={t('renameBook.placeholderTitle')}
                        className="rounded-md border border-border bg-surface px-4 py-3 text-sm leading-[18px] text-ink outline-none placeholder:text-ink-faint"
                        autoFocus
                    />
                    {form.errors.title && <span className="text-xs text-red-600">{form.errors.title}</span>}
                </fieldset>

                <div className="flex items-center justify-end gap-3">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md px-5 py-2.5 text-sm font-medium leading-[18px] text-ink-muted"
                    >
                        {t('renameBook.cancel')}
                    </button>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="rounded-md bg-ink px-6 py-2.5 text-sm font-medium leading-[18px] text-surface disabled:opacity-50"
                    >
                        {t('renameBook.save')}
                    </button>
                </div>
            </form>
        </div>
    );
}
