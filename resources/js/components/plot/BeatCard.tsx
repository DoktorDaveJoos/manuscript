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

    return (
        <div
            className="flex cursor-pointer items-center gap-2"
            onClick={onClick}
            onContextMenu={onContextMenu}
        >
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
