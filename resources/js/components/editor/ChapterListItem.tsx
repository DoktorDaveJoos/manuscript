import { show } from '@/actions/App/Http/Controllers/ChapterController';
import { formatCompactCount } from '@/lib/utils';
import type { Chapter, ChapterStatus } from '@/types/models';
import type { DraggableSyntheticListeners } from '@dnd-kit/core';
import { router } from '@inertiajs/react';
import { CaretRight, Circle, DotsSixVertical } from '@phosphor-icons/react';
import { forwardRef } from 'react';

export const statusDot: Record<ChapterStatus, string> = {
    draft: 'bg-status-draft',
    revised: 'bg-status-revised',
    final: 'bg-status-final',
};

export const statusDotColor: Record<ChapterStatus, string> = {
    draft: 'text-status-draft',
    revised: 'text-status-revised',
    final: 'text-status-final',
};

type ChapterListItemProps = {
    chapter: Chapter;
    bookId: number;
    index: number;
    isActive: boolean;
    displayTitle?: string;
    wordCount?: number;
    onBeforeNavigate?: () => Promise<void>;
    onContextMenu?: (e: React.MouseEvent) => void;
    dragListeners?: DraggableSyntheticListeners;
    isDragging?: boolean;
    isExpanded?: boolean;
    onToggleExpand?: () => void;
    scenesVisible?: boolean;
};

const ChapterListItem = forwardRef<HTMLButtonElement, ChapterListItemProps>(function ChapterListItem(
    { chapter, bookId, index, isActive, displayTitle, wordCount, onBeforeNavigate, onContextMenu, dragListeners, isDragging, isExpanded, onToggleExpand, scenesVisible },
    ref,
) {
    const handleClick = async () => {
        if (isActive) return;
        if (onBeforeNavigate) {
            await onBeforeNavigate();
        }
        router.visit(show.url({ book: bookId, chapter: chapter.id }));
    };

    const paddingClass = scenesVisible === false
        ? (isActive ? 'pl-[22px] pr-2' : 'pl-[22px] pr-2.5')
        : (isActive ? 'px-2' : 'px-2.5');

    return (
        <button
            ref={ref}
            type="button"
            onClick={handleClick}
            onContextMenu={onContextMenu}
            className={`group relative flex w-full items-center gap-1.5 rounded-[5px] ${paddingClass} py-1.5 text-left text-[13px] leading-4 transition-colors ${
                isDragging ? 'opacity-50' : ''
            } ${isActive ? 'bg-ink text-surface' : 'text-ink-soft hover:bg-ink/5 hover:text-ink'}`}
        >
            {/* Chevron + Status Dot / Drag handle area */}
            <span className="relative flex shrink-0 items-center" {...dragListeners}>
                <span className="flex items-center gap-1.5 transition-opacity group-hover:opacity-0">
                    {scenesVisible !== false && (
                        <span
                            role="button"
                            tabIndex={-1}
                            onClick={(e) => {
                                e.stopPropagation();
                                onToggleExpand?.();
                            }}
                            className={`flex items-center transition-transform duration-200 ${isExpanded ? 'rotate-90' : ''} ${isActive ? 'text-white/70' : 'text-ink-faint'}`}
                        >
                            <CaretRight size={8} weight="bold" />
                        </span>
                    )}
                    <Circle size={6} weight="fill" className={`shrink-0 ${statusDotColor[chapter.status]}`} />
                </span>
                <span
                    className={`pointer-events-none absolute inset-0 flex cursor-grab items-center justify-center opacity-0 transition-opacity active:cursor-grabbing group-hover:opacity-100 ${
                        isActive ? 'text-white/40' : 'text-ink-faint'
                    }`}
                >
                    <DotsSixVertical size={12} weight="regular" />
                </span>
            </span>
            <span className="min-w-0 flex-1 truncate">
                {index}. {displayTitle ?? chapter.title}
            </span>
            <span className={`shrink-0 text-[11px] ${isActive ? 'text-white/50' : 'text-ink-faint'}`}>
                {formatCompactCount(wordCount ?? chapter.word_count)}
            </span>
        </button>
    );
});

export default ChapterListItem;
