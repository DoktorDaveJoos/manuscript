import { restoreVersion, versions } from '@/actions/App/Http/Controllers/ChapterController';
import type { ChapterVersion, VersionSource } from '@/types/models';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

const sourceLabel: Record<VersionSource, string> = {
    original: 'Original',
    ai_revision: 'AI',
    manual_edit: 'Edit',
    normalization: 'Normalize',
    beautify: 'Beautify',
};

const sourceBadgeClass: Record<VersionSource, string> = {
    original: 'bg-neutral-bg text-ink-muted',
    ai_revision: 'bg-status-revised/15 text-status-revised',
    manual_edit: 'bg-status-final/15 text-status-final',
    normalization: 'bg-neutral-bg text-ink-muted',
    beautify: 'bg-status-revised/15 text-status-revised',
};

function formatDate(dateStr: string): string {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

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
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        fetch(versions.url({ book: bookId, chapter: chapterId }), {
            headers: { Accept: 'application/json' },
        })
            .then((r) => r.json())
            .then(setVersionList);
    }, [bookId, chapterId]);

    useEffect(() => {
        function handleClickOutside(e: MouseEvent) {
            if (ref.current && !ref.current.contains(e.target as Node)) {
                onClose();
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [onClose]);

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

    return (
        <div
            ref={ref}
            className="absolute right-0 top-full z-50 mt-1 w-[360px] rounded-lg border border-border bg-surface-card shadow-lg"
        >
            <div className="border-b border-border px-4 py-3">
                <span className="text-sm font-medium text-ink">Version History</span>
            </div>

            <div className="max-h-[340px] overflow-y-auto">
                {versionList === null ? (
                    <div className="px-4 py-6 text-center text-xs text-ink-faint">Loading...</div>
                ) : versionList.length === 0 ? (
                    <div className="px-4 py-6 text-center text-xs text-ink-faint">No versions yet</div>
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
                                            {sourceLabel[version.source]}
                                        </span>
                                        {version.is_current && (
                                            <span className="text-[10px] font-medium text-status-final">Current</span>
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
                                            {restoring === version.id ? 'Restoring...' : 'Restore'}
                                        </button>
                                        <button
                                            type="button"
                                            disabled
                                            className="rounded-md border border-border px-2 py-1 text-[11px] text-ink-faint"
                                            title="Coming in Phase 2"
                                        >
                                            Compare
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
