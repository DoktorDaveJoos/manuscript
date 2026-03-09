import {
    acceptVersion,
    rejectVersion,
} from '@/actions/App/Http/Controllers/ChapterController';
import { getXsrfToken } from '@/lib/csrf';
import type { ChapterVersion, VersionSource } from '@/types/models';
import { router } from '@inertiajs/react';
import { diffWords } from 'diff';
import { useCallback, useMemo, useState } from 'react';

const sourceLabel: Record<VersionSource, string> = {
    original: 'original',
    ai_revision: 'ai prose pass',
    manual_edit: 'manual edit',
    normalization: 'normalize',
    beautify: 'beautify',
    snapshot: 'snapshot',
};

function splitParagraphs(html: string | null): string[] {
    if (!html) return [];
    return html
        .replace(/<\/p>\s*<p>/gi, '</p>\n<p>')
        .split(/\n/)
        .map((p) => p.trim())
        .filter(Boolean);
}

function stripTags(html: string): string {
    return html.replace(/<[^>]*>/g, '');
}

type DiffSegment = { text: string; type: 'equal' | 'added' | 'removed' };

function computeDiff(
    originalHtml: string | null,
    revisedHtml: string | null,
): {
    left: { segments: DiffSegment[] }[];
    right: { segments: DiffSegment[] }[];
    changeCount: number;
} {
    const origParagraphs = splitParagraphs(originalHtml);
    const revParagraphs = splitParagraphs(revisedHtml);

    const origText = origParagraphs.map(stripTags).join('\n\n');
    const revText = revParagraphs.map(stripTags).join('\n\n');

    const wordDiffs = diffWords(origText, revText);

    const leftSegments: DiffSegment[] = [];
    const rightSegments: DiffSegment[] = [];
    let changeCount = 0;

    for (const part of wordDiffs) {
        if (part.added) {
            rightSegments.push({ text: part.value, type: 'added' });
            changeCount++;
        } else if (part.removed) {
            leftSegments.push({ text: part.value, type: 'removed' });
        } else {
            leftSegments.push({ text: part.value, type: 'equal' });
            rightSegments.push({ text: part.value, type: 'equal' });
        }
    }

    function splitIntoParagraphs(segments: DiffSegment[]): { segments: DiffSegment[] }[] {
        const paragraphs: { segments: DiffSegment[] }[] = [];
        let current: DiffSegment[] = [];

        for (const seg of segments) {
            const parts = seg.text.split('\n\n');
            for (let i = 0; i < parts.length; i++) {
                if (i > 0) {
                    paragraphs.push({ segments: current });
                    current = [];
                }
                if (parts[i]) {
                    current.push({ text: parts[i], type: seg.type });
                }
            }
        }
        if (current.length > 0) {
            paragraphs.push({ segments: current });
        }
        return paragraphs;
    }

    return {
        left: splitIntoParagraphs(leftSegments),
        right: splitIntoParagraphs(rightSegments),
        changeCount,
    };
}

