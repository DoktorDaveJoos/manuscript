import { useDroppable } from '@dnd-kit/core';
import {
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical, Plus } from 'lucide-react';
import { useCallback, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { stripTags } from '@/lib/ruleCheckers';
import { cn } from '@/lib/utils';
import type { Beat, PlotPoint, PlotPointType } from '@/types/models';
import BeatCard from './BeatCard';

const TYPE_BADGE_COLORS: Record<PlotPointType, { bg: string; text: string }> = {
    setup: { bg: '#E8EEF4', text: '#5B7B9A' },
    conflict: { bg: '#F4E8E8', text: '#9A5B5B' },
    turning_point: { bg: '#FAF0E4', text: '#B87333' },
    resolution: { bg: '#E8EDE8', text: '#588258' },
    worldbuilding: { bg: '#E8ECF2', text: '#586582' },
};

type Props = {
    plotPoint: PlotPoint & { beats?: Beat[] };
    selectedBeatId: number | null;
    isSelected?: boolean;
    titleOverrides?: Record<string, string>;
    onSelectBeat: (beat: Beat) => void;
    onSelectPlotPoint: (plotPoint: PlotPoint) => void;
    onCreateBeat: (plotPointId: number) => void;
    onBeatContextMenu: (beat: Beat, position: { x: number; y: number }) => void;
    onPlotPointContextMenu: (
        plotPoint: PlotPoint,
        position: { x: number; y: number },
    ) => void;
};

export default function PlotPointSection({
    plotPoint,
    selectedBeatId,
    isSelected = false,
    titleOverrides,
    onSelectBeat,
    onSelectPlotPoint,
    onCreateBeat,
    onBeatContextMenu,
    onPlotPointContextMenu,
}: Props) {
    const { t } = useTranslation('plot');

    const beats = plotPoint.beats ?? [];
    const plainDescription = useMemo(
        () =>
            plotPoint.description
                ? stripTags(plotPoint.description).trim()
                : '',
        [plotPoint.description],
    );

    const {
        attributes,
        listeners,
        setNodeRef: setSortableRef,
        transform,
        transition,
        isDragging,
    } = useSortable({
        id: `plotpoint-${plotPoint.id}`,
        data: { type: 'plotpoint', plotPoint },
    });

    const { setNodeRef: setDroppableRef, isOver } = useDroppable({
        id: `plotpoint-drop-${plotPoint.id}`,
        data: { type: 'plotpoint-drop', plotPointId: plotPoint.id },
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    const beatIds = useMemo(() => beats.map((b) => `beat-${b.id}`), [beats]);

    const combinedRef = useCallback(
        (node: HTMLDivElement | null) => {
            setSortableRef(node);
            setDroppableRef(node);
        },
        [setSortableRef, setDroppableRef],
    );

    return (
        <div
            ref={combinedRef}
            style={style}
            {...attributes}
            className={cn(
                'flex flex-col overflow-hidden rounded-lg border border-[#E8E6E1]/60 bg-white pt-3 pb-2.5 shadow-[0_1px_4px_rgba(0,0,0,0.06)] transition-colors',
                isDragging && 'opacity-50',
                isSelected && 'ring-2 ring-[#C49A6C]',
                isOver && 'bg-[#F7F5F0]',
            )}
        >
            {/* Header */}
            <div
                className="flex items-center justify-between gap-2 px-3.5"
                onContextMenu={(e) => {
                    e.preventDefault();
                    onPlotPointContextMenu(plotPoint, {
                        x: e.clientX,
                        y: e.clientY,
                    });
                }}
            >
                <div className="flex min-w-0 flex-1 items-center gap-1.5">
                    <span
                        {...listeners}
                        className="flex shrink-0 cursor-grab items-center text-ink-faint active:cursor-grabbing"
                    >
                        <GripVertical className="h-3.5 w-3.5" />
                    </span>
                    <button
                        type="button"
                        onClick={() => onSelectPlotPoint(plotPoint)}
                        className="min-w-0 flex-1 text-left text-[13px] leading-tight font-semibold transition-opacity hover:opacity-70"
                        style={{ color: '#141414' }}
                    >
                        {titleOverrides?.[`plotpoint-${plotPoint.id}`] ??
                            plotPoint.title}
                    </button>
                </div>
                <span
                    className="shrink-0 rounded px-2 py-0.5 text-[10px] font-medium"
                    style={{
                        backgroundColor:
                            TYPE_BADGE_COLORS[plotPoint.type]?.bg ?? '#F0EEEB',
                        borderRadius: 4,
                        color:
                            TYPE_BADGE_COLORS[plotPoint.type]?.text ??
                            '#737373',
                    }}
                >
                    {t(`type.${plotPoint.type}`)}
                </span>
            </div>

            {/* Divider */}
            <div className="mt-2.5 h-px w-full bg-[#E8E6E1]" />

            {/* Description preview */}
            {plainDescription && (
                <p className="line-clamp-2 px-3.5 pt-2.5 text-[11px] text-ink-muted italic">
                    {plainDescription}
                </p>
            )}

            {/* Beats list */}
            <SortableContext
                items={beatIds}
                strategy={verticalListSortingStrategy}
            >
                {beats.length > 0 && (
                    <div className="flex flex-col gap-2 px-3.5 pt-2.5">
                        {beats.map((beat) => (
                            <BeatCard
                                key={beat.id}
                                beat={beat}
                                isSelected={selectedBeatId === beat.id}
                                titleOverride={
                                    titleOverrides?.[`beat-${beat.id}`]
                                }
                                onClick={() => onSelectBeat(beat)}
                                onContextMenu={(e) => {
                                    e.preventDefault();
                                    onBeatContextMenu(beat, {
                                        x: e.clientX,
                                        y: e.clientY,
                                    });
                                }}
                            />
                        ))}
                    </div>
                )}
            </SortableContext>

            {/* Empty drop zone when no beats */}
            {beats.length === 0 && isOver && (
                <div
                    className="mx-3.5 mt-2.5 rounded border border-dashed py-3 text-center text-[11px] text-ink-faint"
                    style={{ borderColor: '#C49A6C' }}
                >
                    {t('beat.dropHere', 'Drop here')}
                </div>
            )}

            {/* Add beat button */}
            <button
                type="button"
                onClick={() => onCreateBeat(plotPoint.id)}
                className="flex items-center gap-1 px-3.5 pt-2 transition-opacity hover:opacity-70"
            >
                <Plus size={12} style={{ color: '#A3A3A3' }} />
                <span
                    className="text-[11px] font-normal"
                    style={{ color: '#A3A3A3' }}
                >
                    {t('beat.addBeat')}
                </span>
            </button>
        </div>
    );
}
