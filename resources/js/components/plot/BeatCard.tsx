import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical } from 'lucide-react';
import { useMemo } from 'react';
import { stripTags } from '@/lib/ruleCheckers';
import { cn } from '@/lib/utils';
import type { Beat, BeatStatus } from '@/types/models';

const BEAT_DOT_COLORS: Record<BeatStatus, { color: string; opacity?: number }> =
    {
        planned: { color: '#D5D2CC' },
        fulfilled: { color: '#5A8F5C' },
        abandoned: { color: '#D5D2CC', opacity: 0.5 },
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
    const dot = BEAT_DOT_COLORS[beat.status];
    const plainDescription = useMemo(
        () => (beat.description ? stripTags(beat.description).trim() : ''),
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
                'flex cursor-pointer items-start gap-1.5',
                isDragging && 'opacity-50',
                isSelected && 'rounded-md bg-ink/[0.06]',
            )}
            onClick={onClick}
            onContextMenu={onContextMenu}
        >
            <span
                {...listeners}
                className="mt-0.5 flex shrink-0 cursor-grab items-center text-ink-faint active:cursor-grabbing"
            >
                <GripVertical className="h-3 w-3" />
            </span>
            <span
                className="mt-1 shrink-0 rounded-full"
                style={{
                    width: 8,
                    height: 8,
                    backgroundColor: dot.color,
                    opacity: dot.opacity ?? 1,
                }}
            />
            <div className="min-w-0 flex-1">
                <span
                    className={cn(
                        'text-[12px] leading-tight',
                        isSelected
                            ? 'font-medium text-ink'
                            : 'font-normal text-ink-soft',
                    )}
                >
                    {titleOverride ?? beat.title}
                </span>
                {plainDescription && (
                    <p className="line-clamp-2 text-[11px] text-ink-muted italic">
                        {plainDescription}
                    </p>
                )}
            </div>
        </div>
    );
}
