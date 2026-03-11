import {
    createSnapshot,
    destroyVersion,
    restoreVersion,
    versions,
} from '@/actions/App/Http/Controllers/ChapterController';
import { getXsrfToken } from '@/lib/csrf';
import type { ChapterVersion, VersionSource } from '@/types/models';
import { router } from '@inertiajs/react';
import { Trash } from '@phosphor-icons/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

const sourceBadgeClass: Record<VersionSource, string> = {
    original: 'bg-neutral-bg text-ink-muted',
    ai_revision: 'bg-status-revised/15 text-status-revised',
    manual_edit: 'bg-status-final/15 text-status-final',
    normalization: 'bg-neutral-bg text-ink-muted',
    beautify: 'bg-status-revised/15 text-status-revised',
    snapshot: 'bg-status-final/15 text-status-final',
};

export default function VersionHistoryOverlay({
    bookId,
    chapterId,
    onClose,
}: {
    bookId: number;
    chapterId: number;
    onClose: () => void;
}) {
    const [versionList, setVersionList] = useState<ChapterVersion[] | null>(null);
    const [restoring, setRestoring] = useState<number | null>(null);
    const [deleting, setDeleting] = useState<number | null>(null);
    const [showForm, setShowForm] = useState(false);
    const [summary, setSummary] = useState('');
    const [creating, setCreating] = useState(false);
    const { t, i18n } = useTranslation('editor');
    const ref = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    const sourceLabel = (source: VersionSource) => t(`versionHistory.sourceLabel.${source}`);

    const formatDate = (dateStr: string): string => {
        const date = new Date(dateStr);
        return date.toLocaleDateString(i18n.language, {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    };

    const fetchVersions = useCallback(() => {
        fetch(versions.url({ book: bookId, chapter: chapterId }), {
            headers: { Accept: 'application/json' },
        })
            .then((r) => r.json())
            .then(setVersionList);
    }, [bookId, chapterId]);

    useEffect(() => {
        fetchVersions();
    }, [fetchVersions]);

    useEffect(() => {
        function handleClickOutside(e: MouseEvent) {
            if (ref.current && !ref.current.contains(e.target as Node)) {
                onClose();
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [onClose]);

    useEffect(() => {
        if (showForm) {
            inputRef.current?.focus();
        }
    }, [showForm]);

    const handleRestore = useCallback(
        (version: ChapterVersion) => {
            setRestoring(version.id);
            router.post(
                restoreVersion.url({ book: bookId, chapter: chapterId, version: version.id }),
                {},
                {
                    onFinish: () => {
                        setRestoring(null);
                        onClose();
                    },
                },
            );
        },
        [bookId, chapterId, onClose],
    );

    const handleDelete = useCallback(
        (version: ChapterVersion) => {
            setDeleting(version.id);
            fetch(destroyVersion.url({ book: bookId, chapter: chapterId, version: version.id }), {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
            }).then(() => {
                setDeleting(null);
                fetchVersions();
            });
        },
        [bookId, chapterId, fetchVersions],
    );

    const handleCreate = useCallback(
        (e: React.FormEvent) => {
            e.preventDefault();
            setCreating(true);
            fetch(createSnapshot.url({ book: bookId, chapter: chapterId }), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                body: JSON.stringify({ change_summary: summary || null }),
            }).then(() => {
                setCreating(false);
                setShowForm(false);
                setSummary('');
                fetchVersions();
            });
        },
        [bookId, chapterId, summary, fetchVersions],
    );

    return (
        <div
            ref={ref}
            className="absolute right-0 top-full z-50 mt-1 w-[360px] rounded-lg border border-border bg-surface-card shadow-lg"
        >
            <div className="flex items-center justify-between border-b border-border px-4 py-3">
                <span className="text-sm font-medium text-ink">{t('versionHistory.title')}</span>
                <button
                    type="button"
                    onClick={() => setShowForm((v) => !v)}
                    className="rounded-md bg-neutral-bg px-2 py-1 text-[11px] font-medium text-ink-muted transition-colors hover:bg-border hover:text-ink"
                >
                    {t('versionHistory.newVersion')}
                </button>
            </div>

            {showForm && (
                <form onSubmit={handleCreate} className="border-b border-border px-4 py-3">
                    <div className="mb-2 flex items-center gap-2">
                        <span className="rounded-full bg-status-final/15 px-1.5 py-0.5 text-[10px] font-medium text-status-final">
                            {t('versionHistory.snapshot')}
                        </span>
                        <span className="text-xs text-ink-muted">{t('versionHistory.newVersionSnapshot')}</span>
                    </div>
                    <input
                        ref={inputRef}
                        type="text"
                        value={summary}
                        onChange={(e) => setSummary(e.target.value)}
                        placeholder={t('versionHistory.summaryPlaceholder')}
                        maxLength={255}
                        className="mb-2 w-full rounded-md border border-border bg-surface px-2.5 py-1.5 text-xs text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none"
                    />
                    <div className="flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => {
                                setShowForm(false);
                                setSummary('');
                            }}
                            className="rounded-md px-2.5 py-1 text-[11px] text-ink-muted hover:text-ink"
                        >
                            {t('versionHistory.cancel')}
                        </button>
                        <button
                            type="submit"
                            disabled={creating}
                            className="rounded-md bg-accent px-2.5 py-1 text-[11px] font-medium text-white transition-colors hover:bg-accent/90 disabled:opacity-50"
                        >
                            {creating ? t('versionHistory.creating') : t('versionHistory.create')}
                        </button>
                    </div>
                </form>
            )}

            <div className="max-h-[340px] overflow-y-auto">
                {versionList === null ? (
                    <div className="px-4 py-6 text-center text-xs text-ink-faint">{t('versionHistory.loading')}</div>
                ) : versionList.length === 0 ? (
                    <div className="px-4 py-6 text-center text-xs text-ink-faint">{t('versionHistory.empty')}</div>
                ) : (
                    <div className="flex flex-col">
                        {versionList.map((version) => (
                            <div
                                key={version.id}
                                className={`flex items-center gap-3 px-4 py-3 ${version.is_current ? 'bg-neutral-bg/50' : ''}`}
                            >
                                <div className="flex min-w-0 flex-1 flex-col gap-1">
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm font-medium text-ink">
                                            v{version.version_number}
                                        </span>
                                        <span
                                            className={`rounded-full px-1.5 py-0.5 text-[10px] font-medium ${sourceBadgeClass[version.source]}`}
                                        >
                                            {sourceLabel(version.source)}
                                        </span>
                                        {version.is_current && (
                                            <span className="text-[10px] font-medium text-status-final">{t('versionHistory.current')}</span>
                                        )}
                                        {version.status === 'pending' && (
                                            <span className="text-[10px] font-medium text-accent">{t('versionHistory.pendingReview')}</span>
                                        )}
                                    </div>
                                    {version.change_summary && (
                                        <span className="truncate text-xs text-ink-faint">
                                            {version.change_summary}
                                        </span>
                                    )}
                                    <span className="text-[11px] text-ink-faint">
                                        {formatDate(version.created_at)}
                                    </span>
                                </div>

                                {!version.is_current && (
                                    <div className="flex shrink-0 gap-1">
                                        <button
                                            type="button"
                                            onClick={() => handleRestore(version)}
                                            disabled={restoring !== null}
                                            className="rounded-md border border-border px-2 py-1 text-[11px] text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink disabled:opacity-50"
                                        >
                                            {restoring === version.id ? t('versionHistory.restoring') : t('versionHistory.restore')}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => handleDelete(version)}
                                            disabled={deleting !== null}
                                            className="rounded-md border border-border p-1 text-ink-faint transition-colors hover:bg-red-50 hover:text-red-500 disabled:opacity-50"
                                            title={t('versionHistory.deleteVersion')}
                                        >
                                            <Trash size={14} />
                                        </button>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
