import { Head, router, useForm } from '@inertiajs/react';
import { ImagePlus, Trash2, Upload } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    deleteCover,
    update,
    updateEpilogue,
    uploadCover,
} from '@/actions/App/Http/Controllers/PublishController';
import Sidebar from '@/components/editor/Sidebar';
import Button from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import FormField from '@/components/ui/FormField';
import Input from '@/components/ui/Input';
import SectionLabel from '@/components/ui/SectionLabel';
import Select from '@/components/ui/Select';
import Textarea from '@/components/ui/Textarea';
import { useDebouncedCallback } from '@/hooks/useDebouncedCallback';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';

interface PublishPageProps {
    book: {
        id: number;
        title: string;
        author: string;
        language: string;
        copyright_text: string | null;
        dedication_text: string | null;
        epigraph_text: string | null;
        epigraph_attribution: string | null;
        acknowledgment_text: string | null;
        about_author_text: string | null;
        also_by_text: string | null;
        publisher_name: string | null;
        isbn: string | null;
        cover_image_path: string | null;
        cover_image_url: string | null;
    };
    chapters: Array<{
        id: number;
        title: string;
        is_epilogue: boolean;
    }>;
}

export default function PublishPage({ book, chapters }: PublishPageProps) {
    const { t } = useTranslation('publish');
    const sidebarStorylines = useSidebarStorylines();
    const fileInputRef = useRef<HTMLInputElement>(null);

    const form = useForm({
        copyright_text: book.copyright_text ?? '',
        dedication_text: book.dedication_text ?? '',
        epigraph_text: book.epigraph_text ?? '',
        epigraph_attribution: book.epigraph_attribution ?? '',
        acknowledgment_text: book.acknowledgment_text ?? '',
        about_author_text: book.about_author_text ?? '',
        also_by_text: book.also_by_text ?? '',
        publisher_name: book.publisher_name ?? '',
        isbn: book.isbn ?? '',
    });

    const [showSaved, setShowSaved] = useState(false);
    const savedTimeoutRef = useRef<ReturnType<typeof setTimeout>>();
    const formDataRef = useRef(form.data);
    formDataRef.current = form.data;

    const showSavedBriefly = useCallback(() => {
        setShowSaved(true);
        if (savedTimeoutRef.current) clearTimeout(savedTimeoutRef.current);
        savedTimeoutRef.current = setTimeout(() => setShowSaved(false), 2000);
    }, []);

    const debouncedSave = useDebouncedCallback(() => {
        router.put(update.url(book.id), formDataRef.current, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: showSavedBriefly,
        });
    }, 1000);

    const handleFieldChange = useCallback(
        (field: keyof typeof form.data, value: string) => {
            form.setData(field, value);
            debouncedSave();
        },
        [form.setData, debouncedSave],
    );

    const handleCoverUpload = useCallback(
        (e: React.ChangeEvent<HTMLInputElement>) => {
            const file = e.target.files?.[0];
            if (!file) return;
            router.post(
                uploadCover.url(book.id),
                { cover_image: file } as Record<string, File>,
                { forceFormData: true, preserveScroll: true },
            );
            if (fileInputRef.current) fileInputRef.current.value = '';
        },
        [book.id],
    );

    const handleCoverRemove = useCallback(() => {
        router.delete(deleteCover.url(book.id), { preserveScroll: true });
    }, [book.id]);

    useEffect(() => {
        const hash = window.location.hash.slice(1);
        if (hash) {
            const el = document.getElementById(hash);
            el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }, []);

    const currentEpilogueId = chapters.find((ch) => ch.is_epilogue)?.id ?? null;

    const handleEpilogueChange = useCallback(
        (e: React.ChangeEvent<HTMLSelectElement>) => {
            const value = e.target.value;
            router.put(
                updateEpilogue.url(book.id),
                { chapter_id: value === 'none' ? null : Number(value) },
                { preserveScroll: true },
            );
        },
        [book.id],
    );

    return (
        <>
            <Head title={t('pageTitle', { title: book.title })} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar
                    book={book}
                    storylines={sidebarStorylines}
                    scenesVisible={false}
                    onScenesVisibleChange={() => {}}
                />

                <main className="flex flex-1 flex-col items-center overflow-y-auto px-12 py-10">
                    <div className="w-full max-w-[640px]">
                        <div className="flex items-center justify-between">
                            <div>
                                <h1 className="text-xl font-semibold tracking-[-0.01em] text-ink">
                                    {t('title')}
                                </h1>
                                <p className="mt-1 text-[14px] text-ink-muted">
                                    {t('subtitle')}
                                </p>
                            </div>
                            {showSaved && (
                                <span className="animate-in fade-in rounded-full bg-status-final/10 px-3 py-1 text-xs font-medium text-status-final">
                                    {t('saved')}
                                </span>
                            )}
                        </div>

                        <div className="mt-9 flex flex-col gap-9">
                            {/* Cover Image */}
                            <div>
                                <SectionLabel variant="section">
                                    {t('cover.title')}
                                </SectionLabel>
                                <Card className="mt-3 p-6">
                                    <span className="text-sm font-medium text-ink">
                                        {t('cover.title')}
                                    </span>
                                    <p className="mt-1 text-[13px] text-ink-muted">
                                        {t('cover.description')}
                                    </p>

                                    <div className="mt-4">
                                        {book.cover_image_url ? (
                                            <div className="flex items-start gap-6">
                                                <img
                                                    src={book.cover_image_url}
                                                    alt="Cover"
                                                    className="h-48 w-auto rounded-lg border border-border-light object-cover shadow-sm"
                                                />
                                                <div className="flex flex-col gap-2 pt-1">
                                                    <Button
                                                        variant="secondary"
                                                        size="sm"
                                                        onClick={() =>
                                                            fileInputRef.current?.click()
                                                        }
                                                    >
                                                        <Upload size={14} />
                                                        {t('cover.replace')}
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={
                                                            handleCoverRemove
                                                        }
                                                        className="text-delete hover:text-delete/80"
                                                    >
                                                        <Trash2 size={14} />
                                                        {t('cover.remove')}
                                                    </Button>
                                                </div>
                                            </div>
                                        ) : (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    fileInputRef.current?.click()
                                                }
                                                className="flex h-48 w-36 flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-border-dashed transition-colors hover:border-ink-faint hover:bg-neutral-bg"
                                            >
                                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-neutral-bg">
                                                    <ImagePlus
                                                        size={18}
                                                        className="text-ink-muted"
                                                    />
                                                </div>
                                                <span className="text-xs font-medium text-ink-muted">
                                                    {t('cover.upload')}
                                                </span>
                                            </button>
                                        )}

                                        <input
                                            ref={fileInputRef}
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            onChange={handleCoverUpload}
                                            className="hidden"
                                        />
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
                                        <FormField
                                            label={t('metadata.publisherName')}
                                        >
                                            <Input
                                                value={form.data.publisher_name}
                                                onChange={(e) =>
                                                    handleFieldChange(
                                                        'publisher_name',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder={t(
                                                    'metadata.publisherNamePlaceholder',
                                                )}
                                            />
                                        </FormField>
                                    </Card>
                                    <Card className="px-6 py-4">
                                        <FormField label={t('metadata.isbn')}>
                                            <Input
                                                value={form.data.isbn}
                                                onChange={(e) =>
                                                    handleFieldChange(
                                                        'isbn',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder={t(
                                                    'metadata.isbnPlaceholder',
                                                )}
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
                                    <span className="text-sm font-medium text-ink">
                                        {t('frontMatter.title')}
                                    </span>
                                    <p className="mt-1 text-[13px] text-ink-muted">
                                        {t('frontMatter.description')}
                                    </p>

                                    <div className="mt-4 flex flex-col gap-5">
                                        <FormField
                                            id="copyright"
                                            label={t('frontMatter.copyright')}
                                        >
                                            <Textarea
                                                value={form.data.copyright_text}
                                                onChange={(e) =>
                                                    handleFieldChange(
                                                        'copyright_text',
                                                        e.target.value,
                                                    )
                                                }
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
                                                value={
                                                    form.data.dedication_text
                                                }
                                                onChange={(e) =>
                                                    handleFieldChange(
                                                        'dedication_text',
                                                        e.target.value,
                                                    )
                                                }
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
                                                value={form.data.epigraph_text}
                                                onChange={(e) =>
                                                    handleFieldChange(
                                                        'epigraph_text',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder={t(
                                                    'frontMatter.epigraphPlaceholder',
                                                )}
                                                rows={3}
                                            />
                                        </FormField>

                                        <FormField
                                            label={t(
                                                'frontMatter.epigraphAttribution',
                                            )}
                                        >
                                            <Input
                                                value={
                                                    form.data
                                                        .epigraph_attribution
                                                }
                                                onChange={(e) =>
                                                    handleFieldChange(
                                                        'epigraph_attribution',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder={t(
                                                    'frontMatter.epigraphAttributionPlaceholder',
                                                )}
                                            />
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
                                    <span className="text-sm font-medium text-ink">
                                        {t('backMatter.title')}
                                    </span>
                                    <p className="mt-1 text-[13px] text-ink-muted">
                                        {t('backMatter.description')}
                                    </p>

                                    <div className="mt-4 flex flex-col gap-5">
                                        <FormField
                                            id="acknowledgments"
                                            label={t(
                                                'backMatter.acknowledgments',
                                            )}
                                        >
                                            <Textarea
                                                value={
                                                    form.data
                                                        .acknowledgment_text
                                                }
                                                onChange={(e) =>
                                                    handleFieldChange(
                                                        'acknowledgment_text',
                                                        e.target.value,
                                                    )
                                                }
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
                                                value={
                                                    form.data.about_author_text
                                                }
                                                onChange={(e) =>
                                                    handleFieldChange(
                                                        'about_author_text',
                                                        e.target.value,
                                                    )
                                                }
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
                                                value={form.data.also_by_text}
                                                onChange={(e) =>
                                                    handleFieldChange(
                                                        'also_by_text',
                                                        e.target.value,
                                                    )
                                                }
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
                                                        ? String(
                                                              currentEpilogueId,
                                                          )
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
                    </div>
                </main>
            </div>
        </>
    );
}
