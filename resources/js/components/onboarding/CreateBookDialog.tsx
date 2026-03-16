import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';

const LANGUAGES = [
    { value: 'de', label: 'Deutsch' },
    { value: 'en', label: 'English' },
    { value: 'fr', label: 'Fran\u00E7ais' },
    { value: 'es', label: 'Espa\u00F1ol' },
];

const GENRES = [
    { value: 'thriller', label: 'Thriller' },
    { value: 'mystery', label: 'Mystery' },
    { value: 'romance', label: 'Romance' },
    { value: 'science_fiction', label: 'Science Fiction' },
    { value: 'fantasy', label: 'Fantasy' },
    { value: 'horror', label: 'Horror' },
    { value: 'literary_fiction', label: 'Literary Fiction' },
    { value: 'historical_fiction', label: 'Historical Fiction' },
    { value: 'crime', label: 'Crime' },
    { value: 'adventure', label: 'Adventure' },
    { value: 'drama', label: 'Drama' },
    { value: 'young_adult', label: 'Young Adult' },
    { value: 'non_fiction', label: 'Non-Fiction' },
    { value: 'memoir', label: 'Memoir' },
    { value: 'poetry', label: 'Poetry' },
    { value: 'western', label: 'Western' },
    { value: 'dystopian', label: 'Dystopian' },
];

export default function CreateBookDialog({ onClose }: { onClose: () => void }) {
    const { t } = useTranslation('onboarding');
    const form = useForm({
        title: '',
        author: '',
        language: 'de',
        genre: '',
        secondary_genres: [] as string[],
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
                    <h2 className="font-serif text-[32px] leading-10 tracking-[-0.01em] text-ink">{t('createBook.title')}</h2>
                    <p className="text-sm leading-[22px] text-ink-muted">
                        {t('createBook.description')}
                    </p>
                </div>

                <div className="flex flex-col gap-6">
                    <fieldset className="flex flex-col gap-1.5">
                        <label className="text-xs font-medium uppercase leading-4 tracking-[0.08em] text-ink-muted">
                            {t('createBook.labelTitle')}
                        </label>
                        <input
                            type="text"
                            value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                            placeholder={t('createBook.placeholderTitle')}
                            className="rounded-md border border-border bg-surface px-4 py-3 text-sm leading-[18px] text-ink outline-none placeholder:text-ink-faint"
                            autoFocus
                        />
                        {form.errors.title && (
                            <span className="text-xs text-red-600">{form.errors.title}</span>
                        )}
                    </fieldset>

                    <fieldset className="flex flex-col gap-1.5">
                        <label className="text-xs font-medium uppercase leading-4 tracking-[0.08em] text-ink-muted">
                            {t('createBook.labelAuthor')}
                        </label>
                        <input
                            type="text"
                            value={form.data.author}
                            onChange={(e) => form.setData('author', e.target.value)}
                            placeholder={t('createBook.placeholderAuthor')}
                            className="rounded-md border border-border bg-surface px-4 py-3 text-sm leading-[18px] text-ink outline-none placeholder:text-ink-faint"
                        />
                    </fieldset>

                    <fieldset className="flex flex-col gap-1.5">
                        <label className="text-xs font-medium uppercase leading-4 tracking-[0.08em] text-ink-muted">
                            {t('createBook.labelLanguage')}
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

                    <fieldset className="flex flex-col gap-1.5">
                        <label className="text-xs font-medium uppercase leading-4 tracking-[0.08em] text-ink-muted">
                            {t('createBook.labelGenre')}
                        </label>
                        <select
                            value={form.data.genre}
                            onChange={(e) => {
                                const newGenre = e.target.value;
                                form.setData((prev) => ({
                                    ...prev,
                                    genre: newGenre,
                                    secondary_genres: prev.secondary_genres.filter((g) => g !== newGenre),
                                }));
                            }}
                            className="appearance-none rounded-md border border-border bg-surface px-4 py-3 text-sm leading-[18px] text-ink outline-none"
                        >
                            <option value="">{t('createBook.placeholderGenre')}</option>
                            {GENRES.map((g) => (
                                <option key={g.value} value={g.value}>
                                    {g.label}
                                </option>
                            ))}
                        </select>
                    </fieldset>

                    {form.data.genre && (
                        <fieldset className="flex flex-col gap-1.5">
                            <label className="text-xs font-medium uppercase leading-4 tracking-[0.08em] text-ink-muted">
                                {t('createBook.labelSecondaryGenres')}
                            </label>
                            {form.data.secondary_genres.length > 0 && (
                                <div className="flex flex-wrap gap-1.5">
                                    {form.data.secondary_genres.map((g) => {
                                        const genre = GENRES.find((x) => x.value === g);
                                        return (
                                            <span
                                                key={g}
                                                className="inline-flex items-center gap-1 rounded-md bg-surface-raised px-2.5 py-1 text-xs font-medium text-ink"
                                            >
                                                {genre?.label ?? g}
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        form.setData(
                                                            'secondary_genres',
                                                            form.data.secondary_genres.filter((x) => x !== g),
                                                        )
                                                    }
                                                    className="ml-0.5 text-ink-muted hover:text-ink"
                                                >
                                                    &times;
                                                </button>
                                            </span>
                                        );
                                    })}
                                </div>
                            )}
                            <select
                                value=""
                                onChange={(e) => {
                                    if (e.target.value) {
                                        form.setData('secondary_genres', [
                                            ...form.data.secondary_genres,
                                            e.target.value,
                                        ]);
                                    }
                                }}
                                className="appearance-none rounded-md border border-border bg-surface px-4 py-3 text-sm leading-[18px] text-ink outline-none"
                            >
                                <option value="">{t('createBook.placeholderSecondaryGenres')}</option>
                                {GENRES.filter(
                                    (g) =>
                                        g.value !== form.data.genre &&
                                        !form.data.secondary_genres.includes(g.value),
                                ).map((g) => (
                                    <option key={g.value} value={g.value}>
                                        {g.label}
                                    </option>
                                ))}
                            </select>
                        </fieldset>
                    )}
                </div>

                <div className="flex items-center justify-end gap-3">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md px-5 py-2.5 text-sm font-medium leading-[18px] text-ink-muted"
                    >
                        {t('createBook.cancel')}
                    </button>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="rounded-md bg-ink px-6 py-2.5 text-sm font-medium leading-[18px] text-surface disabled:opacity-50"
                    >
                        {t('createBook.continue')}
                    </button>
                </div>
            </form>
        </div>
    );
}
