import type {
    BeatStatus,
    CharacterPlotPointRole,
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
    {
        bg: 'var(--color-act-1-bg)',
        border: 'var(--color-act-1-border)',
        label: 'var(--color-act-1-label)',
        track: 'var(--color-act-1-track)',
    },
    {
        bg: 'var(--color-act-2-bg)',
        border: 'var(--color-act-2-border)',
        label: 'var(--color-act-2-label)',
        track: 'var(--color-act-2-track)',
    },
    {
        bg: 'var(--color-act-3-bg)',
        border: 'var(--color-act-3-border)',
        label: 'var(--color-act-3-label)',
        track: 'var(--color-act-3-track)',
    },
    {
        bg: 'var(--color-act-4-bg)',
        border: 'var(--color-act-4-border)',
        label: 'var(--color-act-4-label)',
        track: 'var(--color-act-4-track)',
    },
    {
        bg: 'var(--color-act-5-bg)',
        border: 'var(--color-act-5-border)',
        label: 'var(--color-act-5-label)',
        track: 'var(--color-act-5-track)',
    },
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
        activeClass: 'bg-status-final/15 text-status-final',
    },
    {
        value: 'abandoned',
        labelKey: 'status.abandoned',
        activeClass: 'bg-neutral-300/20 text-ink-muted line-through',
    },
];

export const TYPE_PILL_OPTIONS: {
    value: PlotPointType;
    labelKey: string;
    activeClass: string;
}[] = [
    {
        value: 'setup',
        labelKey: 'type.setup',
        activeClass: 'bg-ink/10 text-ink',
    },
    {
        value: 'conflict',
        labelKey: 'type.conflict',
        activeClass: 'bg-ink/10 text-ink',
    },
    {
        value: 'turning_point',
        labelKey: 'typeShort.turning_point',
        activeClass: 'bg-ink/10 text-ink',
    },
    {
        value: 'resolution',
        labelKey: 'type.resolution',
        activeClass: 'bg-ink/10 text-ink',
    },
    {
        value: 'worldbuilding',
        labelKey: 'typeShort.worldbuilding',
        activeClass: 'bg-ink/10 text-ink',
    },
];

export const ROLE_OPTIONS: CharacterPlotPointRole[] = [
    'key',
    'supporting',
    'mentioned',
];
