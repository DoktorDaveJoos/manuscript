import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical } from 'lucide-react';
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
    onClick: () => void;
    onContextMenu: (e: React.MouseEvent) => void;
};

export default function BeatCard({
    beat,
    isSelected,
    onClick,
    onContextMenu,
}: Props) {
    const dot = BEAT_DOT_COLORS[beat.status];

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
                'flex cursor-pointer items-center gap-1.5',
                isDragging && 'opacity-50',
            )}
            onClick={onClick}
            onContextMenu={onContextMenu}
        >
            <span
                {...listeners}
                className="flex shrink-0 cursor-grab items-center text-ink-faint active:cursor-grabbing"
            >
                <GripVertical className="h-3 w-3" />
            </span>
            <span
                className="shrink-0 rounded-full"
                style={{
                    width: 6,
                    height: 6,
                    backgroundColor: dot.color,
                    opacity: dot.opacity ?? 1,
                }}
            />
            <span
                className="text-[12px] leading-tight font-normal"
                style={{ color: isSelected ? '#141414' : '#595959' }}
            >
                {beat.title}
            </span>
        </div>
    );
}
