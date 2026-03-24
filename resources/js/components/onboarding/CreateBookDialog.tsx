import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import Dialog from '@/components/ui/Dialog';
import FormField from '@/components/ui/FormField';
import Input from '@/components/ui/Input';
import Select from '@/components/ui/Select';

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
        <Dialog onClose={onClose} backdrop="none" className="!p-0">
            <form onSubmit={handleSubmit} className="flex flex-col gap-8 p-10">
                <div className="flex flex-col gap-2">
                    <h2 className="font-serif text-2xl leading-8 font-normal tracking-[-0.01em] text-ink">
                        {t('createBook.title')}
                    </h2>
                    <p className="text-sm leading-[22px] text-ink-muted">
                        {t('createBook.description')}
                    </p>
                </div>

                <div className="flex flex-col gap-6">
                    <FormField
                        label={t('createBook.labelTitle')}
                        error={form.errors.title}
                    >
                        <Input
                            variant="dialog"
                            type="text"
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                            placeholder={t('createBook.placeholderTitle')}
                            autoFocus
                        />
                    </FormField>

                    <FormField label={t('createBook.labelAuthor')}>
                        <Input
                            variant="dialog"
                            type="text"
                            value={form.data.author}
                            onChange={(e) =>
                                form.setData('author', e.target.value)
                            }
                            placeholder={t('createBook.placeholderAuthor')}
                        />
                    </FormField>

                    <FormField label={t('createBook.labelLanguage')}>
                        <Select
                            variant="dialog"
                            value={form.data.language}
                            onChange={(e) =>
                                form.setData('language', e.target.value)
                            }
                        >
                            {LANGUAGES.map((lang) => (
                                <option key={lang.value} value={lang.value}>
                                    {lang.label}
                                </option>
                            ))}
                        </Select>
                    </FormField>

                    <FormField label={t('createBook.labelGenre')}>
                        <Select
                            variant="dialog"
                            value={form.data.genre}
                            onChange={(e) => {
                                const newGenre = e.target.value;
                                form.setData((prev) => ({
                                    ...prev,
                                    genre: newGenre,
                                    secondary_genres:
                                        prev.secondary_genres.filter(
                                            (g) => g !== newGenre,
                                        ),
                                }));
                            }}
                        >
                            <option value="">
                                {t('createBook.placeholderGenre')}
                            </option>
                            {GENRES.map((g) => (
                                <option key={g.value} value={g.value}>
                                    {g.label}
                                </option>
                            ))}
                        </Select>
                    </FormField>

                    {form.data.genre && (
                        <FormField label={t('createBook.labelSecondaryGenres')}>
                            {form.data.secondary_genres.length > 0 && (
                                <div className="flex flex-wrap gap-1.5">
                                    {form.data.secondary_genres.map((g) => {
                                        const genre = GENRES.find(
                                            (x) => x.value === g,
                                        );
                                        return (
                                            <span
                                                key={g}
                                                className="bg-surface-raised inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-medium text-ink"
                                            >
                                                {genre?.label ?? g}
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        form.setData(
                                                            'secondary_genres',
                                                            form.data.secondary_genres.filter(
                                                                (x) => x !== g,
                                                            ),
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
                            <Select
                                variant="dialog"
                                value=""
                                onChange={(e) => {
                                    if (e.target.value) {
                                        form.setData('secondary_genres', [
                                            ...form.data.secondary_genres,
                                            e.target.value,
                                        ]);
                                    }
                                }}
                            >
                                <option value="">
                                    {t('createBook.placeholderSecondaryGenres')}
                                </option>
                                {GENRES.filter(
                                    (g) =>
                                        g.value !== form.data.genre &&
                                        !form.data.secondary_genres.includes(
                                            g.value,
                                        ),
                                ).map((g) => (
                                    <option key={g.value} value={g.value}>
                                        {g.label}
                                    </option>
                                ))}
                            </Select>
                        </FormField>
                    )}
                </div>

                <div className="flex items-center justify-end gap-3">
                    <Button
                        variant="ghost"
                        size="lg"
                        type="button"
                        onClick={onClose}
                    >
                        {t('createBook.cancel')}
                    </Button>
                    <Button
                        variant="primary"
                        size="lg"
                        type="submit"
                        disabled={form.processing}
                    >
                        {t('createBook.continue')}
                    </Button>
                </div>
            </form>
        </Dialog>
    );
}
