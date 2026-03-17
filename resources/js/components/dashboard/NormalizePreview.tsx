import Button from '@/components/ui/Button';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { getXsrfToken } from '@/lib/csrf';
import type { NormalizePreviewResult } from '@/types/models';

export default function NormalizePreview({
    bookId,
    chapterId,
    onClose,
}: {
    bookId: number;
    chapterId?: number;
    onClose: () => void;
}) {
    const { t } = useTranslation('dashboard');
    const [loading, setLoading] = useState(true);
    const [applying, setApplying] = useState(false);
    const [preview, setPreview] = useState<NormalizePreviewResult | null>(null);
    const [error, setError] = useState<string | null>(null);

    const previewUrl = chapterId
        ? `/books/${bookId}/chapters/${chapterId}/normalize/preview`
        : `/books/${bookId}/normalize/preview`;

    const applyUrl = chapterId
        ? `/books/${bookId}/chapters/${chapterId}/normalize/apply`
        : `/books/${bookId}/normalize/apply`;

    useEffect(() => {
        const controller = new AbortController();

        fetch(previewUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            signal: controller.signal,
        })
            .then((res) => {
                if (!res.ok) throw new Error('Failed to load preview');
                return res.json();
            })
            .then((data) => {
                setPreview(data);
                setLoading(false);
            })
            .catch((e) => {
                if (e.name !== 'AbortError') {
                    setError(t('normalize.loadError'));
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [previewUrl, t]);

    const handleApply = useCallback(async () => {
        setApplying(true);

        try {
            const res = await fetch(applyUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
            });

            if (!res.ok) throw new Error('Apply failed');

            onClose();
            router.reload();
        } catch {
            setError(t('normalize.applyError'));
            setApplying(false);
        }
    }, [applyUrl, onClose, t]);

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-ink/20" onClick={onClose}>
            <div
                className="w-full max-w-lg rounded-lg border border-border bg-surface-card shadow-lg"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center justify-between border-b border-border px-6 py-4">
                    <h2 className="text-sm font-medium text-ink">
                        {chapterId ? t('normalize.titleChapter') : t('normalize.titleManuscript')}
                    </h2>
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-ink-faint transition-colors hover:text-ink"
                    >
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                        </svg>
                    </button>
                </div>

                <div className="max-h-80 overflow-y-auto px-6 py-4">
                    {loading && (
                        <div className="flex items-center gap-2 text-sm text-ink-muted">
                            <span className="inline-block size-3 animate-spin rounded-full border-2 border-ink-faint border-t-ink" />
                            {t('normalize.analyzing')}
                        </div>
                    )}

                    {error && <p className="text-sm text-red-600">{error}</p>}

                    {preview && preview.total_changes === 0 && (
                        <p className="text-sm text-ink-muted">{t('normalize.noChanges')}</p>
                    )}

                    {preview && preview.total_changes > 0 && (
                        <div className="flex flex-col gap-3">
                            <p className="text-sm text-ink-muted">
                                {t('normalize.foundChanges', {
                                    count: preview.total_changes,
                                    chapters: preview.chapters.filter((c) => c.total_changes > 0).length,
                                })}
                            </p>
                            {preview.chapters
                                .filter((c) => c.total_changes > 0)
                                .map((ch) => (
                                    <div key={ch.id} className="rounded-md border border-border-light px-4 py-3">
                                        <p className="text-sm font-medium text-ink">{ch.title}</p>
                                        <div className="mt-1.5 flex flex-wrap gap-2">
                                            {ch.changes.map((change) => (
                                                <span
                                                    key={change.rule}
                                                    className="rounded bg-neutral-bg px-2 py-0.5 text-xs text-ink-muted"
                                                >
                                                    {change.rule}: {change.count}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                        </div>
                    )}
                </div>

                <div className="flex items-center justify-end gap-3 border-t border-border px-6 py-4">
                    <Button variant="ghost" type="button" onClick={onClose}>
                        {t('normalize.cancel')}
                    </Button>
                    {preview && preview.total_changes > 0 && (
                        <Button variant="primary" type="button" onClick={handleApply} disabled={applying}>
                            {applying ? t('normalize.applying') : t('normalize.applyChanges')}
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}
