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

    return (
        <div
            className={cn(
                'flex cursor-pointer items-center gap-2 rounded-md px-2 py-1 transition-colors',
                isSelected
                    ? 'bg-ink/[0.06] font-medium'
                    : 'hover:bg-ink/[0.03]',
            )}
            onClick={onClick}
            onContextMenu={onContextMenu}
        >
            <span
                className="shrink-0 rounded-full"
                style={{
                    width: 8,
                    height: 8,
                    backgroundColor: dot.color,
                    opacity: dot.opacity ?? 1,
                }}
            />
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
        </div>
    );
}
