import { router } from '@inertiajs/react';
import { Download, ImagePlus, Sparkles, Trash2, Upload } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    deleteCover,
    downloadCover,
    uploadCover,
} from '@/actions/App/Http/Controllers/PublishController';
import type { TrimSizeOption } from '@/components/export/types';
import CoverCreatorDialog from '@/components/publish/CoverCreatorDialog';
import Button from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import PageHeader from '@/components/ui/PageHeader';
import SaveStatusIndicator from '@/components/ui/SaveStatusIndicator';
import type { SaveStatus } from '@/components/ui/SaveStatusIndicator';
import { Spinner } from '@/components/ui/spinner';
import BookSettingsLayout from '@/layouts/BookSettingsLayout';
import type { CoverSettings } from '@/types/models';

type BookData = {
    id: number;
    title: string;
    author: string | null;
    cover_settings: CoverSettings | null;
    klappentext: string | null;
    cover_image_url: string | null;
    cover_genre: string;
};

interface Props {
    book: BookData;
    trimSizes: TrimSizeOption[];
}

export default function CoverSettingsPage({ book, trimSizes }: Props) {
    const { t } = useTranslation('publish');
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [coverCreatorOpen, setCoverCreatorOpen] = useState(false);
    const [saveStatus, setSaveStatus] = useState<SaveStatus>('idle');
    const [isConvertingPdf, setIsConvertingPdf] = useState(false);
    const [coverError, setCoverError] = useState<string | null>(null);

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

    return (
        <BookSettingsLayout
            activeSection="cover"
            book={book}
            title={t('cover.pageTitle', { title: book.title })}
        >
            <PageHeader
                title={t('cover.title')}
                subtitle={t('cover.description')}
                actions={<SaveStatusIndicator status={saveStatus} />}
            />

            <Card className="mt-9 p-6">
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
                                onClick={() => fileInputRef.current?.click()}
                                disabled={isConvertingPdf}
                            >
                                {isConvertingPdf ? (
                                    <Spinner className="size-[14px]" />
                                ) : (
                                    <Upload size={14} />
                                )}
                                {isConvertingPdf
                                    ? t('cover.converting')
                                    : t('cover.replace')}
                            </Button>
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => setCoverCreatorOpen(true)}
                                disabled={isConvertingPdf}
                            >
                                <Sparkles size={14} />
                                {book.cover_settings
                                    ? t('cover.create.edit')
                                    : t('cover.create.button')}
                            </Button>
                            {book.cover_settings && (
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    onClick={() => {
                                        window.location.href =
                                            downloadCover.url(book.id);
                                    }}
                                    disabled={isConvertingPdf}
                                >
                                    <Download size={14} />
                                    {t('cover.download')}
                                </Button>
                            )}
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={handleCoverRemove}
                                className="text-delete hover:text-delete/80"
                                disabled={isConvertingPdf}
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
                            onClick={() => fileInputRef.current?.click()}
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
                                    ? t('cover.converting')
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
                                onClick={() => setCoverCreatorOpen(true)}
                            >
                                <Sparkles size={14} />
                                {t('cover.create.button')}
                            </Button>
                        </div>
                    </div>
                )}

                {coverError && (
                    <p className="mt-3 text-xs text-danger">{coverError}</p>
                )}

                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf"
                    onChange={handleCoverUpload}
                    className="hidden"
                />
            </Card>

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
        </BookSettingsLayout>
    );
}
