// Type augmentation for ES2025 Map.getOrInsertComputed
declare global {
    interface Map<K, V> {
        getOrInsertComputed(key: K, cb: (k: K) => V): V;
    }
}

// Polyfill Map.getOrInsertComputed (ES2025) — needed for pdfjs in older Electron/Chromium
if (!Map.prototype.getOrInsertComputed) {
    Map.prototype.getOrInsertComputed = function (
        key: unknown,
        cb: (k: unknown) => unknown,
    ) {
        if (this.has(key)) return this.get(key);
        const v = cb(key);
        this.set(key, v);
        return v;
    };
}

import * as pdfjsLib from 'pdfjs-dist';
import type { PDFDocumentProxy } from 'pdfjs-dist';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Virtuoso } from 'react-virtuoso';
import type {
    Format,
    MatterItem,
    TrimSizeOption,
} from '@/components/export/types';
import { VISUAL_FORMATS } from '@/components/export/types';
import { useResizablePanel } from '@/hooks/useResizablePanel';
import { jsonFetchHeaders } from '@/lib/utils';

pdfjsLib.GlobalWorkerOptions.workerSrc = new URL(
    'pdfjs-dist/build/pdf.worker.mjs',
    import.meta.url,
).toString();

const PAGE_GAP = 12;
const PAGE_PADDING = 56; // px-7 = 28px each side
const EBOOK_SPEC: TrimSizeOption = {
    value: 'ebook',
    label: 'E-Reader',
    width: 90,
    height: 122,
};
function computePageDimensions(
    spec: TrimSizeOption,
    pageWidth: number,
): { pageHeight: number; scaleFactor: number } {
    const pageHeight = Math.round(pageWidth * (spec.height / spec.width));
    const pageWidthPt = (spec.width / 25.4) * 72;
    const scaleFactor = pageWidth / pageWidthPt;
    return { pageHeight, scaleFactor };
}

interface ExportPreviewProps {
    bookId: number;
    format: Format;
    trimSize: string;
    trimSizes: TrimSizeOption[];
    fontSize: number;
    includeChapterTitles: boolean;
    showPageNumbers: boolean;
    includeActBreaks: boolean;
    selectedChapterIds: Set<number>;
    orderedChapters: Array<{ id: number }>;
    frontMatter: MatterItem[];
    backMatter: MatterItem[];
    template?: string;
    fontPairing?: string;
    sceneBreakStyle?: string;
    dropCaps?: boolean;
    includeCover?: boolean;
}

function SkeletonPage({ width, height }: { width: number; height: number }) {
    return (
        <div className="flex justify-center px-7 pb-3">
            <div
                className="animate-pulse rounded bg-neutral-bg shadow-[0_6px_20px_#00000014] dark:shadow-[0_6px_20px_#00000040]"
                style={{ width, height }}
            />
        </div>
    );
}

