import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

const LANGUAGES = [
    { value: 'de', label: 'Deutsch' },
    { value: 'en', label: 'English' },
    { value: 'fr', label: 'Fran\u00E7ais' },
    { value: 'es', label: 'Espa\u00F1ol' },
];

export default function CreateBookDialog({ onClose }: { onClose: () => void }) {
    const form = useForm({
        title: '',
        author: '',
        language: 'de',
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        form.post('/books', {
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
                    <h2 className="font-serif text-[32px] leading-10 tracking-[-0.01em] text-ink">New book</h2>
                    <p className="text-sm leading-[22px] text-ink-muted">
                        Give your book a working title. You can change it later.
                    </p>
                </div>

                <div className="flex flex-col gap-6">
                    <fieldset className="flex flex-col gap-1.5">
                        <label className="text-xs font-medium uppercase leading-4 tracking-[0.06em] text-ink-muted">
                            Title
                        </label>
                        <input
                            type="text"
                            value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                            placeholder="The Weight of Silence"
                            className="rounded-md border border-border bg-surface px-4 py-3 text-sm leading-[18px] text-ink outline-none placeholder:text-ink-faint"
                            autoFocus
                        />
                        {form.errors.title && (
                            <span className="text-xs text-red-600">{form.errors.title}</span>
                        )}
                    </fieldset>

                    <fieldset className="flex flex-col gap-1.5">
                        <label className="text-xs font-medium uppercase leading-4 tracking-[0.06em] text-ink-muted">
                            Author
                        </label>
                        <input
                            type="text"
                            value={form.data.author}
                            onChange={(e) => form.setData('author', e.target.value)}
                            placeholder="Your name (optional)"
                            className="rounded-md border border-border bg-surface px-4 py-3 text-sm leading-[18px] text-ink outline-none placeholder:text-ink-faint"
                        />
                    </fieldset>

                    <fieldset className="flex flex-col gap-1.5">
                        <label className="text-xs font-medium uppercase leading-4 tracking-[0.06em] text-ink-muted">
                            Language
                        </label>
                        <select
                            value={form.data.language}
                            onChange={(e) => form.setData('language', e.target.value)}
                            className="appearance-none rounded-md border border-border bg-surface px-4 py-3 text-sm leading-[18px] text-ink outline-none"
                        >
                            {LANGUAGES.map((lang) => (
                                <option key={lang.value} value={lang.value}>
                                    {lang.label}
                                </option>
                            ))}
                        </select>
                    </fieldset>
                </div>

                <div className="flex items-center justify-end gap-3">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md px-5 py-2.5 text-sm font-medium leading-[18px] text-ink-muted"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="rounded-md bg-ink px-6 py-2.5 text-sm font-medium leading-[18px] text-surface disabled:opacity-50"
                    >
                        Continue
                    </button>
                </div>
            </form>
        </div>
    );
}
