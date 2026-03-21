import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { stripTags } from '@/lib/ruleCheckers';
import type { Beat, PlotPoint } from '@/types/models';
import BeatCard from './BeatCard';

type Props = {
    plotPoint: PlotPoint & { beats?: Beat[] };
    selectedBeatId: number | null;
    onSelectBeat: (beat: Beat) => void;
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
    onCreateBeat,
    onBeatContextMenu,
    onPlotPointContextMenu,
}: Props) {
    const { t } = useTranslation('plot');

    const beats = plotPoint.beats ?? [];
    const plainDescription = plotPoint.description
        ? stripTags(plotPoint.description).trim()
        : '';

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
                <span
                    className="text-[13px] leading-tight font-semibold"
                    style={{ color: '#141414' }}
                >
                    {plotPoint.title}
                </span>
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
