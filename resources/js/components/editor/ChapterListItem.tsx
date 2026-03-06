import { show } from '@/actions/App/Http/Controllers/ChapterController';
import type { Chapter, ChapterStatus } from '@/types/models';
import { router } from '@inertiajs/react';

const statusDot: Record<ChapterStatus, string> = {
    draft: 'bg-status-draft',
    revised: 'bg-status-revised',
    final: 'bg-status-final',
};

function formatCompactCount(count: number): string {
    if (count >= 1000) {
        return `${(count / 1000).toFixed(1).replace(/\.0$/, '')}k`;
    }
    return count.toString();
}

export default function ChapterListItem({
    chapter,
    bookId,
    index,
    isActive,
    onBeforeNavigate,
}: {
    chapter: Chapter;
    bookId: number;
    index: number;
    isActive: boolean;
    onBeforeNavigate?: () => Promise<void>;
}) {
    const handleClick = async () => {
        if (isActive) return;
        if (onBeforeNavigate) {
            await onBeforeNavigate();
        }
        router.visit(show.url({ book: bookId, chapter: chapter.id }));
    };

    return (
        <button
            type="button"
            onClick={handleClick}
            className={`flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm transition-colors ${
                isActive ? 'bg-neutral-bg text-ink' : 'text-ink-muted hover:bg-neutral-bg/50 hover:text-ink'
            }`}
        >
            <span className={`inline-block size-1.5 shrink-0 rounded-full ${statusDot[chapter.status]}`} />
            <span className="min-w-0 flex-1 truncate">
                <span className="text-ink-faint">{index}.</span> {chapter.title}
            </span>
            <span className="shrink-0 text-xs text-ink-faint">{formatCompactCount(chapter.word_count)}</span>
        </button>
    );
}
