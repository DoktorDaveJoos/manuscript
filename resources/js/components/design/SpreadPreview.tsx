import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { PDFDocumentProxy } from 'pdfjs-dist';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import { pdfjsLib } from '@/lib/pdfjs';
import { jsonFetchHeaders } from '@/lib/utils';

interface SpreadPreviewProps {
    bookId: number;
    /** Template slug the preview should render with (built-in or custom:<id>). */
    templateSlug: string;
    /** Bumped by the parent whenever the selected template's settings change. */
    version: number;
    trimWidth: number;
    trimHeight: number;
}

function SpreadPageCanvas({
    pdfDoc,
    pageNum,
    width,
    height,
}: {
    pdfDoc: PDFDocumentProxy;
    pageNum: number;
    width: number;
    height: number;
}) {
    const canvasRef = useRef<HTMLCanvasElement>(null);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;

        let cancelled = false;

        (async () => {
            try {
                const page = await pdfDoc.getPage(pageNum);
                if (cancelled) return;

                const dpr = window.devicePixelRatio || 1;
                const baseViewport = page.getViewport({ scale: 1 });
                const scale = (width / baseViewport.width) * dpr;
                const viewport = page.getViewport({ scale });
                canvas.width = viewport.width;
                canvas.height = viewport.height;

                const ctx = canvas.getContext('2d');
                if (!ctx) return;

                ctx.fillStyle = '#FFFEFA';
                ctx.fillRect(0, 0, canvas.width, canvas.height);

                await page.render({
                    canvas: null,
                    canvasContext: ctx,
                    viewport,
                }).promise;
            } catch {
                // Render cancelled or failed
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [pdfDoc, pageNum, width]);

    return (
        <canvas
            ref={canvasRef}
            className="shadow-[0_6px_20px_#00000014] dark:shadow-[0_6px_20px_#00000040]"
            style={{ width, height, backgroundColor: '#FFFEFA' }}
        />
    );
}

/**
 * Center stage of the Book Designer: a facing-page spread rendered by the
 * real PDF pipeline (mPDF via the export preview endpoint), with page-turn
 * controls. What you see is exactly what exports.
 */
export default function SpreadPreview({
    bookId,
    templateSlug,
    version,
    trimWidth,
    trimHeight,
}: SpreadPreviewProps) {
    const { t } = useTranslation('design');

    const containerRef = useRef<HTMLDivElement>(null);
    const [containerSize, setContainerSize] = useState({
        width: 0,
        height: 0,
    });
    const [pdfDoc, setPdfDoc] = useState<PDFDocumentProxy | null>(null);
    const [spreadIndex, setSpreadIndex] = useState(0);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const abortRef = useRef<AbortController | null>(null);

    useEffect(() => {
        const el = containerRef.current;
        if (!el) return;
        const observer = new ResizeObserver(([entry]) => {
            setContainerSize({
                width: entry.contentRect.width,
                height: entry.contentRect.height,
            });
        });
        observer.observe(el);
        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        const timer = setTimeout(() => {
            abortRef.current?.abort();
            const controller = new AbortController();
            abortRef.current = controller;
            setLoading(true);
            setError(null);

            fetch(`/books/${bookId}/export/preview`, {
                method: 'POST',
                headers: jsonFetchHeaders(),
                signal: controller.signal,
                body: JSON.stringify({
                    format: 'pdf',
                    scope: 'full',
                    template: templateSlug,
                }),
            })
                .then(async (res) => {
                    if (!res.ok) {
                        throw new Error(await res.text());
                    }
                    return res.json() as Promise<{ pdf: string }>;
                })
                .then(async ({ pdf }) => {
                    if (controller.signal.aborted) return;

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
                    setSpreadIndex((current) =>
                        Math.min(current, Math.floor(doc.numPages / 2)),
                    );
                    setLoading(false);
                })
                .catch((err: unknown) => {
                    if (
                        err instanceof DOMException &&
                        err.name === 'AbortError'
                    ) {
                        return;
                    }
                    setError(t('preview.error'));
                    setLoading(false);
                });
        }, 400);

        return () => {
            clearTimeout(timer);
            abortRef.current?.abort();
        };
    }, [bookId, templateSlug, version, t]);

    useEffect(() => {
        return () => {
            pdfDoc?.destroy();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Facing pages the way a bound book opens: page 1 is a recto standing
    // alone (blank verso beside it), then (2,3), (4,5), … — odd pages always
    // sit on the right, so the gutter margins face the spine in the preview.
    const pageCount = pdfDoc?.numPages ?? 0;
    const spreadCount = pageCount > 0 ? Math.floor(pageCount / 2) + 1 : 1;
    const rightPage = spreadIndex * 2 + 1;
    const leftPage = rightPage - 1;

    // Fit the spread (two pages + gap) inside the available stage
    const pageSize = useMemo(() => {
        const gap = 4;
        const padding = 48;
        const availableWidth = Math.max(
            0,
            (containerSize.width - padding - gap) / 2,
        );
        const availableHeight = Math.max(0, containerSize.height - padding);
        const ratio = trimHeight / trimWidth;
        const width = Math.min(availableWidth, availableHeight / ratio);
        return { width, height: width * ratio };
    }, [containerSize, trimWidth, trimHeight]);

    return (
        <div className="flex h-full min-w-0 flex-1 flex-col">
            <div
                ref={containerRef}
                className="relative flex min-h-0 flex-1 items-center justify-center"
            >
                {loading && (
                    <span
                        className="absolute top-4 right-4 inline-block size-3 animate-spin rounded-full border-2 border-ink-faint border-t-ink"
                        data-testid="design-preview-loading"
                    />
                )}
                {error ? (
                    <p className="text-[12px] text-ink-muted">{error}</p>
                ) : pdfDoc && pageSize.width > 0 ? (
                    <div
                        className="flex gap-1"
                        data-testid="design-spread"
                        data-page-ratio={(trimHeight / trimWidth).toFixed(3)}
                    >
                        {leftPage >= 1 ? (
                            <SpreadPageCanvas
                                pdfDoc={pdfDoc}
                                pageNum={leftPage}
                                width={pageSize.width}
                                height={pageSize.height}
                            />
                        ) : (
                            <div
                                className="bg-neutral-bg"
                                data-testid="design-spread-blank"
                                style={{
                                    width: pageSize.width,
                                    height: pageSize.height,
                                }}
                            />
                        )}
                        {rightPage <= pageCount ? (
                            <SpreadPageCanvas
                                pdfDoc={pdfDoc}
                                pageNum={rightPage}
                                width={pageSize.width}
                                height={pageSize.height}
                            />
                        ) : (
                            <div
                                className="bg-neutral-bg"
                                data-testid="design-spread-blank"
                                style={{
                                    width: pageSize.width,
                                    height: pageSize.height,
                                }}
                            />
                        )}
                    </div>
                ) : !loading ? (
                    <p className="text-[12px] text-ink-muted">
                        {t('preview.empty')}
                    </p>
                ) : (
                    <div className="flex gap-1">
                        {[0, 1].map((i) => (
                            <div
                                key={i}
                                className="animate-pulse bg-neutral-bg"
                                style={{
                                    width: pageSize.width || 240,
                                    height: pageSize.height || 384,
                                }}
                            />
                        ))}
                    </div>
                )}
            </div>
            <div className="flex items-center justify-center gap-3 pb-4">
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={t('preview.previous')}
                    disabled={spreadIndex === 0}
                    onClick={() => setSpreadIndex((i) => Math.max(0, i - 1))}
                >
                    <ChevronLeft className="size-4" />
                </Button>
                <span className="text-[11px] text-ink-faint tabular-nums">
                    {pageCount > 0
                        ? Math.max(1, leftPage) ===
                          Math.min(rightPage, pageCount)
                            ? t('preview.pageSingle', {
                                  page: Math.max(1, leftPage),
                                  total: pageCount,
                              })
                            : t('preview.pageOf', {
                                  first: Math.max(1, leftPage),
                                  last: Math.min(rightPage, pageCount),
                                  total: pageCount,
                              })
                        : '—'}
                </span>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={t('preview.next')}
                    disabled={spreadIndex >= spreadCount - 1}
                    onClick={() =>
                        setSpreadIndex((i) => Math.min(spreadCount - 1, i + 1))
                    }
                >
                    <ChevronRight className="size-4" />
                </Button>
            </div>
        </div>
    );
}
