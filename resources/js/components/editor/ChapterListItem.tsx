import { show } from '@/actions/App/Http/Controllers/ChapterController';
import { formatCompactCount } from '@/lib/utils';
import type { Chapter, ChapterStatus } from '@/types/models';
import { router } from '@inertiajs/react';
import type { DraggableSyntheticListeners } from '@dnd-kit/core';
import { forwardRef } from 'react';

export const statusDot: Record<ChapterStatus, string> = {
    draft: 'bg-status-draft',
    revised: 'bg-status-revised',
    final: 'bg-status-final',
};

type ChapterListItemProps = {
    chapter: Chapter;
    bookId: number;
    index: number;
    isActive: boolean;
    onBeforeNavigate?: () => Promise<void>;
    onContextMenu?: (e: React.MouseEvent) => void;
    dragListeners?: DraggableSyntheticListeners;
    isDragging?: boolean;
    hasMultipleScenes?: boolean;
    isExpanded?: boolean;
    onToggleExpand?: () => void;
};

const ChapterListItem = forwardRef<HTMLButtonElement, ChapterListItemProps>(function ChapterListItem(
    { chapter, bookId, index, isActive, onBeforeNavigate, onContextMenu, dragListeners, isDragging, hasMultipleScenes, isExpanded, onToggleExpand },
    ref,
) {
    const handleClick = async () => {
        if (isActive) return;
        if (onBeforeNavigate) {
            await onBeforeNavigate();
        }
        router.visit(show.url({ book: bookId, chapter: chapter.id }));
    };

    return (
        <button
            ref={ref}
            type="button"
            onClick={handleClick}
            onContextMenu={onContextMenu}
            className={`group flex w-full items-center gap-2 rounded-[5px] px-2.5 py-1.5 text-left text-[13px] leading-4 transition-colors ${
                isDragging ? 'opacity-50' : ''
            } ${isActive ? 'bg-ink text-surface' : 'text-ink-soft hover:bg-ink/5 hover:text-ink'}`}
        >
            {hasMultipleScenes && (
                <span
                    role="button"
                    tabIndex={-1}
                    onClick={(e) => {
                        e.stopPropagation();
                        onToggleExpand?.();
                    }}
                    className={`flex shrink-0 items-center transition-transform ${isExpanded ? 'rotate-90' : ''} ${isActive ? 'text-white/50' : 'text-ink-faint'}`}
                >
                    <svg width="8" height="8" viewBox="0 0 8 8" fill="currentColor">
                        <path d="M2 1l4 3-4 3V1z" />
                    </svg>
                </span>
            )}
            <span
                {...dragListeners}
                className={`flex shrink-0 cursor-grab items-center text-ink-faint opacity-0 transition-opacity active:cursor-grabbing group-hover:opacity-100 ${
                    isActive ? 'text-white/40' : ''
                }`}
            >
                <svg width="6" height="10" viewBox="0 0 6 10" fill="currentColor">
                    <circle cx="1" cy="1" r="1" />
                    <circle cx="5" cy="1" r="1" />
                    <circle cx="1" cy="5" r="1" />
                    <circle cx="5" cy="5" r="1" />
                    <circle cx="1" cy="9" r="1" />
                    <circle cx="5" cy="9" r="1" />
                </svg>
            </span>
            <span className={`inline-block size-[7px] shrink-0 rounded-full ${statusDot[chapter.status]}`} />
            <span className="min-w-0 flex-1 truncate">
                <span className={isActive ? 'text-white/70' : 'text-ink-faint'}>{index}.</span> {chapter.title}
            </span>
            <span className={`shrink-0 text-[11px] ${isActive ? 'text-white/50' : 'text-ink-faint'}`}>
                {formatCompactCount(chapter.word_count)}
            </span>
            {isActive && (
                <span
                    role="button"
                    tabIndex={-1}
                    onClick={(e) => {
                        e.stopPropagation();
                        onContextMenu?.(e);
                    }}
                    className="shrink-0 text-white/50 hover:text-white/70"
                >
                    <svg width="15" height="15" viewBox="0 0 15 15" fill="currentColor">
                        <circle cx="3.5" cy="7.5" r="1.5" />
                        <circle cx="7.5" cy="7.5" r="1.5" />
                        <circle cx="11.5" cy="7.5" r="1.5" />
                    </svg>
                </span>
            )}
        </button>
    );
});

export default ChapterListItem;