export default function DiffView({
    bookId,
    chapterId,
    chapterTitle,
    currentVersion,
    pendingVersion,
}: {
    bookId: number;
    chapterId: number;
    chapterTitle: string;
    currentVersion: ChapterVersion;
    pendingVersion: ChapterVersion;
}) {
    const [isAccepting, setIsAccepting] = useState(false);
    const [isRejecting, setIsRejecting] = useState(false);

    const diff = useMemo(
        () => computeDiff(currentVersion.content, pendingVersion.content),
        [currentVersion.content, pendingVersion.content],
    );

    const handleAccept = useCallback(async () => {
        setIsAccepting(true);
        try {
            const response = await fetch(
                acceptVersion.url({ book: bookId, chapter: chapterId, version: pendingVersion.id }),
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                    },
                },
            );
            if (!response.ok) throw new Error('Accept failed');
            router.reload();
        } catch {
            setIsAccepting(false);
        }
    }, [bookId, chapterId, pendingVersion.id]);

    const handleReject = useCallback(async () => {
        setIsRejecting(true);
        try {
            const response = await fetch(
                rejectVersion.url({ book: bookId, chapter: chapterId, version: pendingVersion.id }),
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                    },
                },
            );
            if (!response.ok) throw new Error('Reject failed');
            router.reload();
        } catch {
            setIsRejecting(false);
        }
    }, [bookId, chapterId, pendingVersion.id]);

    return (
        <div className="flex flex-1 flex-col overflow-hidden">
            {/* Review bar */}
            <div className="flex h-12 shrink-0 items-center justify-between border-b border-border px-6">
                <div className="flex items-center gap-2 text-sm">
                    <span className="text-ink-faint">{chapterTitle}</span>
                    <span className="text-ink-faint">/</span>
                    <span className="font-medium text-accent">
                        Reviewing {sourceLabel[pendingVersion.source] ?? pendingVersion.source}
                    </span>
                    <span className="text-ink-faint">
                        {diff.changeCount} {diff.changeCount === 1 ? 'change' : 'changes'}
                    </span>
                </div>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={handleReject}
                        disabled={isRejecting || isAccepting}
                        className="rounded-md border border-border px-3 py-1.5 text-xs font-medium text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink disabled:opacity-50"
                    >
                        {isRejecting ? 'Rejecting...' : 'Reject all'}
                    </button>
                    <button
                        type="button"
                        onClick={handleAccept}
                        disabled={isAccepting || isRejecting}
                        className="rounded-md bg-ink px-3 py-1.5 text-xs font-medium text-surface transition-colors hover:bg-ink/90 disabled:opacity-50"
                    >
                        {isAccepting ? 'Accepting...' : 'Accept revision'}
                    </button>
                </div>
            </div>

            {/* Side-by-side diff */}
            <div className="flex flex-1 overflow-hidden">
                {/* Left: Original */}
                <div className="flex-1 overflow-y-auto border-r border-border px-12 py-8">
                    <div className="mb-6 flex items-center gap-2">
                        <span className="text-xs font-semibold uppercase tracking-wider text-ink-faint">
                            Original
                        </span>
                        <span className="text-xs text-ink-faint">
                            v{currentVersion.version_number} &middot;{' '}
                            {sourceLabel[currentVersion.source] ?? currentVersion.source}
                        </span>
                    </div>
                    <div className="max-w-prose space-y-4 font-serif text-base leading-relaxed text-ink">
                        {diff.left.map((para, i) => (
                            <p key={i}>
                                {para.segments.map((seg, j) =>
                                    seg.type === 'removed' ? (
                                        <span key={j} className="bg-delete-bg text-delete line-through">
                                            {seg.text}
                                        </span>
                                    ) : (
                                        <span key={j}>{seg.text}</span>
                                    ),
                                )}
                            </p>
                        ))}
                    </div>
                </div>

                {/* Right: Revision */}
                <div className="flex-1 overflow-y-auto px-12 py-8">
                    <div className="mb-6 flex items-center gap-2">
                        <span className="text-xs font-semibold uppercase tracking-wider text-ink-faint">
                            Revision
                        </span>
                        <span className="text-xs text-ink-faint">
                            v{pendingVersion.version_number} &middot;{' '}
                            {sourceLabel[pendingVersion.source] ?? pendingVersion.source}
                        </span>
                    </div>
                    <div className="max-w-prose space-y-4 font-serif text-base leading-relaxed text-ink">
                        {diff.right.map((para, i) => (
                            <p key={i}>
                                {para.segments.map((seg, j) =>
                                    seg.type === 'added' ? (
                                        <span key={j} className="bg-ai-green/15 text-ai-green">
                                            {seg.text}
                                        </span>
                                    ) : (
                                        <span key={j}>{seg.text}</span>
                                    ),
                                )}
                            </p>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
