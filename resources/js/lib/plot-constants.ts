import type { ConnectionType, PlotPointStatus, PlotPointType } from '@/types/models';

export const TYPE_STYLES: Record<PlotPointType, string> = {
    setup: 'bg-[#EDE8F5] text-[#6B5A8E]',
    conflict: 'bg-[#F5E8E8] text-[#8E5A5A]',
    turning_point: 'bg-[#F5EDE0] text-[#8A7A5A]',
    resolution: 'bg-[#E8F0E8] text-[#5A8E5A]',
    worldbuilding: 'bg-[#E8EDF5] text-[#5A6B8E]',
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
    planned: '#D4A843',
    fulfilled: '#6DBB7B',
    abandoned: '#B0A99F',
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
