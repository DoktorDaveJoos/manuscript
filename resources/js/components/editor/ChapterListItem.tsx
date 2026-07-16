import type {
    DraggableAttributes,
    DraggableSyntheticListeners,
} from '@dnd-kit/core';
import { router } from '@inertiajs/react';
import { GripVertical } from 'lucide-react';
import { forwardRef } from 'react';
import { useTranslation } from 'react-i18next';
import { show } from '@/actions/App/Http/Controllers/ChapterController';
import Button from '@/components/ui/Button';
import { formatWordCount } from '@/lib/utils';
import type { Chapter } from '@/types/models';
import StatusDot from './StatusDot';

type ChapterListItemProps = {
    chapter: Chapter;
    bookId: number;
    index: number;
    isActive: boolean;
    displayTitle?: string;
    wordCount?: number;
    showStatusBubble?: boolean;
    showWordCount?: boolean;
    compactWordCount?: boolean;
    onBeforeNavigate?: () => Promise<void>;
    onChapterNavigate?: (chapterId: number) => void;
    onOpenInNewPane?: (chapterId: number) => void;
    onContextMenu?: (e: React.MouseEvent) => void;
    dragAttributes?: DraggableAttributes;
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
            showStatusBubble = true,
            showWordCount = true,
            compactWordCount = true,
            onBeforeNavigate,
            onChapterNavigate,
            onOpenInNewPane,
            onContextMenu,
            dragAttributes,
            dragListeners,
            isDragging,
        },
        ref,
    ) {
        const { t } = useTranslation('editor');
        const handleClick = async (e: React.MouseEvent) => {
            // Cmd+click opens in new pane
            if ((e.metaKey || e.ctrlKey) && onOpenInNewPane) {
                onOpenInNewPane(chapter.id);
                return;
            }

            if (isActive && !onOpenInNewPane) return;

            // If chapter is already active, Cmd+click wasn't handled above,
            // just return
            if (isActive) return;

            if (onBeforeNavigate) {
                await onBeforeNavigate();
            }

            if (onChapterNavigate) {
                onChapterNavigate(chapter.id);
            } else {
                router.visit(show.url({ book: bookId, chapter: chapter.id }), {
                    preserveScroll: true,
                });
            }
        };

        return (
            <div
                onContextMenu={onContextMenu}
                className={`relative flex w-full items-center px-1.5 text-left text-[13px] leading-4 transition-colors ${
                    isDragging ? 'opacity-50' : ''
                } ${isActive ? 'rounded-lg bg-ink font-medium text-surface' : 'rounded-md text-ink-muted hover:bg-ink/5 hover:text-ink'}`}
            >
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={t('drag.chapter')}
                    className={`size-6 shrink-0 cursor-grab bg-transparent p-0 hover:bg-transparent active:cursor-grabbing ${isActive ? 'text-surface/[0.38] hover:text-surface' : 'text-ink-faint'}`}
                    {...dragAttributes}
                    {...dragListeners}
                >
                    <GripVertical size={12} />
                </Button>
                <Button
                    ref={ref}
                    type="button"
                    variant="ghost"
                    data-sidebar-chapter={chapter.id}
                    onClick={handleClick}
                    className={`h-auto min-w-0 flex-1 justify-start gap-2 bg-transparent px-1 py-[7px] text-left text-[13px] leading-4 hover:bg-transparent ${isActive ? 'text-surface hover:text-surface' : 'text-ink-muted hover:text-ink'}`}
                >
                    {showStatusBubble && <StatusDot status={chapter.status} />}
                    <span className="min-w-0 flex-1 truncate">
                        {index}. {displayTitle ?? chapter.title}
                    </span>
                    {showWordCount && (
                        <span
                            data-testid="chapter-word-count"
                            className={`shrink-0 text-[11px] ${isActive ? 'text-surface/50' : 'text-ink-faint'}`}
                        >
                            {formatWordCount(
                                wordCount ?? chapter.word_count,
                                compactWordCount,
                            )}
                        </span>
                    )}
                </Button>
            </div>
        );
    },
);

export default ChapterListItem;
