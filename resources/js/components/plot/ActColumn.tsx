import { useDroppable } from '@dnd-kit/core';
import {
    SortableContext,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { Plus } from 'lucide-react';
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
    isLast?: boolean;
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
    isLast = false,
}: Props) {
    const { t } = useTranslation('plot');
    const color = getActColor(colorIndex);

    const plainDescription = act.description
        ? stripTags(act.description).trim()
        : '';

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
        <div
            className="flex min-w-[300px] flex-1 flex-col"
            style={{
                borderRight: isLast ? 'none' : '1px solid #E4E2DD',
            }}
        >
            {/* Header */}
            <div
                className="flex items-center justify-between px-4 py-3"
                style={{
                    backgroundColor: color.bg,
                    borderTop: `3px solid ${color.border}`,
                }}
                onContextMenu={(e) => {
                    e.preventDefault();
                    onActContextMenu(act, { x: e.clientX, y: e.clientY });
                }}
            >
                <button
                    type="button"
                    className="flex min-w-0 flex-1 items-center gap-1.5 transition-opacity hover:opacity-70"
                    onClick={() => onSelectAct?.(act.id)}
                >
                    <span
                        className="shrink-0 text-[10px] font-bold tracking-[0.08em] uppercase"
                        style={{ color: color.label }}
                    >
                        ACT {act.number}
                    </span>
                    <span
                        className="truncate text-[13px] font-medium"
                        style={{ color: '#141414' }}
                    >
                        {titleOverrides?.[`act-${act.id}`] ?? act.title}
                    </span>
                </button>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => onCreatePlotPoint(act.id)}
                        className="flex items-center justify-center rounded transition-colors hover:bg-black/5"
                        style={{ width: 20, height: 20 }}
                    >
                        <Plus size={14} style={{ color: '#A3A3A3' }} />
                    </button>
                </div>
            </div>

            {/* Description preview */}
            {plainDescription && (
                <p className="line-clamp-2 px-4 pt-2 text-[11px] text-ink-muted italic">
                    {plainDescription}
                </p>
            )}

            {/* Progress bar */}
            <div
                className="flex items-center gap-2.5 px-4 pb-2"
                style={{ paddingTop: 8 }}
            >
                <div
                    className="h-1.5 flex-1 overflow-hidden rounded-[3px]"
                    style={{ backgroundColor: color.track }}
                >
                    {totalBeats > 0 && (
                        <div
                            className="h-full rounded-[3px] transition-all"
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

            {/* Plot points */}
            <div
                ref={setDroppableRef}
                className="flex flex-1 flex-col gap-4 overflow-y-auto p-4"
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
