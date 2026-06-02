import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical } from 'lucide-react';
import { useMemo } from 'react';
import { markdownToPlainText } from '@/lib/markdown';
import { cn } from '@/lib/utils';
import type { Beat, BeatStatus } from '@/types/models';

const BEAT_DOT_CLASSES: Record<BeatStatus, string> = {
    planned: 'bg-border-dashed',
    fulfilled: 'bg-status-final',
    abandoned: 'bg-border-dashed opacity-50',
};

type Props = {
    beat: Beat;
    isSelected: boolean;
    titleOverride?: string;
    onClick: () => void;
    onContextMenu: (e: React.MouseEvent) => void;
};

export default function BeatCard({
    beat,
    isSelected,
    titleOverride,
    onClick,
    onContextMenu,
}: Props) {
    const dotClass = BEAT_DOT_CLASSES[beat.status];
    const plainDescription = useMemo(
        () => markdownToPlainText(beat.description ?? ''),
        [beat.description],
    );

    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({
        id: `beat-${beat.id}`,
        data: { type: 'beat', beat },
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            {...attributes}
            className={cn(
                'flex cursor-pointer flex-col',
                isDragging && 'opacity-50',
                isSelected && 'rounded-md bg-ink/[0.06]',
            )}
            onClick={onClick}
            onContextMenu={onContextMenu}
        >
            <div className="flex items-center gap-1.5">
                <span
                    {...listeners}
                    className="flex shrink-0 cursor-grab items-center text-ink-faint active:cursor-grabbing"
                >
                    <GripVertical className="h-3 w-3" />
                </span>
                <span
                    className={cn('h-2 w-2 shrink-0 rounded-full', dotClass)}
                />
                <span
                    className={cn(
                        'min-w-0 flex-1 text-[12px] leading-tight',
                        isSelected
                            ? 'font-medium text-ink'
                            : 'font-normal text-ink-soft',
                    )}
                >
                    {titleOverride ?? beat.title}
                </span>
            </div>
            {plainDescription && (
                <p className="line-clamp-2 pl-[32px] text-[11px] text-ink-muted italic">
                    {plainDescription}
                </p>
            )}
        </div>
    );
}
