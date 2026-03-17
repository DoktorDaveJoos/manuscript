import type { DraggableSyntheticListeners } from '@dnd-kit/core';
import { router } from '@inertiajs/react';
import { GripVertical } from 'lucide-react';
import { forwardRef } from 'react';
import { show } from '@/actions/App/Http/Controllers/ChapterController';
import { formatCompactCount } from '@/lib/utils';
import type { Chapter } from '@/types/models';

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
};

const ChapterListItem = forwardRef<HTMLButtonElement, ChapterListItemProps>(
    function ChapterListItem(
        {
            chapter,
            bookId,
            index,
            isActive,
            displayTitle,
            wordCount,
            onBeforeNavigate,
            onContextMenu,
            dragListeners,
            isDragging,
        },
        ref,
    ) {
        const handleClick = async () => {
            if (isActive) return;
            if (onBeforeNavigate) {
                await onBeforeNavigate();
            }
            router.visit(show.url({ book: bookId, chapter: chapter.id }), {
                preserveScroll: true,
            });
        };

        return (
            <button
                ref={ref}
                type="button"
                onClick={handleClick}
                onContextMenu={onContextMenu}
                className={`relative flex w-full items-center gap-2 px-2.5 py-[7px] text-left text-[13px] leading-4 transition-colors ${
                    isDragging ? 'opacity-50' : ''
                } ${isActive ? 'rounded-lg bg-ink font-medium text-surface' : 'rounded-md text-ink-muted hover:bg-ink/5 hover:text-ink'}`}
            >
                {/* Drag handle */}
                <span
                    className={`flex shrink-0 cursor-grab items-center active:cursor-grabbing ${isActive ? 'text-surface/[0.38]' : 'text-ink-faint'}`}
                    {...dragListeners}
                >
                    <GripVertical size={12} />
                </span>
                <span className="min-w-0 flex-1 truncate">
                    {index}. {displayTitle ?? chapter.title}
                </span>
                <span
                    className={`shrink-0 text-[11px] ${isActive ? 'text-surface/50' : 'text-ink-faint'}`}
                >
                    {formatCompactCount(wordCount ?? chapter.word_count)}
                </span>
            </button>
        );
    },
);

export default ChapterListItem;
