import { Head, router, useForm } from '@inertiajs/react';
import { Download, ImagePlus, Sparkles, Trash2, Upload } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    deleteCover,
    downloadCover,
    update,
    updateEpilogue,
    updatePrologue,
    uploadCover,
} from '@/actions/App/Http/Controllers/PublishController';
import Sidebar from '@/components/editor/Sidebar';
import type { TrimSizeOption } from '@/components/export/types';
import CoverCreatorDialog from '@/components/publish/CoverCreatorDialog';
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
import { useDebouncedCallback } from '@/hooks/useDebouncedCallback';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import type { Book } from '@/types/models';

interface PublishPageProps {
    book: Book;
    chapters: Array<{
        id: number;
        title: string;
        is_epilogue: boolean;
        is_prologue: boolean;
    }>;
    trimSizes: TrimSizeOption[];
}

export default function PublishPage({
    book,
    chapters,
    trimSizes,
}: PublishPageProps) {
    const { t } = useTranslation('publish');
    const sidebarStorylines = useSidebarStorylines();
    const ai = useAiFeatures();
    const {
        generate: generateKlappentext,
        isGenerating: isGeneratingKlappentext,
    } = useBlurb();
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [coverCreatorOpen, setCoverCreatorOpen] = useState(false);

    const form = useForm({
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
    const [isConvertingPdf, setIsConvertingPdf] = useState(false);
    const [coverError, setCoverError] = useState<string | null>(null);
    const formDataRef = useRef(form.data);
    useEffect(() => {
        formDataRef.current = form.data;
    }, [form.data]);

    const debouncedSave = useDebouncedCallback(() => {
        router.put(update.url(book.id), formDataRef.current, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setSaveStatus('saved'),
            onError: () => setSaveStatus('error'),
        });
    }, 1000);

    const handleFieldChange = (
        field: keyof typeof form.data,
        value: string,
    ) => {
        form.setData(field, value);
        setSaveStatus('saving');
        debouncedSave();
    };

    const handleCoverUpload = useCallback(
        async (e: React.ChangeEvent<HTMLInputElement>) => {
            const file = e.target.files?.[0];
            if (fileInputRef.current) fileInputRef.current.value = '';
            if (!file) return;

            setCoverError(null);

            let payload = file;
            if (file.type === 'application/pdf') {
                setIsConvertingPdf(true);
                try {
                    const { renderPdfFirstPageToPng } =
                        await import('@/lib/pdfjs');
                    payload = await renderPdfFirstPageToPng(file);
                } catch (err) {
                    console.error('PDF cover conversion failed', err);
                    setCoverError(t('cover.conversionError'));
                    setIsConvertingPdf(false);
                    return;
                }
                setIsConvertingPdf(false);
            }

            setSaveStatus('saving');
            router.post(
                uploadCover.url(book.id),
                { cover_image: payload } as Record<string, File>,
                {
                    forceFormData: true,
                    preserveScroll: true,
                    onSuccess: () => setSaveStatus('saved'),
                    onError: () => setSaveStatus('error'),
                },
            );
        },
        [book.id, t],
    );

    const handleCoverRemove = useCallback(() => {
        setSaveStatus('saving');
        router.delete(deleteCover.url(book.id), {
            preserveScroll: true,
            onSuccess: () => setSaveStatus('saved'),
            onError: () => setSaveStatus('error'),
        });
    }, [book.id]);

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
        <>
            <Head title={t('pageTitle', { title: book.title })} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar
                    book={book}
                    storylines={sidebarStorylines}
                    scenesVisible={false}
                    onScenesVisibleChange={() => {}}
                />

                <main className="flex-1 overflow-y-auto">
                    <div className="mx-auto w-full max-w-[760px] px-12 pt-12 pb-[80vh]">
                        <PageHeader
                            title={t('title')}
                            subtitle={t('subtitle')}
                            actions={
                                <SaveStatusIndicator status={saveStatus} />
                            }
                        />

                        <div className="mt-9 flex flex-col gap-9">
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
                                                onClick={() =>
                                                    generateKlappentext(
                                                        book.id,
                                                        (full) =>
                                                            handleFieldChange(
                                                                'klappentext',
                                                                full,
                                                            ),
                                                    )
                                                }
                                                disabled={
                                                    isGeneratingKlappentext
                                                }
                                            >
                                                {isGeneratingKlappentext ? (
                                                    <Spinner className="size-[14px]" />
                                                ) : (
                                                    <Sparkles size={14} />
                                                )}
                                                {isGeneratingKlappentext
                                                    ? t(
                                                          'klappentext.generating',
                                                      )
                                                    : t('klappentext.generate')}
                                            </Button>
                                        )}
                                    </div>

                                    <div className="mt-4">
                                        <Textarea
                                            value={form.data.klappentext}
                                            onChange={(e) =>
                                                handleFieldChange(
                                                    'klappentext',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder={t(
                                                'klappentext.placeholder',
                                            )}
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

                            {/* Cover Image */}
                            <div>
                                <SectionLabel variant="section">
                                    {t('cover.title')}
                                </SectionLabel>
                                <Card className="mt-3 p-6">
                                    <p className="text-[13px] text-ink-muted">
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
                                                        disabled={
                                                            isConvertingPdf
                                                        }
                                                    >
                                                        {isConvertingPdf ? (
                                                            <Spinner className="size-[14px]" />
                                                        ) : (
                                                            <Upload size={14} />
                                                        )}
                                                        {isConvertingPdf
                                                            ? t(
                                                                  'cover.converting',
                                                              )
                                                            : t(
                                                                  'cover.replace',
                                                              )}
                                                    </Button>
                                                    <Button
                                                        variant="secondary"
                                                        size="sm"
                                                        onClick={() =>
                                                            setCoverCreatorOpen(
                                                                true,
                                                            )
                                                        }
                                                        disabled={
                                                            isConvertingPdf
                                                        }
                                                    >
                                                        <Sparkles size={14} />
                                                        {book.cover_settings
                                                            ? t(
                                                                  'cover.create.edit',
                                                              )
                                                            : t(
                                                                  'cover.create.button',
                                                              )}
                                                    </Button>
                                                    {book.cover_settings && (
                                                        <Button
                                                            variant="secondary"
                                                            size="sm"
                                                            onClick={() => {
                                                                window.location.href =
                                                                    downloadCover.url(
                                                                        book.id,
                                                                    );
                                                            }}
                                                            disabled={
                                                                isConvertingPdf
                                                            }
                                                        >
                                                            <Download
                                                                size={14}
                                                            />
                                                            {t(
                                                                'cover.download',
                                                            )}
                                                        </Button>
                                                    )}
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={
                                                            handleCoverRemove
                                                        }
                                                        className="text-delete hover:text-delete/80"
                                                        disabled={
                                                            isConvertingPdf
                                                        }
                                                    >
                                                        <Trash2 size={14} />
                                                        {t('cover.remove')}
                                                    </Button>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="flex items-end gap-6">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        fileInputRef.current?.click()
                                                    }
                                                    disabled={isConvertingPdf}
                                                    className="flex h-48 w-36 flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-border-dashed transition-colors hover:border-ink-faint hover:bg-neutral-bg disabled:cursor-not-allowed disabled:opacity-60"
                                                >
                                                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-neutral-bg">
                                                        {isConvertingPdf ? (
                                                            <Spinner className="size-[18px] text-ink-muted" />
                                                        ) : (
                                                            <ImagePlus
                                                                size={18}
                                                                className="text-ink-muted"
                                                            />
                                                        )}
                                                    </div>
                                                    <span className="text-xs font-medium text-ink-muted">
                                                        {isConvertingPdf
                                                            ? t(
                                                                  'cover.converting',
                                                              )
                                                            : t('cover.upload')}
                                                    </span>
                                                </button>
                                                <div className="flex flex-col gap-2 pb-1">
                                                    <p className="text-xs text-ink-faint">
                                                        {t('cover.createHint')}
                                                    </p>
                                                    <Button
                                                        variant="secondary"
                                                        size="sm"
                                                        onClick={() =>
                                                            setCoverCreatorOpen(
                                                                true,
                                                            )
                                                        }
                                                    >
                                                        <Sparkles size={14} />
                                                        {t(
                                                            'cover.create.button',
                                                        )}
                                                    </Button>
                                                </div>
                                            </div>
                                        )}

                                        {coverError && (
                                            <p className="mt-3 text-xs text-danger">
                                                {coverError}
                                            </p>
                                        )}

                                        <input
                                            ref={fileInputRef}
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf"
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
                                    <p className="text-[13px] text-ink-muted">
                                        {t('frontMatter.description')}
                                    </p>

                                    <div className="mt-5 flex flex-col gap-5">
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

                                        <div className="border-t border-border-subtle" />

                                        <FormField
                                            id="prologue"
                                            label={t('frontMatter.prologue')}
                                        >
                                            <Select
                                                value={
                                                    currentPrologueId
                                                        ? String(
                                                              currentPrologueId,
                                                          )
                                                        : 'none'
                                                }
                                                onChange={handlePrologueChange}
                                            >
                                                <option value="none">
                                                    {t(
                                                        'frontMatter.noPrologue',
                                                    )}
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

            {coverCreatorOpen && (
                <CoverCreatorDialog
                    bookId={book.id}
                    trimSizes={trimSizes}
                    initialTitle={
                        book.cover_settings?.title ?? book.title ?? ''
                    }
                    initialSubtitle={
                        book.cover_settings?.subtitle ?? book.cover_genre ?? ''
                    }
                    initialAuthor={
                        book.cover_settings?.author ?? book.author ?? ''
                    }
                    initialTrimSize={book.cover_settings?.trim_size ?? ''}
                    initialSpineWidth={book.cover_settings?.spine_width ?? 0}
                    klappentext={book.klappentext ?? ''}
                    onClose={() => setCoverCreatorOpen(false)}
                    onSaved={() => setCoverCreatorOpen(false)}
                />
            )}
        </>
    );
}
