import { EllipsisVertical, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { getActColor } from '@/lib/plot-constants';
import type { Act, Beat, PlotPoint } from '@/types/models';
import PlotPointSection from './PlotPointSection';

type Props = {
    act: Act;
    colorIndex: number;
    plotPoints: (PlotPoint & { beats?: Beat[] })[];
    selectedBeatId: number | null;
    onSelectBeat: (beat: Beat) => void;
    onCreateBeat: (plotPointId: number) => void;
    onDeleteAct: (actId: number) => void;
    onCreatePlotPoint: (actId: number) => void;
    onDeletePlotPoint: (plotPointId: number) => void;
    onBeatContextMenu: (beat: Beat, position: { x: number; y: number }) => void;
    isLast?: boolean;
};

export default function ActColumn({
    act,
    colorIndex,
    plotPoints,
    selectedBeatId,
    onSelectBeat,
    onCreateBeat,
    onDeleteAct,
    onCreatePlotPoint,
    onDeletePlotPoint,
    onBeatContextMenu,
    isLast = false,
}: Props) {
    const { t } = useTranslation('plot');
    const [menuOpen, setMenuOpen] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);
    const color = getActColor(colorIndex);

    useEffect(() => {
        if (!menuOpen) return;

        function handleClickOutside(e: MouseEvent) {
            if (
                menuRef.current &&
                !menuRef.current.contains(e.target as Node)
            ) {
                setMenuOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, [menuOpen]);

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

    return (
        <div
            className="flex flex-1 flex-col"
            style={{
                borderRight: isLast ? 'none' : '1px solid #E4E2DD',
            }}
        >
            {/* Header */}
            <div
                className="flex items-center justify-between px-4 py-3"
                style={{
                    backgroundColor: color.bg,
                    borderTop: `2px solid ${color.border}`,
                }}
            >
                <div className="flex items-center gap-1.5">
                    <span
                        className="text-[10px] font-bold tracking-[0.08em] uppercase"
                        style={{ color: '#C49A6C' }}
                    >
                        ACT {act.number}
                    </span>
                    <span
                        className="text-[13px] font-medium"
                        style={{ color: '#141414' }}
                    >
                        {act.title}
                    </span>
                </div>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => onCreatePlotPoint(act.id)}
                        className="flex items-center justify-center rounded transition-colors hover:bg-black/5"
                        style={{ width: 20, height: 20 }}
                    >
                        <Plus size={14} style={{ color: '#A3A3A3' }} />
                    </button>
                    <div ref={menuRef} className="relative">
                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                setMenuOpen(!menuOpen);
                            }}
                            className="flex items-center justify-center rounded transition-colors hover:bg-black/5"
                            style={{ width: 20, height: 20 }}
                        >
                            <EllipsisVertical
                                size={14}
                                style={{ color: '#A3A3A3' }}
                            />
                        </button>
                        {menuOpen && (
                            <div className="absolute top-full right-0 z-50 mt-1 w-[160px] rounded-lg bg-surface-card shadow-[0_4px_24px_#0000001F,0_0_0_1px_#0000000A]">
                                <div className="flex flex-col p-1">
                                    <button
                                        type="button"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            setMenuOpen(false);
                                            onDeleteAct(act.id);
                                        }}
                                        className="flex w-full items-center gap-2.5 rounded-[5px] px-3 py-2 text-left text-[13px] font-medium text-delete transition-colors hover:bg-neutral-bg"
                                    >
                                        <Trash2 size={14} />
                                        {t('act.deleteAct')}
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Progress bar */}
            {totalBeats > 0 && (
                <div
                    className="flex items-center gap-2.5 px-4 pb-2"
                    style={{ paddingTop: 8 }}
                >
                    <div
                        className="h-1 flex-1 overflow-hidden rounded-sm"
                        style={{ backgroundColor: '#EDE5DA' }}
                    >
                        <div
                            className="h-full rounded-sm transition-all"
                            style={{
                                backgroundColor: '#C49A6C',
                                width: `${progressRatio * 100}%`,
                            }}
                        />
                    </div>
                    <span
                        className="shrink-0 text-[10px] font-medium"
                        style={{ color: '#C49A6C' }}
                    >
                        {fulfilledCount} / {totalBeats}
                    </span>
                </div>
            )}

            {/* Plot points */}
            <div className="flex flex-1 flex-col gap-4 overflow-y-auto p-4">
                {plotPoints.map((plotPoint) => (
                    <PlotPointSection
                        key={plotPoint.id}
                        plotPoint={plotPoint}
                        selectedBeatId={selectedBeatId}
                        onSelectBeat={onSelectBeat}
                        onCreateBeat={onCreateBeat}
                        onDeletePlotPoint={onDeletePlotPoint}
                        onBeatContextMenu={onBeatContextMenu}
                    />
                ))}
            </div>
        </div>
    );
}
