import { router } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    update,
    updateEpilogue,
    updatePrologue,
} from '@/actions/App/Http/Controllers/PublishController';
import Button from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import FormField from '@/components/ui/FormField';
import Input from '@/components/ui/Input';
import PageHeader from '@/components/ui/PageHeader';
import SaveStatusIndicator from '@/components/ui/SaveStatusIndicator';
import type { SaveStatus } from '@/components/ui/SaveStatusIndicator';
import SectionLabel from '@/components/ui/SectionLabel';
import Select from '@/components/ui/Select';
import { Spinner } from '@/components/ui/spinner';
import Textarea from '@/components/ui/Textarea';
import { useAiFeatures } from '@/hooks/useAiFeatures';
import { useBlurb } from '@/hooks/useBlurb';
import BookSettingsLayout from '@/layouts/BookSettingsLayout';

type BookData = {
    id: number;
    title: string;
    author: string | null;
    language: string;
    copyright_text: string | null;
    dedication_text: string | null;
    epigraph_text: string | null;
    epigraph_attribution: string | null;
    acknowledgment_text: string | null;
    about_author_text: string | null;
    also_by_text: string | null;
    klappentext: string | null;
    publisher_name: string | null;
    isbn: string | null;
};

interface Props {
    book: BookData;
    chapters: Array<{
        id: number;
        title: string;
        is_epilogue: boolean;
        is_prologue: boolean;
    }>;
}

