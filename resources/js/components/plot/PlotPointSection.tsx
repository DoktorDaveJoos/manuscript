import { useDroppable } from '@dnd-kit/core';
import {
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical, Plus } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { stripTags } from '@/lib/ruleCheckers';
import { cn } from '@/lib/utils';
import type { Beat, PlotPoint } from '@/types/models';
import BeatCard from './BeatCard';

type Props = {
    plotPoint: PlotPoint & { beats?: Beat[] };
    selectedBeatId: number | null;
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
    onSelectBeat,
    onSelectPlotPoint,
    onCreateBeat,
    onBeatContextMenu,
    onPlotPointContextMenu,
}: Props) {
    const { t } = useTranslation('plot');

    const beats = plotPoint.beats ?? [];
    const plainDescription = plotPoint.description
        ? stripTags(plotPoint.description).trim()
        : '';

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

    return (
        <div
            ref={(node) => {
                setSortableRef(node);
                setDroppableRef(node);
            }}
            style={{
                ...style,
                backgroundColor: isOver ? '#F7F5F0' : '#FCFAF7',
                borderColor: isOver ? '#C49A6C' : '#E4E2DD',
                borderRadius: 8,
            }}
            {...attributes}
            className={cn(
                'flex flex-col gap-2.5 rounded-lg border p-3.5 transition-colors',
                isDragging && 'opacity-50',
            )}
        >
            {/* Header */}
            <div
                className="flex items-center justify-between gap-2"
                onContextMenu={(e) => {
                    e.preventDefault();
                    onPlotPointContextMenu(plotPoint, {
                        x: e.clientX,
                        y: e.clientY,
                    });
                }}
            >
                <div className="flex items-center gap-1.5">
                    <span
                        {...listeners}
                        className="flex shrink-0 cursor-grab items-center text-ink-faint active:cursor-grabbing"
                    >
                        <GripVertical className="h-3.5 w-3.5" />
                    </span>
                    <button
                        type="button"
                        onClick={() => onSelectPlotPoint(plotPoint)}
                        className="text-left text-[13px] leading-tight font-semibold transition-opacity hover:opacity-70"
                        style={{ color: '#141414' }}
                    >
                        {plotPoint.title}
                    </button>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <span
                        className="rounded px-2 py-0.5 text-[10px] font-medium"
                        style={{
                            backgroundColor: '#F0EEEB',
                            borderRadius: 4,
                            color: '#737373',
                        }}
                    >
                        {t(`type.${plotPoint.type}`)}
                    </span>
                </div>
            </div>

            {/* Description preview */}
            {plainDescription && (
                <p className="line-clamp-2 text-[11px] text-ink-muted italic">
                    {plainDescription}
                </p>
            )}

            {/* Beats list */}
            <SortableContext
                items={beatIds}
                strategy={verticalListSortingStrategy}
            >
                {beats.length > 0 && (
                    <div className="flex flex-col gap-2 pl-1">
                        {beats.map((beat) => (
                            <BeatCard
                                key={beat.id}
                                beat={beat}
                                isSelected={selectedBeatId === beat.id}
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
                    className="rounded border border-dashed py-3 text-center text-[11px] text-ink-faint"
                    style={{ borderColor: '#C49A6C' }}
                >
                    {t('beat.dropHere', 'Drop here')}
                </div>
            )}

            {/* Add beat button */}
            <button
                type="button"
                onClick={() => onCreateBeat(plotPoint.id)}
                className="flex items-center gap-1 pt-1 transition-opacity hover:opacity-70"
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
