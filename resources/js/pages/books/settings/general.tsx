import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { updateGeneral } from '@/actions/App/Http/Controllers/BookSettingsController';
import { Card } from '@/components/ui/Card';
import FormField from '@/components/ui/FormField';
import Input from '@/components/ui/Input';
import PageHeader from '@/components/ui/PageHeader';
import SaveStatusIndicator from '@/components/ui/SaveStatusIndicator';
import type { SaveStatus } from '@/components/ui/SaveStatusIndicator';
import Select from '@/components/ui/Select';
import BookSettingsLayout from '@/layouts/BookSettingsLayout';
import { BOOK_LANGUAGES } from '@/lib/languages';

type GenreOption = { value: string; label: string };

type BookData = {
    id: number;
    title: string;
    author: string | null;
    language: string;
    genre: string | null;
    secondary_genres: string[] | null;
};

interface Props {
    book: BookData;
    genres: GenreOption[];
}

export default function GeneralSettings({ book, genres }: Props) {
    const { t } = useTranslation('settings');

    const [data, setData] = useState({
        title: book.title ?? '',
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
                                        {lang.label}
                                    </option>
                                ))}
                            </Select>
                        </FormField>

                        <div className="border-t border-border-subtle" />

                        <FormField label={t('general.labelGenre')}>
                            <Select
                                value={data.genre}
                                onChange={(e) => {
                                    const newGenre = e.target.value;
                                    setAndSave({
                                        genre: newGenre,
                                        secondary_genres:
                                            data.secondary_genres.filter(
                                                (g) => g !== newGenre,
                                            ),
                                    });
                                }}
                            >
                                <option value="">
                                    {t('general.placeholderGenre')}
                                </option>
                                {genres.map((g) => (
                                    <option key={g.value} value={g.value}>
                                        {g.label}
                                    </option>
                                ))}
                            </Select>
                        </FormField>

                        {data.genre && (
                            <FormField
                                label={t('general.labelSecondaryGenres')}
                            >
                                {data.secondary_genres.length > 0 && (
                                    <div className="flex flex-wrap gap-1.5">
                                        {data.secondary_genres.map((g) => {
                                            const genre = genres.find(
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
                                <Select
                                    value=""
                                    onChange={(e) => {
                                        if (e.target.value) {
                                            setAndSave({
                                                secondary_genres: [
                                                    ...data.secondary_genres,
                                                    e.target.value,
                                                ],
                                            });
                                        }
                                    }}
                                >
                                    <option value="">
                                        {t(
                                            'general.placeholderSecondaryGenres',
                                        )}
                                    </option>
                                    {genres
                                        .filter(
                                            (g) =>
                                                g.value !== data.genre &&
                                                !data.secondary_genres.includes(
                                                    g.value,
                                                ),
                                        )
                                        .map((g) => (
                                            <option
                                                key={g.value}
                                                value={g.value}
                                            >
                                                {g.label}
                                            </option>
                                        ))}
                                </Select>
                            </FormField>
                        )}
                    </div>
                </Card>
            </div>
        </BookSettingsLayout>
    );
}
