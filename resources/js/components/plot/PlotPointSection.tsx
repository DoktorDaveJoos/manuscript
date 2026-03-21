import { EllipsisVertical, Plus, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
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
    onSelectPlotPoint?: (plotPoint: PlotPoint) => void;
    onCreateBeat: (plotPointId: number) => void;
    onDeletePlotPoint: (plotPointId: number) => void;
    onBeatContextMenu: (beat: Beat, position: { x: number; y: number }) => void;
};

export default function PlotPointSection({
    plotPoint,
    selectedBeatId,
    isSelected = false,
    titleOverrides,
    onSelectBeat,
    onSelectPlotPoint,
    onCreateBeat,
    onDeletePlotPoint,
    onBeatContextMenu,
}: Props) {
    const { t } = useTranslation('plot');
    const [menuOpen, setMenuOpen] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);

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

    const beats = plotPoint.beats ?? [];

    return (
        <div
            className="flex flex-col gap-2.5 rounded-lg border p-3.5"
            style={{
                backgroundColor: '#FCFAF7',
                borderColor: isSelected ? '#C49A6C' : '#E4E2DD',
                borderRadius: 8,
                borderWidth: isSelected ? 2 : 1,
            }}
        >
            {/* Header */}
            <div className="flex items-center justify-between gap-2">
                <button
                    type="button"
                    onClick={() => onSelectPlotPoint?.(plotPoint)}
                    className="min-w-0 truncate text-[13px] leading-tight font-semibold transition-colors hover:opacity-70"
                    style={{ color: '#141414' }}
                >
                    {titleOverrides?.[`plotpoint-${plotPoint.id}`] ??
                        plotPoint.title}
                </button>
                <div className="flex shrink-0 items-center gap-2">
                    <span
                        className="rounded px-2 py-0.5 text-[10px] font-medium"
                        style={{
                            backgroundColor:
                                TYPE_BADGE_COLORS[plotPoint.type]?.bg ??
                                '#F0EEEB',
                            borderRadius: 4,
                            color:
                                TYPE_BADGE_COLORS[plotPoint.type]?.text ??
                                '#737373',
                        }}
                    >
                        {t(`type.${plotPoint.type}`)}
                    </span>
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
                                            onDeletePlotPoint(plotPoint.id);
                                        }}
                                        className="flex w-full items-center gap-2.5 rounded-[5px] px-3 py-2 text-left text-[13px] font-medium text-delete transition-colors hover:bg-neutral-bg"
                                    >
                                        <Trash2 size={14} />
                                        {t('plotPoint.delete')}
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Beats list */}
            {beats.length > 0 && (
                <div className="flex flex-col gap-2 pl-1">
                    {beats.map((beat) => (
                        <BeatCard
                            key={beat.id}
                            beat={beat}
                            isSelected={selectedBeatId === beat.id}
                            titleOverride={titleOverrides?.[`beat-${beat.id}`]}
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
