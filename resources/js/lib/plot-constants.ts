import type { PlotPointStatus, PlotPointType } from '@/types/models';

export const TYPE_STYLES: Record<PlotPointType, string> = {
    setup: 'bg-[#EBEAF2] text-[#655882]',
    conflict: 'bg-[#F2E8E8] text-[#82585A]',
    turning_point: 'bg-[#F2ECE2] text-[#7D7058]',
    resolution: 'bg-[#E8EDE8] text-[#588258]',
    worldbuilding: 'bg-[#E8ECF2] text-[#586582]',
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

