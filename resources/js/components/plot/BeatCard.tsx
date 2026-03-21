import { stripTags } from '@/lib/ruleCheckers';
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
    const plainDescription = beat.description
        ? stripTags(beat.description).trim()
        : '';

    return (
        <div
            className="flex cursor-pointer items-start gap-2"
            onClick={onClick}
            onContextMenu={onContextMenu}
        >
            <span
                className="mt-1 shrink-0 rounded-full"
                style={{
                    width: 6,
                    height: 6,
                    backgroundColor: dot.color,
                    opacity: dot.opacity ?? 1,
                }}
            />
            <div className="min-w-0 flex-1">
                <span
                    className="text-[12px] leading-tight font-normal"
                    style={{ color: isSelected ? '#141414' : '#595959' }}
                >
                    {beat.title}
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
