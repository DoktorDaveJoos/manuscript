import { useDroppable } from '@dnd-kit/core';
import {
    SortableContext,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { GripVertical, Plus } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { getActColor } from '@/lib/plot-constants';
import { stripTags } from '@/lib/ruleCheckers';
import type { Act, Beat, PlotPoint } from '@/types/models';
import PlotPointSection from './PlotPointSection';

type Props = {
    act: Act;
    colorIndex: number;
    plotPoints: (PlotPoint & { beats?: Beat[] })[];
    selectedBeatId: number | null;
    selectedPlotPointId?: number | null;
    titleOverrides?: Record<string, string>;
    onSelectBeat: (beat: Beat) => void;
    onSelectPlotPoint: (plotPoint: PlotPoint) => void;
    onCreateBeat: (plotPointId: number) => void;
    onCreatePlotPoint: (actId: number) => void;
    onBeatContextMenu: (beat: Beat, position: { x: number; y: number }) => void;
    onActContextMenu: (act: Act, position: { x: number; y: number }) => void;
    onPlotPointContextMenu: (
        plotPoint: PlotPoint,
        position: { x: number; y: number },
    ) => void;
    onSelectAct?: (actId: number) => void;
};

export default function ActColumn({
    act,
    colorIndex,
    plotPoints,
    selectedBeatId,
    selectedPlotPointId,
    titleOverrides,
    onSelectBeat,
    onSelectPlotPoint,
    onSelectAct,
    onCreateBeat,
    onCreatePlotPoint,
    onBeatContextMenu,
    onActContextMenu,
    onPlotPointContextMenu,
}: Props) {
    const { t } = useTranslation('plot');
    const color = getActColor(colorIndex);

    const plainDescription = useMemo(
        () => (act.description ? stripTags(act.description).trim() : ''),
        [act.description],
    );

    const { setNodeRef: setDroppableRef } = useDroppable({
        id: `act-drop-${act.id}`,
        data: { type: 'act-drop', actId: act.id },
    });

    const { fulfilledCount, totalBeats, progressRatio } = useMemo(() => {
        const allBeats = plotPoints.flatMap((pp) => pp.beats ?? []);
        const fulfilled = allBeats.filter(
            (beat) => beat.status === 'fulfilled',
        ).length;
        const total = allBeats.length;
        return {
            fulfilledCount: fulfilled,
            totalBeats: total,
            progressRatio: total > 0 ? fulfilled / total : 0,
        };
    }, [plotPoints]);

    const plotPointIds = useMemo(
        () => plotPoints.map((pp) => `plotpoint-${pp.id}`),
        [plotPoints],
    );

    return (
        <div className="flex flex-col border-r border-border last:border-r-0">
            <div
                className="sticky top-0 z-10 flex items-center justify-between px-4 py-3"
                style={{
                    backgroundColor: color.bg,
                    borderTop: `3px solid ${color.border}`,
                }}
                onContextMenu={(e) => {
                    e.preventDefault();
                    onActContextMenu(act, { x: e.clientX, y: e.clientY });
                }}
            >
                <div className="flex min-w-0 flex-1 items-center gap-1.5">
                    <span className="flex shrink-0 cursor-grab items-center text-ink-faint active:cursor-grabbing">
                        <GripVertical className="h-3.5 w-3.5" />
                    </span>
                    <button
                        type="button"
                        className="flex min-w-0 flex-1 items-center gap-1.5 transition-opacity hover:opacity-70"
                        onClick={() => onSelectAct?.(act.id)}
                    >
                        <span
                            className="shrink-0 text-[11px] font-bold tracking-[0.08em] uppercase"
                            style={{ color: color.label }}
                        >
                            ACT {act.number}
                        </span>
                        <span className="truncate text-[13px] font-medium text-ink">
                            {titleOverrides?.[`act-${act.id}`] ?? act.title}
                        </span>
                    </button>
                </div>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => onCreatePlotPoint(act.id)}
                        className="flex h-5 w-5 items-center justify-center rounded transition-colors hover:bg-black/5"
                    >
                        <Plus size={14} className="text-ink-faint" />
                    </button>
                </div>
            </div>

            {plainDescription && (
                <p className="line-clamp-2 px-4 pt-2 text-[11px] text-ink-muted italic">
                    {plainDescription}
                </p>
            )}

            <div className="flex items-center gap-2.5 px-4 py-2">
                <div
                    className="h-1.5 flex-1 overflow-hidden rounded"
                    style={{ backgroundColor: color.track }}
                >
                    {totalBeats > 0 && (
                        <div
                            className="h-full rounded transition-all"
                            style={{
                                backgroundColor: color.label,
                                width: `${progressRatio * 100}%`,
                            }}
                        />
                    )}
                </div>
                {totalBeats > 0 && (
                    <span
                        className="shrink-0 text-[11px] font-medium"
                        style={{ color: color.label }}
                    >
                        {t('beat.progress', {
                            fulfilled: fulfilledCount,
                            total: totalBeats,
                        })}
                    </span>
                )}
            </div>

            <div
                ref={setDroppableRef}
                className="flex flex-1 flex-col gap-4 p-4"
            >
                <SortableContext
                    items={plotPointIds}
                    strategy={verticalListSortingStrategy}
                >
                    {plotPoints.map((plotPoint) => (
                        <PlotPointSection
                            key={plotPoint.id}
                            plotPoint={plotPoint}
                            selectedBeatId={selectedBeatId}
                            isSelected={selectedPlotPointId === plotPoint.id}
                            titleOverrides={titleOverrides}
                            onSelectBeat={onSelectBeat}
                            onSelectPlotPoint={onSelectPlotPoint}
                            onCreateBeat={onCreateBeat}
                            onBeatContextMenu={onBeatContextMenu}
                            onPlotPointContextMenu={onPlotPointContextMenu}
                        />
                    ))}
                </SortableContext>
            </div>
        </div>
    );
}
