import type {
    BeatStatus,
    PlotPointStatus,
    PlotPointType,
} from '@/types/models';

export const TYPE_STYLES: Record<PlotPointType, string> = {
    setup: 'bg-plot-setup-bg text-plot-setup-text',
    conflict: 'bg-plot-conflict-bg text-plot-conflict-text',
    turning_point: 'bg-plot-turning-bg text-plot-turning-text',
    resolution: 'bg-plot-resolution-bg text-plot-resolution-text',
    worldbuilding: 'bg-plot-worldbuilding-bg text-plot-worldbuilding-text',
};

export const STATUS_COLORS: Record<PlotPointStatus, string> = {
    planned: 'var(--color-accent)',
    fulfilled: 'var(--color-status-final)',
    abandoned: 'var(--color-ink-faint)',
};

export const NEXT_STATUS: Record<PlotPointStatus, PlotPointStatus> = {
    planned: 'fulfilled',
    fulfilled: 'abandoned',
    abandoned: 'planned',
};

export const ACT_COLORS = [
    { bg: '#FAF3EB', border: '#E8D5BE', label: '#C49A6C', track: '#EDE5DA' },
    { bg: '#F8EDE2', border: '#D4B89A', label: '#B87333', track: '#EDE0D2' },
    { bg: '#F3ECE4', border: '#C4B8A8', label: '#8B6F47', track: '#E2DCD4' },
    { bg: '#F0E8DF', border: '#C8B8A4', label: '#8B6F47', track: '#E5DDD4' },
    { bg: '#EBE4DB', border: '#BAA996', label: '#6B5A40', track: '#DDD6CC' },
] as const;

export function getActColor(index: number) {
    return ACT_COLORS[index % ACT_COLORS.length];
}

export const STATUS_PILL_OPTIONS: {
    value: BeatStatus;
    labelKey: string;
    activeClass: string;
}[] = [
    {
        value: 'planned',
        labelKey: 'status.planned',
        activeClass: 'bg-neutral-300/30 text-ink',
    },
    {
        value: 'fulfilled',
        labelKey: 'status.fulfilled',
        activeClass: 'bg-[#5A8F5C]/15 text-[#5A8F5C]',
    },
    {
        value: 'abandoned',
        labelKey: 'status.abandoned',
        activeClass: 'bg-neutral-300/20 text-ink-muted line-through',
    },
];
