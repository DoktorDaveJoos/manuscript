import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { updateGeneral } from '@/actions/App/Http/Controllers/BookSettingsController';
import GenreSelect from '@/components/GenreSelect';
import { Card } from '@/components/ui/Card';
import FormField from '@/components/ui/FormField';
import Input from '@/components/ui/Input';
import PageHeader from '@/components/ui/PageHeader';
import SaveStatusIndicator from '@/components/ui/SaveStatusIndicator';
import type { SaveStatus } from '@/components/ui/SaveStatusIndicator';
import Select from '@/components/ui/Select';
import BookSettingsLayout from '@/layouts/BookSettingsLayout';
import { genreLabel } from '@/lib/genres';
import { BOOK_LANGUAGES } from '@/lib/languages';

type BookData = {
    id: number;
    title: string;
    subtitle: string | null;
    author: string | null;
    language: string;
    genre: string | null;
    secondary_genres: string[] | null;
};

interface Props {
    book: BookData;
}

export default function GeneralSettings({ book }: Props) {
    const { t } = useTranslation('settings');

    const [data, setData] = useState({
        title: book.title ?? '',
        subtitle: book.subtitle ?? '',
        author: book.author ?? '',
        language: book.language ?? 'en',
        genre: book.genre ?? '',
        secondary_genres: book.secondary_genres ?? [],
    });
    const [saveStatus, setSaveStatus] = useState<SaveStatus>('idle');

    const save = (next: typeof data) => {
        setSaveStatus('saving');
        router.put(updateGeneral.url(book.id), next, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setSaveStatus('saved'),
            onError: () => setSaveStatus('error'),
        });
    };

    const setAndSave = (patch: Partial<typeof data>) => {
        const next = { ...data, ...patch };
        setData(next);
        save(next);
    };

    return (
        <BookSettingsLayout
            activeSection="general"
            book={book}
            title={t('general.pageTitle', { bookTitle: book.title })}
        >
            <div className="flex flex-col gap-4">
                <PageHeader
                    title={t('general.title')}
                    subtitle={t('general.description')}
                    actions={<SaveStatusIndicator status={saveStatus} />}
                />

                <Card className="p-6">
                    <div className="flex flex-col gap-5">
                        <FormField label={t('general.labelTitle')}>
                            <Input
                                value={data.title}
                                onChange={(e) =>
                                    setData({ ...data, title: e.target.value })
                                }
                                onBlur={() => save(data)}
                            />
                        </FormField>

                        <FormField label={t('general.labelSubtitle')}>
                            <Input
                                value={data.subtitle}
                                onChange={(e) =>
                                    setData({
                                        ...data,
                                        subtitle: e.target.value,
                                    })
                                }
                                onBlur={() => save(data)}
                                placeholder={t('general.placeholderSubtitle')}
                            />
                        </FormField>

                        <FormField label={t('general.labelAuthor')}>
                            <Input
                                value={data.author}
                                onChange={(e) =>
                                    setData({ ...data, author: e.target.value })
                                }
                                onBlur={() => save(data)}
                            />
                        </FormField>

                        <FormField label={t('general.labelLanguage')}>
                            <Select
                                value={data.language}
                                onChange={(e) =>
                                    setAndSave({ language: e.target.value })
                                }
                            >
                                {BOOK_LANGUAGES.map((lang) => (
                                    <option key={lang.value} value={lang.value}>
                                        {t(lang.labelKey, { ns: 'common' })}
                                    </option>
                                ))}
                            </Select>
                        </FormField>

                        <div className="border-t border-border-subtle" />

                        <FormField label={t('general.labelGenre')}>
                            <GenreSelect
                                value={data.genre}
                                placeholder={t('general.placeholderGenre')}
                                onChange={(newGenre) =>
                                    setAndSave({
                                        genre: newGenre,
                                        secondary_genres:
                                            data.secondary_genres.filter(
                                                (g) => g !== newGenre,
                                            ),
                                    })
                                }
                            />
                        </FormField>

                        {data.genre && (
                            <FormField
                                label={t('general.labelSecondaryGenres')}
                            >
                                {data.secondary_genres.length > 0 && (
                                    <div className="flex flex-wrap gap-1.5">
                                        {data.secondary_genres.map((g) => {
                                            return (
                                                <span
                                                    key={g}
                                                    className="bg-surface-raised inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-medium text-ink"
                                                >
                                                    {genreLabel(g)}
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setAndSave({
                                                                secondary_genres:
                                                                    data.secondary_genres.filter(
                                                                        (x) =>
                                                                            x !==
                                                                            g,
                                                                    ),
                                                            })
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
                                    value=""
                                    placeholder={t(
                                        'general.placeholderSecondaryGenres',
                                    )}
                                    exclude={[
                                        data.genre,
                                        ...data.secondary_genres,
                                    ]}
                                    onChange={(value) => {
                                        if (value) {
                                            setAndSave({
                                                secondary_genres: [
                                                    ...data.secondary_genres,
                                                    value,
                                                ],
                                            });
                                        }
                                    }}
                                />
                            </FormField>
                        )}
                    </div>
                </Card>
            </div>
        </BookSettingsLayout>
    );
}
