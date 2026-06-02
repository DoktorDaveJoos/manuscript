import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    generateCover,
    uploadCover,
} from '@/actions/App/Http/Controllers/PublishController';
import type { TrimSizeOption } from '@/components/export/types';
import Button from '@/components/ui/Button';
import Dialog from '@/components/ui/Dialog';
import FormField from '@/components/ui/FormField';
import Input from '@/components/ui/Input';
import NumberInput from '@/components/ui/NumberInput';
import Select from '@/components/ui/Select';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/ToggleGroup';
import { pdfjsLib, renderPdfFirstPageToPng } from '@/lib/pdfjs';
import { jsonFetchHeaders } from '@/lib/utils';
import type { CoverSettings } from '@/types/models';

type CoverFace = 'front' | 'back';

type CoverCreatorDialogProps = {
    bookId: number;
    trimSizes: TrimSizeOption[];
    initialTitle: string;
    initialSubtitle: string;
    initialAuthor: string;
    initialTrimSize: string;
    initialSpineWidth: number;
    klappentext: string;
    onClose: () => void;
    onSaved: () => void;
};

const DEFAULT_TRIM = '13x19cm';

function base64ToPdfFile(base64: string): File {
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return new File([bytes], 'cover.pdf', { type: 'application/pdf' });
}

