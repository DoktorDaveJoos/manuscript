import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import GenreSelect from '@/components/GenreSelect';
import Button from '@/components/ui/Button';
import Dialog from '@/components/ui/Dialog';
import FormField from '@/components/ui/FormField';
import Input from '@/components/ui/Input';
import Select from '@/components/ui/Select';
import { track } from '@/lib/analytics';
import { genreLabel } from '@/lib/genres';
import { BOOK_LANGUAGES } from '@/lib/languages';

export default function CreateBookDialog({ onClose }: { onClose: () => void }) {
    const { t } = useTranslation('onboarding');
    const form = useForm({
        title: '',
        subtitle: '',
        author: '',
        language: 'de',
        genre: '',
        secondary_genres: [] as string[],
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        form.post('/books', {
            onSuccess: () => {
                track('book_created');
                onClose();
            },
        });
    }

    return (
        <Dialog onClose={onClose} backdrop="none" className="!p-0">
            <form onSubmit={handleSubmit} className="flex flex-col gap-8 p-10">
                <div className="flex flex-col gap-2">
                    <h2 className="font-serif text-2xl leading-8 font-semibold tracking-[-0.01em] text-ink">
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

                    <FormField label={t('createBook.labelSubtitle')}>
                        <Input
                            variant="dialog"
                            type="text"
                            value={form.data.subtitle}
                            onChange={(e) =>
                                form.setData('subtitle', e.target.value)
                            }
                            placeholder={t('createBook.placeholderSubtitle')}
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
                            {BOOK_LANGUAGES.map((lang) => (
                                <option key={lang.value} value={lang.value}>
                                    {lang.label}
                                </option>
                            ))}
                        </Select>
                    </FormField>

                    <FormField label={t('createBook.labelGenre')}>
                        <GenreSelect
                            variant="dialog"
                            value={form.data.genre}
                            placeholder={t('createBook.placeholderGenre')}
                            onChange={(newGenre) =>
                                form.setData((prev) => ({
                                    ...prev,
                                    genre: newGenre,
                                    secondary_genres:
                                        prev.secondary_genres.filter(
                                            (g) => g !== newGenre,
                                        ),
                                }))
                            }
                        />
                    </FormField>

                    {form.data.genre && (
                        <FormField label={t('createBook.labelSecondaryGenres')}>
                            {form.data.secondary_genres.length > 0 && (
                                <div className="flex flex-wrap gap-1.5">
                                    {form.data.secondary_genres.map((g) => {
                                        return (
                                            <span
                                                key={g}
                                                className="bg-surface-raised inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-medium text-ink"
                                            >
                                                {genreLabel(g)}
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
                            <GenreSelect
                                variant="dialog"
                                value=""
                                placeholder={t(
                                    'createBook.placeholderSecondaryGenres',
                                )}
                                exclude={[
                                    form.data.genre,
                                    ...form.data.secondary_genres,
                                ]}
                                onChange={(value) => {
                                    if (value) {
                                        form.setData('secondary_genres', [
                                            ...form.data.secondary_genres,
                                            value,
                                        ]);
                                    }
                                }}
                            />
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
