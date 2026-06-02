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
import { Card } from '@/components/ui/Card';
import { markdownToPlainText } from '@/lib/markdown';
import { TYPE_STYLES } from '@/lib/plot-constants';
import { cn } from '@/lib/utils';
import type { Beat, PlotPoint } from '@/types/models';
import BeatCard from './BeatCard';

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
        () => markdownToPlainText(plotPoint.description ?? ''),
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
        <Card
            ref={combinedRef}
            style={style}
            {...attributes}
            className={cn(
                'flex flex-col overflow-hidden pt-3 pb-2.5 transition-colors',
                isDragging && 'opacity-50',
                isSelected && 'ring-2 ring-accent',
                isOver && 'bg-surface-warm',
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
                        className="min-w-0 flex-1 text-left text-[13px] leading-tight font-semibold text-ink transition-opacity hover:opacity-70"
                    >
                        {titleOverrides?.[`plotpoint-${plotPoint.id}`] ??
                            plotPoint.title}
                    </button>
                </div>
                {plotPoint.type && (
                    <span
                        className={cn(
                            'shrink-0 rounded px-2 py-0.5 text-[11px] font-medium',
                            TYPE_STYLES[plotPoint.type] ??
                                'bg-neutral-bg text-ink-muted',
                        )}
                    >
                        {t(`type.${plotPoint.type}`)}
                    </span>
                )}
            </div>

            {/* Divider */}
            <div className="mt-2.5 h-px w-full bg-border" />

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
                <div className="mx-3.5 mt-2.5 rounded border border-dashed border-accent py-3 text-center text-[11px] text-ink-faint">
                    {t('beat.dropHere', 'Drop here')}
                </div>
            )}

            {/* Add beat button */}
            <button
                type="button"
                onClick={() => onCreateBeat(plotPoint.id)}
                className="flex items-center gap-1 px-3.5 pt-2 transition-opacity hover:opacity-70"
            >
                <Plus size={12} className="text-ink-faint" />
                <span className="text-[11px] font-normal text-ink-faint">
                    {t('beat.addBeat')}
                </span>
            </button>
        </Card>
    );
}
