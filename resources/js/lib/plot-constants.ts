import type { ConnectionType, PlotPointStatus, PlotPointType } from '@/types/models';

export const TYPE_STYLES: Record<PlotPointType, string> = {
    setup: 'bg-[#EBEAF2] text-[#655882]',
    conflict: 'bg-[#F2E8E8] text-[#82585A]',
    turning_point: 'bg-[#F2ECE2] text-[#7D7058]',
    resolution: 'bg-[#E8EDE8] text-[#588258]',
    worldbuilding: 'bg-[#E8ECF2] text-[#586582]',
};

export const TYPE_LABELS: Record<PlotPointType, string> = {
    setup: 'Setup',
    conflict: 'Conflict',
    turning_point: 'Turning point',
    resolution: 'Resolution',
    worldbuilding: 'Worldbuilding',
};

export const TYPE_LABELS_SHORT: Record<PlotPointType, string> = {
    setup: 'Setup',
    conflict: 'Conflict',
    turning_point: 'Turning pt.',
    resolution: 'Resolution',
    worldbuilding: 'World',
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

export const CONNECTION_LABELS: Record<ConnectionType, { incoming: string; outgoing: string }> = {
    causes: { incoming: 'Caused by', outgoing: 'Causes' },
    sets_up: { incoming: 'Set up by', outgoing: 'Sets up' },
    resolves: { incoming: 'Resolved by', outgoing: 'Resolves' },
    contradicts: { incoming: 'Contradicted by', outgoing: 'Contradicts' },
};
