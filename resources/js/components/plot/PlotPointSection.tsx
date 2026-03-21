import { EllipsisVertical, Plus, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { Beat, PlotPoint } from '@/types/models';
import BeatCard from './BeatCard';

type Props = {
    plotPoint: PlotPoint & { beats?: Beat[] };
    selectedBeatId: number | null;
    onSelectBeat: (beat: Beat) => void;
    onSelectPlotPoint: (plotPoint: PlotPoint) => void;
    onCreateBeat: (plotPointId: number) => void;
    onDeletePlotPoint: (plotPointId: number) => void;
    onBeatContextMenu: (beat: Beat, position: { x: number; y: number }) => void;
};

export default function PlotPointSection({
    plotPoint,
    selectedBeatId,
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
                borderColor: '#E4E2DD',
                borderRadius: 8,
            }}
        >
            {/* Header */}
            <div className="flex items-center justify-between gap-2">
                <button
                    type="button"
                    onClick={() => onSelectPlotPoint(plotPoint)}
                    className="text-left text-[13px] leading-tight font-semibold transition-opacity hover:opacity-70"
                    style={{ color: '#141414' }}
                >
                    {plotPoint.title}
                </button>
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
                                        {t('detailPanel.header', 'Delete')}
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
