import { useForm } from '@inertiajs/react';
import type {FormEvent} from 'react';
import { useTranslation } from 'react-i18next';
import { update } from '@/actions/App/Http/Controllers/BookController';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';
import type { Book } from '@/types/models';

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
                    <Input
                        variant="dialog"
                        type="text"
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                        placeholder={t('renameBook.placeholderTitle')}
                        autoFocus
                    />
                    {form.errors.title && <span className="text-xs text-red-600">{form.errors.title}</span>}
                </fieldset>

                <div className="flex items-center justify-end gap-3">
                    <Button variant="ghost" size="lg" type="button" onClick={onClose}>
                        {t('renameBook.cancel')}
                    </Button>
                    <Button variant="primary" size="lg" type="submit" disabled={form.processing}>
                        {t('renameBook.save')}
                    </Button>
                </div>
            </form>
        </div>
    );
}