export default function CoverCreatorDialog({
    bookId,
    trimSizes,
    initialTitle,
    initialSubtitle,
    initialAuthor,
    initialTrimSize,
    initialSpineWidth,
    klappentext,
    onClose,
    onSaved,
}: CoverCreatorDialogProps) {
    const { t, i18n } = useTranslation('publish');

    const isMetric = !i18n.language.startsWith('en');
    const trimLabel = useCallback(
        (o: TrimSizeOption) => (isMetric ? o.labelMetric : o.label),
        [isMetric],
    );

    const [title, setTitle] = useState(initialTitle);
    const [subtitle, setSubtitle] = useState(initialSubtitle);
    const [author, setAuthor] = useState(initialAuthor);
    const [trimSize, setTrimSize] = useState(
        initialTrimSize ||
            (trimSizes.some((o) => o.value === DEFAULT_TRIM)
                ? DEFAULT_TRIM
                : (trimSizes[0]?.value ?? DEFAULT_TRIM)),
    );
    const [spineWidth, setSpineWidth] = useState(initialSpineWidth);

    // Which panel the preview shows. The saved cover image is always the front,
    // regardless of the panel currently displayed.
    const [face, setFace] = useState<CoverFace>('front');

    const [loading, setLoading] = useState(false);
    const [backLoading, setBackLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    // Base64 of the most recent successful front preview — reused when saving.
    const [previewPdf, setPreviewPdf] = useState<string | null>(null);
    // Base64 of the back-panel preview (the Klappentext), shown but never saved.
    const [backPdf, setBackPdf] = useState<string | null>(null);

    const canvasRef = useRef<HTMLCanvasElement>(null);
    const abortRef = useRef<AbortController | null>(null);
    const backAbortRef = useRef<AbortController | null>(null);

    const hasKlappentext = klappentext.trim() !== '';

    const settings = useMemo<CoverSettings>(
        () => ({
            title: title.trim(),
            subtitle: subtitle.trim(),
            author: author.trim(),
            trim_size: trimSize,
            spine_width: spineWidth,
        }),
        [title, subtitle, author, trimSize, spineWidth],
    );

    // Debounced live front preview: regenerate the cover PDF whenever a field changes.
    useEffect(() => {
        if (!title.trim()) {
            setPreviewPdf(null);
            return;
        }

        const timer = setTimeout(() => {
            abortRef.current?.abort();
            const controller = new AbortController();
            abortRef.current = controller;

            setLoading(true);
            setError(null);

            fetch(generateCover.url(bookId), {
                method: 'POST',
                headers: jsonFetchHeaders(),
                signal: controller.signal,
                body: JSON.stringify(settings),
            })
                .then(async (res) => {
                    if (!res.ok) {
                        throw new Error((await res.text()).slice(0, 200));
                    }
                    return res.json() as Promise<{ pdf: string }>;
                })
                .then(({ pdf }) => {
                    if (controller.signal.aborted) return;
                    setPreviewPdf(pdf);
                    setLoading(false);
                })
                .catch((err: unknown) => {
                    if (
                        err instanceof DOMException &&
                        err.name === 'AbortError'
                    ) {
                        return;
                    }
                    setError(t('cover.create.previewError'));
                    setLoading(false);
                });
        }, 400);

        return () => {
            clearTimeout(timer);
            abortRef.current?.abort();
        };
    }, [bookId, settings, title, t]);

    // Back-panel preview: only fetched when the back face is shown and the book has
    // a Klappentext. The server injects the saved Klappentext onto the back panel.
    useEffect(() => {
        if (face !== 'back' || !hasKlappentext || !title.trim()) {
            return;
        }

        const timer = setTimeout(() => {
            backAbortRef.current?.abort();
            const controller = new AbortController();
            backAbortRef.current = controller;

            setBackLoading(true);
            setError(null);

            fetch(generateCover.url(bookId), {
                method: 'POST',
                headers: jsonFetchHeaders(),
                signal: controller.signal,
                body: JSON.stringify({ ...settings, face: 'back' }),
            })
                .then(async (res) => {
                    if (!res.ok) {
                        throw new Error((await res.text()).slice(0, 200));
                    }
                    return res.json() as Promise<{ pdf: string }>;
                })
                .then(({ pdf }) => {
                    if (controller.signal.aborted) return;
                    setBackPdf(pdf);
                    setBackLoading(false);
                })
                .catch((err: unknown) => {
                    if (
                        err instanceof DOMException &&
                        err.name === 'AbortError'
                    ) {
                        return;
                    }
                    setError(t('cover.create.previewError'));
                    setBackLoading(false);
                });
        }, 400);

        return () => {
            clearTimeout(timer);
            backAbortRef.current?.abort();
        };
    }, [bookId, settings, face, hasKlappentext, title, t]);

    const displayedPdf = face === 'back' ? backPdf : previewPdf;
    const displayLoading = face === 'back' ? backLoading : loading;
    const showBackEmpty = face === 'back' && !hasKlappentext;

    // Render the active preview PDF's first page to the canvas.
    useEffect(() => {
        if (!displayedPdf) return;
        let cancelled = false;

        const render = async () => {
            const file = base64ToPdfFile(displayedPdf);
            const buffer = await file.arrayBuffer();
            const doc = await pdfjsLib.getDocument({
                data: buffer,
                disableFontFace: true,
            }).promise;
            try {
                const canvas = canvasRef.current;
                if (cancelled || !canvas) return;
                const page = await doc.getPage(1);
                const targetWidth = 280;
                const baseViewport = page.getViewport({ scale: 1 });
                const scale = targetWidth / baseViewport.width;
                const viewport = page.getViewport({ scale });
                const outputScale = window.devicePixelRatio || 1;

                canvas.width = Math.floor(viewport.width * outputScale);
                canvas.height = Math.floor(viewport.height * outputScale);
                canvas.style.width = `${Math.floor(viewport.width)}px`;
                canvas.style.height = `${Math.floor(viewport.height)}px`;

                const ctx = canvas.getContext('2d');
                if (!ctx) return;
                ctx.scale(outputScale, outputScale);
                await page.render({ canvasContext: ctx, viewport, canvas })
                    .promise;
            } finally {
                await doc.destroy();
            }
        };

        render().catch(() => {
            /* preview render is best-effort */
        });

        return () => {
            cancelled = true;
        };
    }, [displayedPdf]);

    const handleSave = useCallback(async () => {
        if (!previewPdf || !title.trim()) return;
        setSaving(true);
        setError(null);

        try {
            const pdfFile = base64ToPdfFile(previewPdf);
            const png = await renderPdfFirstPageToPng(pdfFile);

            // Flatten cover_settings into bracketed keys so the multipart payload
            // stays Record<string, string | File>; Laravel parses them into the
            // nested cover_settings array the upload request validates.
            router.post(
                uploadCover.url(bookId),
                {
                    cover_image: png,
                    'cover_settings[title]': settings.title ?? '',
                    'cover_settings[subtitle]': settings.subtitle ?? '',
                    'cover_settings[author]': settings.author ?? '',
                    'cover_settings[trim_size]': settings.trim_size ?? '',
                    'cover_settings[spine_width]': String(
                        settings.spine_width ?? 0,
                    ),
                } as Record<string, string | File>,
                {
                    forceFormData: true,
                    preserveScroll: true,
                    onSuccess: () => {
                        setSaving(false);
                        onSaved();
                    },
                    onError: () => {
                        setSaving(false);
                        setError(t('cover.create.saveError'));
                    },
                },
            );
        } catch {
            setSaving(false);
            setError(t('cover.create.saveError'));
        }
    }, [previewPdf, title, bookId, settings, onSaved, t]);

    return (
        <Dialog
            onClose={onClose}
            width={720}
            title={t('cover.create.title')}
            className="p-8"
        >
            <h2 className="font-serif text-2xl leading-8 font-semibold tracking-[-0.01em] text-ink">
                {t('cover.create.title')}
            </h2>
            <p className="mt-1 text-[13px] text-ink-muted">
                {t('cover.create.description')}
            </p>

            <div className="mt-6 flex gap-8">
                {/* Form */}
                <div className="flex w-1/2 flex-col gap-4">
                    <FormField label={t('cover.create.titleField')}>
                        <Input
                            value={title}
                            onChange={(e) => setTitle(e.target.value)}
                            placeholder={t('cover.create.titlePlaceholder')}
                            autoFocus
                        />
                    </FormField>
                    <FormField label={t('cover.create.subtitleField')}>
                        <Input
                            value={subtitle}
                            onChange={(e) => setSubtitle(e.target.value)}
                            placeholder={t('cover.create.subtitlePlaceholder')}
                        />
                    </FormField>
                    <FormField label={t('cover.create.authorField')}>
                        <Input
                            value={author}
                            onChange={(e) => setAuthor(e.target.value)}
                            placeholder={t('cover.create.authorPlaceholder')}
                        />
                    </FormField>
                    <FormField label={t('cover.create.trimSizeField')}>
                        <Select
                            value={trimSize}
                            onChange={(e) => setTrimSize(e.target.value)}
                        >
                            {trimSizes.map((o) => (
                                <option key={o.value} value={o.value}>
                                    {trimLabel(o)}
                                </option>
                            ))}
                        </Select>
                    </FormField>
                    <FormField label={t('cover.create.spineWidthField')}>
                        <NumberInput
                            value={spineWidth}
                            onChange={setSpineWidth}
                            min={0}
                            max={50}
                            step={0.5}
                            unit="mm"
                            aria-label={t('cover.create.spineWidthField')}
                        />
                        <p className="mt-1.5 text-xs text-ink-faint">
                            {t('cover.create.spineWidthHint')}
                        </p>
                    </FormField>
                </div>

                {/* Preview */}
                <div className="flex w-1/2 flex-col items-center gap-3">
                    <ToggleGroup
                        type="single"
                        value={face}
                        onValueChange={(value) => {
                            if (value) setFace(value as CoverFace);
                        }}
                    >
                        <ToggleGroupItem value="front">
                            {t('cover.create.faceFront')}
                        </ToggleGroupItem>
                        <ToggleGroupItem value="back">
                            {t('cover.create.faceBack')}
                        </ToggleGroupItem>
                    </ToggleGroup>

                    <div className="flex w-full flex-1 flex-col items-center justify-center rounded-lg border border-border-light bg-neutral-bg p-4">
                        {showBackEmpty ? (
                            <p className="px-4 text-center text-xs text-ink-faint">
                                {t('cover.create.backEmpty')}
                            </p>
                        ) : displayLoading && !displayedPdf ? (
                            <Spinner className="size-5 text-ink-muted" />
                        ) : displayedPdf ? (
                            <div className="relative">
                                <canvas
                                    ref={canvasRef}
                                    className="rounded shadow-md"
                                />
                                {displayLoading && (
                                    <span className="absolute top-2 right-2 inline-block size-3 animate-spin rounded-full border-2 border-ink-faint border-t-ink" />
                                )}
                            </div>
                        ) : (
                            <p className="text-center text-xs text-ink-faint">
                                {t('cover.create.previewEmpty')}
                            </p>
                        )}
                    </div>
                </div>
            </div>

            {error && <p className="mt-4 text-xs text-delete">{error}</p>}

            <div className="mt-8 flex justify-end gap-3">
                <Button variant="secondary" onClick={onClose} disabled={saving}>
                    {t('cover.create.cancel')}
                </Button>
                <Button
                    variant="primary"
                    onClick={handleSave}
                    disabled={!previewPdf || !title.trim() || saving}
                >
                    {saving ? <Spinner className="size-[14px]" /> : null}
                    {t('cover.create.save')}
                </Button>
            </div>
        </Dialog>
    );
}