function PdfPageCanvas({
    pdfDoc,
    pageNum,
    scaleFactor,
    pageWidth,
    pageHeight,
}: {
    pdfDoc: PDFDocumentProxy;
    pageNum: number;
    scaleFactor: number;
    pageWidth: number;
    pageHeight: number;
}) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const renderingRef = useRef(false);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas || renderingRef.current) {
            return;
        }

        renderingRef.current = true;

        let cancelled = false;

        (async () => {
            try {
                const page = await pdfDoc.getPage(pageNum);
                if (cancelled) {
                    return;
                }

                const viewport = page.getViewport({ scale: scaleFactor });
                canvas.width = viewport.width;
                canvas.height = viewport.height;

                const ctx = canvas.getContext('2d');
                if (!ctx) {
                    return;
                }

                ctx.fillStyle = '#FFFEFA';
                ctx.fillRect(0, 0, canvas.width, canvas.height);

                await page.render({
                    canvas: null,
                    canvasContext: ctx,
                    viewport,
                }).promise;
            } catch {
                // Render cancelled or failed
            } finally {
                renderingRef.current = false;
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [pdfDoc, pageNum, scaleFactor]);

    return (
        <div className="flex justify-center px-7 pb-3">
            <canvas
                ref={canvasRef}
                className="rounded shadow-[0_6px_20px_#00000014] dark:shadow-[0_6px_20px_#00000040]"
                style={{
                    width: pageWidth,
                    height: pageHeight,
                    backgroundColor: '#FFFEFA',
                }}
            />
        </div>
    );
}

const VirtuosoFooter = () => <div className="h-6" />;

export default function ExportPreview({
    bookId,
    format,
    trimSize,
    trimSizes,
    fontSize,
    includeChapterTitles,
    showPageNumbers,
    includeActBreaks,
    selectedChapterIds,
    orderedChapters,
    frontMatter,
    backMatter,
    template = 'classic',
    fontPairing = 'classic-serif',
    sceneBreakStyle = 'asterisks',
    dropCaps = true,
    includeCover = false,
}: ExportPreviewProps) {
    const { t } = useTranslation('export');

    const {
        width: panelWidth,
        panelRef,
        handleMouseDown,
    } = useResizablePanel({
        storageKey: 'manuscript:export-preview-width',
        minWidth: 300,
        maxWidth: 700,
        defaultWidth: 400,
        direction: 'right',
    });

    const [pdfDoc, setPdfDoc] = useState<PDFDocumentProxy | null>(null);
    const [pageCount, setPageCount] = useState(0);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const abortControllerRef = useRef<AbortController | null>(null);

    const pageWidth = panelWidth - PAGE_PADDING;
    const isEbookFormat = format === 'epub' || format === 'kdp';
    const hasVisualPreview = VISUAL_FORMATS.has(format);
    const trimSpec = useMemo(
        () =>
            isEbookFormat
                ? EBOOK_SPEC
                : (trimSizes.find((t) => t.value === trimSize) ?? trimSizes[0]),
        [trimSizes, trimSize, isEbookFormat],
    );
    const { pageHeight, scaleFactor } = computePageDimensions(
        trimSpec,
        pageWidth,
    );

    const selectedIdsArray = useMemo(
        () => Array.from(selectedChapterIds),
        [selectedChapterIds],
    );

    const orderedChapterIds = useMemo(
        () => orderedChapters.map((ch) => ch.id),
        [orderedChapters],
    );

    const checkedFrontMatter = useMemo(
        () => frontMatter.filter((item) => item.checked).map((item) => item.id),
        [frontMatter],
    );

    const checkedBackMatter = useMemo(
        () => backMatter.filter((item) => item.checked).map((item) => item.id),
        [backMatter],
    );

    const hasSelectedChapters = selectedIdsArray.length > 0;

    useEffect(() => {
        if (!hasSelectedChapters || !hasVisualPreview) {
            setPdfDoc(null);
            setPageCount(0);
            return;
        }

        const timer = setTimeout(() => {
            abortControllerRef.current?.abort();
            const controller = new AbortController();
            abortControllerRef.current = controller;

            setLoading(true);
            setError(null);

            const orderedSelectedIds = orderedChapterIds.filter((id) =>
                selectedChapterIds.has(id),
            );

            fetch(`/books/${bookId}/export/preview`, {
                method: 'POST',
                headers: jsonFetchHeaders(),
                signal: controller.signal,
                body: JSON.stringify({
                    format,
                    template,
                    trim_size: trimSize,
                    font_size: fontSize,
                    include_chapter_titles: includeChapterTitles,
                    show_page_numbers: showPageNumbers,
                    include_act_breaks: includeActBreaks,
                    chapter_ids: orderedSelectedIds,
                    front_matter: checkedFrontMatter,
                    back_matter: checkedBackMatter,
                    font_pairing: fontPairing,
                    scene_break_style: sceneBreakStyle,
                    drop_caps: dropCaps,
                    include_cover: includeCover,
                }),
            })
                .then(async (res) => {
                    if (!res.ok) {
                        const body = await res.text();
                        const truncated = body.slice(0, 500);
                        console.error('Preview error', res.status, truncated);
                        throw new Error(truncated.slice(0, 300));
                    }
                    return res.json() as Promise<{ pdf: string }>;
                })
                .then(async ({ pdf }) => {
                    if (controller.signal.aborted) {
                        return;
                    }

                    const binaryString = atob(pdf);
                    const bytes = new Uint8Array(binaryString.length);
                    for (let i = 0; i < binaryString.length; i++) {
                        bytes[i] = binaryString.charCodeAt(i);
                    }

                    const doc = await pdfjsLib.getDocument({
                        data: bytes,
                        disableFontFace: true,
                    }).promise;

                    if (controller.signal.aborted) {
                        doc.destroy();
                        return;
                    }

                    setPdfDoc((prev) => {
                        prev?.destroy();
                        return doc;
                    });
                    setPageCount(doc.numPages);
                    setLoading(false);
                })
                .catch((err: unknown) => {
                    if (
                        err instanceof DOMException &&
                        err.name === 'AbortError'
                    ) {
                        return;
                    }
                    const message =
                        err instanceof Error
                            ? err.message
                            : 'Failed to load preview';
                    setError(message);
                    setLoading(false);
                });
        }, 500);

        return () => {
            clearTimeout(timer);
            abortControllerRef.current?.abort();
        };
    }, [
        bookId,
        hasSelectedChapters,
        hasVisualPreview,
        format,
        template,
        trimSize,
        fontSize,
        includeChapterTitles,
        showPageNumbers,
        includeActBreaks,
        selectedIdsArray,
        orderedChapterIds,
        checkedFrontMatter,
        checkedBackMatter,
        fontPairing,
        sceneBreakStyle,
        dropCaps,
        includeCover,
    ]);

    useEffect(() => {
        return () => {
            pdfDoc?.destroy();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const pageIndices = useMemo(
        () => Array.from({ length: pageCount }, (_, i) => i + 1),
        [pageCount],
    );

    const previewLabel = t('preview');

    const templateLabel = template.charAt(0).toUpperCase() + template.slice(1);

    const VirtuosoHeader = useCallback(
        () => (
            <div className="flex items-center justify-between px-7 pt-6 pb-3">
                <span className="text-[11px] font-semibold tracking-[0.08em] text-ink-faint uppercase">
                    {previewLabel}
                </span>
                <div className="flex items-center gap-2">
                    {loading && pdfDoc && (
                        <span className="inline-block size-3 animate-spin rounded-full border-2 border-ink-faint border-t-ink" />
                    )}
                    <span className="text-[11px] text-ink-faint">
                        {templateLabel}
                    </span>
                </div>
            </div>
        ),
        [previewLabel, loading, pdfDoc, templateLabel],
    );

    const virtuosoComponents = useMemo(
        () => ({
            Header: VirtuosoHeader,
            Footer: VirtuosoFooter,
        }),
        [VirtuosoHeader],
    );

    const renderPage = useCallback(
        (_index: number, pageNum: number) => {
            if (!pdfDoc) {
                return <SkeletonPage width={pageWidth} height={pageHeight} />;
            }
            return (
                <PdfPageCanvas
                    pdfDoc={pdfDoc}
                    pageNum={pageNum}
                    scaleFactor={scaleFactor}
                    pageWidth={pageWidth}
                    pageHeight={pageHeight}
                />
            );
        },
        [pdfDoc, scaleFactor, pageWidth, pageHeight],
    );

    const showEmptyState = !hasSelectedChapters || !hasVisualPreview;
    const showSkeleton = hasSelectedChapters && !pdfDoc && loading;
    const showPdf = pdfDoc && pageCount > 0;

    return (
        <aside
            ref={panelRef as React.RefObject<HTMLElement>}
            className="relative flex h-full shrink-0 flex-col items-center border-l border-border-subtle bg-neutral-bg"
            style={{ width: panelWidth }}
        >
            <div
                onMouseDown={handleMouseDown}
                className="group absolute inset-y-0 -left-1 z-10 w-2 cursor-col-resize"
            >
                <div className="absolute inset-y-0 left-[3px] w-px bg-transparent transition-colors group-hover:bg-ink/20" />
            </div>
            {showEmptyState ? (
                <div className="flex w-full flex-1 flex-col items-center gap-3 overflow-y-auto px-7 py-6">
                    <VirtuosoHeader />
                    <div className="flex flex-1 items-center justify-center">
                        <p className="text-center text-[12px] text-ink-muted">
                            {t('preview.empty')}
                        </p>
                    </div>
                </div>
            ) : showSkeleton ? (
                <div className="flex w-full flex-1 flex-col items-center gap-3 overflow-y-auto">
                    <VirtuosoHeader />
                    {Array.from({ length: 3 }, (_, i) => (
                        <SkeletonPage
                            key={i}
                            width={pageWidth}
                            height={pageHeight}
                        />
                    ))}
                </div>
            ) : showPdf ? (
                <div className="relative flex w-full flex-1 flex-col">
                    {loading && (
                        <div className="pointer-events-none absolute inset-0 z-10 bg-surface/40" />
                    )}
                    <Virtuoso
                        className="w-full flex-1"
                        data={pageIndices}
                        fixedItemHeight={pageHeight + PAGE_GAP}
                        overscan={3}
                        components={virtuosoComponents}
                        itemContent={renderPage}
                    />
                </div>
            ) : (
                <div className="flex w-full flex-1 flex-col items-center gap-3 overflow-y-auto px-7 py-6">
                    <VirtuosoHeader />
                    <div className="flex flex-1 items-center justify-center">
                        <p className="text-center text-[12px] text-ink-muted">
                            {error ?? t('preview.empty')}
                        </p>
                    </div>
                </div>
            )}
        </aside>
    );
}