export default function PublishingSettings({ book, chapters }: Props) {
    const { t } = useTranslation('publish');
    const ai = useAiFeatures();
    const {
        generate: generateKlappentext,
        isGenerating: isGeneratingKlappentext,
    } = useBlurb();

    const [data, setData] = useState({
        copyright_text: book.copyright_text ?? '',
        dedication_text: book.dedication_text ?? '',
        epigraph_text: book.epigraph_text ?? '',
        epigraph_attribution: book.epigraph_attribution ?? '',
        acknowledgment_text: book.acknowledgment_text ?? '',
        about_author_text: book.about_author_text ?? '',
        also_by_text: book.also_by_text ?? '',
        klappentext: book.klappentext ?? '',
        publisher_name: book.publisher_name ?? '',
        isbn: book.isbn ?? '',
    });
    const [saveStatus, setSaveStatus] = useState<SaveStatus>('idle');
    const dataRef = useRef(data);
    useEffect(() => {
        dataRef.current = data;
    }, [data]);

    const save = useCallback(() => {
        setSaveStatus('saving');
        router.put(update.url(book.id), dataRef.current, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setSaveStatus('saved'),
            onError: () => setSaveStatus('error'),
        });
    }, [book.id]);

    const handleFieldChange = useCallback(
        (field: keyof typeof data, value: string) => {
            setData((prev) => ({ ...prev, [field]: value }));
        },
        [],
    );

    const handleGenerateKlappentext = useCallback(async () => {
        await generateKlappentext(book.id, (full) =>
            handleFieldChange('klappentext', full),
        );
        save();
    }, [book.id, generateKlappentext, handleFieldChange, save]);

    useEffect(() => {
        const hash = window.location.hash.slice(1);
        if (hash) {
            const el = document.getElementById(hash);
            el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }, []);

    const currentEpilogueId = chapters.find((ch) => ch.is_epilogue)?.id ?? null;
    const currentPrologueId = chapters.find((ch) => ch.is_prologue)?.id ?? null;

    const handleEpilogueChange = useCallback(
        (e: React.ChangeEvent<HTMLSelectElement>) => {
            const value = e.target.value;
            setSaveStatus('saving');
            router.put(
                updateEpilogue.url(book.id),
                { chapter_id: value === 'none' ? null : Number(value) },
                {
                    preserveScroll: true,
                    onSuccess: () => setSaveStatus('saved'),
                    onError: () => setSaveStatus('error'),
                },
            );
        },
        [book.id],
    );

    const handlePrologueChange = useCallback(
        (e: React.ChangeEvent<HTMLSelectElement>) => {
            const value = e.target.value;
            setSaveStatus('saving');
            router.put(
                updatePrologue.url(book.id),
                { chapter_id: value === 'none' ? null : Number(value) },
                {
                    preserveScroll: true,
                    onSuccess: () => setSaveStatus('saved'),
                    onError: () => setSaveStatus('error'),
                },
            );
        },
        [book.id],
    );

    return (
        <BookSettingsLayout
            activeSection="publishing"
            book={book}
            title={t('pageTitle', { title: book.title })}
        >
            <PageHeader
                title={t('title')}
                subtitle={t('subtitle')}
                actions={<SaveStatusIndicator status={saveStatus} />}
            />

            <div className="mt-9 flex flex-col gap-9 pb-24">
                {/* Klappentext */}
                <div id="klappentext">
                    <SectionLabel variant="section">
                        {t('klappentext.title')}
                    </SectionLabel>
                    <Card className="mt-3 p-6">
                        <div className="flex items-start justify-between gap-4">
                            <p className="text-[13px] text-ink-muted">
                                {t('klappentext.description')}
                            </p>
                            {ai.usable && (
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    className="shrink-0"
                                    onClick={handleGenerateKlappentext}
                                    disabled={isGeneratingKlappentext}
                                >
                                    {isGeneratingKlappentext ? (
                                        <Spinner className="size-[14px]" />
                                    ) : (
                                        <Sparkles size={14} />
                                    )}
                                    {isGeneratingKlappentext
                                        ? t('klappentext.generating')
                                        : t('klappentext.generate')}
                                </Button>
                            )}
                        </div>

                        <div className="mt-4">
                            <Textarea
                                value={data.klappentext}
                                onChange={(e) =>
                                    handleFieldChange(
                                        'klappentext',
                                        e.target.value,
                                    )
                                }
                                onBlur={save}
                                placeholder={t('klappentext.placeholder')}
                                rows={6}
                                disabled={isGeneratingKlappentext}
                            />
                            <p className="mt-1.5 text-xs text-ink-faint">
                                {ai.usable
                                    ? t('klappentext.coverHint')
                                    : t('klappentext.aiHint')}
                            </p>
                        </div>
                    </Card>
                </div>

                {/* Metadata */}
                <div>
                    <SectionLabel variant="section">
                        {t('metadata.title')}
                    </SectionLabel>
                    <div className="mt-3 flex flex-col gap-3">
                        <Card className="px-6 py-4">
                            <FormField label={t('metadata.publisherName')}>
                                <Input
                                    value={data.publisher_name}
                                    onChange={(e) =>
                                        handleFieldChange(
                                            'publisher_name',
                                            e.target.value,
                                        )
                                    }
                                    onBlur={save}
                                    placeholder={t(
                                        'metadata.publisherNamePlaceholder',
                                    )}
                                />
                            </FormField>
                        </Card>
                        <Card className="px-6 py-4">
                            <FormField label={t('metadata.isbn')}>
                                <Input
                                    value={data.isbn}
                                    onChange={(e) =>
                                        handleFieldChange(
                                            'isbn',
                                            e.target.value,
                                        )
                                    }
                                    onBlur={save}
                                    placeholder={t('metadata.isbnPlaceholder')}
                                />
                                <p className="mt-1.5 text-xs text-ink-faint">
                                    {t('metadata.isbnHint')}
                                </p>
                            </FormField>
                        </Card>
                    </div>
                </div>

                {/* Front Matter */}
                <div id="front-matter">
                    <SectionLabel variant="section">
                        {t('frontMatter.title')}
                    </SectionLabel>
                    <Card className="mt-3 p-6">
                        <p className="text-[13px] text-ink-muted">
                            {t('frontMatter.description')}
                        </p>

                        <div className="mt-5 flex flex-col gap-5">
                            <FormField
                                id="copyright"
                                label={t('frontMatter.copyright')}
                            >
                                <Textarea
                                    value={data.copyright_text}
                                    onChange={(e) =>
                                        handleFieldChange(
                                            'copyright_text',
                                            e.target.value,
                                        )
                                    }
                                    onBlur={save}
                                    placeholder={t(
                                        'frontMatter.copyrightPlaceholder',
                                    )}
                                    rows={3}
                                />
                            </FormField>

                            <FormField
                                id="dedication"
                                label={t('frontMatter.dedication')}
                            >
                                <Textarea
                                    value={data.dedication_text}
                                    onChange={(e) =>
                                        handleFieldChange(
                                            'dedication_text',
                                            e.target.value,
                                        )
                                    }
                                    onBlur={save}
                                    placeholder={t(
                                        'frontMatter.dedicationPlaceholder',
                                    )}
                                    rows={2}
                                />
                            </FormField>

                            <div className="border-t border-border-subtle" />

                            <FormField
                                id="epigraph"
                                label={t('frontMatter.epigraph')}
                            >
                                <Textarea
                                    value={data.epigraph_text}
                                    onChange={(e) =>
                                        handleFieldChange(
                                            'epigraph_text',
                                            e.target.value,
                                        )
                                    }
                                    onBlur={save}
                                    placeholder={t(
                                        'frontMatter.epigraphPlaceholder',
                                    )}
                                    rows={3}
                                />
                            </FormField>

                            <FormField
                                label={t('frontMatter.epigraphAttribution')}
                            >
                                <Input
                                    value={data.epigraph_attribution}
                                    onChange={(e) =>
                                        handleFieldChange(
                                            'epigraph_attribution',
                                            e.target.value,
                                        )
                                    }
                                    onBlur={save}
                                    placeholder={t(
                                        'frontMatter.epigraphAttributionPlaceholder',
                                    )}
                                />
                            </FormField>

                            <div className="border-t border-border-subtle" />

                            <FormField
                                id="prologue"
                                label={t('frontMatter.prologue')}
                            >
                                <Select
                                    value={
                                        currentPrologueId
                                            ? String(currentPrologueId)
                                            : 'none'
                                    }
                                    onChange={handlePrologueChange}
                                >
                                    <option value="none">
                                        {t('frontMatter.noPrologue')}
                                    </option>
                                    {chapters.map((ch) => (
                                        <option
                                            key={ch.id}
                                            value={String(ch.id)}
                                        >
                                            {ch.title}
                                        </option>
                                    ))}
                                </Select>
                                <p className="mt-1.5 text-xs text-ink-faint">
                                    {t('frontMatter.prologueHint')}
                                </p>
                            </FormField>
                        </div>
                    </Card>
                </div>

                {/* Back Matter */}
                <div id="back-matter">
                    <SectionLabel variant="section">
                        {t('backMatter.title')}
                    </SectionLabel>
                    <Card className="mt-3 p-6">
                        <p className="text-[13px] text-ink-muted">
                            {t('backMatter.description')}
                        </p>

                        <div className="mt-5 flex flex-col gap-5">
                            <FormField
                                id="acknowledgments"
                                label={t('backMatter.acknowledgments')}
                            >
                                <Textarea
                                    value={data.acknowledgment_text}
                                    onChange={(e) =>
                                        handleFieldChange(
                                            'acknowledgment_text',
                                            e.target.value,
                                        )
                                    }
                                    onBlur={save}
                                    placeholder={t(
                                        'backMatter.acknowledgmentsPlaceholder',
                                    )}
                                    rows={4}
                                />
                            </FormField>

                            <FormField
                                id="about-author"
                                label={t('backMatter.aboutAuthor')}
                            >
                                <Textarea
                                    value={data.about_author_text}
                                    onChange={(e) =>
                                        handleFieldChange(
                                            'about_author_text',
                                            e.target.value,
                                        )
                                    }
                                    onBlur={save}
                                    placeholder={t(
                                        'backMatter.aboutAuthorPlaceholder',
                                    )}
                                    rows={4}
                                />
                            </FormField>

                            <FormField
                                id="also-by"
                                label={t('backMatter.alsoBy')}
                            >
                                <Textarea
                                    value={data.also_by_text}
                                    onChange={(e) =>
                                        handleFieldChange(
                                            'also_by_text',
                                            e.target.value,
                                        )
                                    }
                                    onBlur={save}
                                    placeholder={t(
                                        'backMatter.alsoByPlaceholder',
                                    )}
                                    rows={3}
                                />
                                <p className="mt-1.5 text-xs text-ink-faint">
                                    {t('backMatter.alsoByHint')}
                                </p>
                            </FormField>

                            <div className="border-t border-border-subtle" />

                            <FormField
                                id="epilogue"
                                label={t('backMatter.epilogue')}
                            >
                                <Select
                                    value={
                                        currentEpilogueId
                                            ? String(currentEpilogueId)
                                            : 'none'
                                    }
                                    onChange={handleEpilogueChange}
                                >
                                    <option value="none">
                                        {t('backMatter.noEpilogue')}
                                    </option>
                                    {chapters.map((ch) => (
                                        <option
                                            key={ch.id}
                                            value={String(ch.id)}
                                        >
                                            {ch.title}
                                        </option>
                                    ))}
                                </Select>
                                <p className="mt-1.5 text-xs text-ink-faint">
                                    {t('backMatter.epilogueHint')}
                                </p>
                            </FormField>
                        </div>
                    </Card>
                </div>
            </div>
        </BookSettingsLayout>
    );
}
