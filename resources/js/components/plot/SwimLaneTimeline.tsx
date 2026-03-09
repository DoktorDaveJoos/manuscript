import { buildTimelineGrid, cellKey } from '@/lib/plot-utils';
import type { Act, PlotPoint, Storyline } from '@/types/models';
import PlotPointCard from './PlotPointCard';

type ChapterColumn = {
    id: number;
    title: string;
    reader_order: number;
    act_id: number;
    storyline_id: number;
    tension_score: number | null;
};

type Props = {
    acts: (Act & { chapters: ChapterColumn[] })[];
    storylines: Storyline[];
    plotPoints: PlotPoint[];
    onSelectPlotPoint: (pp: PlotPoint) => void;
    onCreatePlotPoint: (storylineId: number, chapterId: number) => void;
};

const ACT_COLORS: Record<number, string> = {
    0: '#C8B88A',
    1: '#8AB0C8',
    2: '#A3C4A0',
};

export default function SwimLaneTimeline({
    acts,
    storylines,
    plotPoints,
    onSelectPlotPoint,
    onCreatePlotPoint,
}: Props) {
    const { grid, allChapters } = buildTimelineGrid(acts, storylines, plotPoints);
    const COL_W = 160;
    const LABEL_W = 120;

    return (
        <div className="overflow-auto">
            <div className="inline-flex flex-col" style={{ minWidth: LABEL_W + allChapters.length * COL_W }}>
                {/* Act headers */}
                <div className="flex" style={{ paddingLeft: LABEL_W }}>
                    {acts.map((act, i) => (
                        <div
                            key={act.id}
                            className="flex items-center gap-2 border-b border-[#ECEAE4] px-3 py-2"
                            style={{ width: act.chapters.length * COL_W }}
                        >
                            <div
                                className="h-3.5 w-1 rounded-sm"
                                style={{ backgroundColor: ACT_COLORS[i] ?? '#C8B88A' }}
                            />
                            <span className="text-[11px] font-semibold uppercase tracking-wide text-[#5A574F]">
                                Act {act.number} — {act.title}
                            </span>
                        </div>
                    ))}
                </div>

                {/* Chapter sub-headers */}
                <div className="flex border-b border-[#ECEAE4]" style={{ paddingLeft: LABEL_W }}>
                    {allChapters.map((ch) => (
                        <div
                            key={ch.id}
                            className="px-3 py-1.5 text-[11px] text-[#8A857D]"
                            style={{ width: COL_W }}
                        >
                            Ch. {ch.reader_order + 1}
                        </div>
                    ))}
                </div>

                {/* Storyline rows */}
                {storylines.map((storyline) => (
                    <div key={storyline.id} className="flex border-b border-[#F0EEEA]">
                        <div
                            className="flex items-start gap-2 px-3 py-3"
                            style={{ width: LABEL_W, flexShrink: 0 }}
                        >
                            <div
                                className="mt-0.5 h-2 w-2 rounded-full"
                                style={{ backgroundColor: storyline.color ?? '#8A857D' }}
                            />
                            <span className="text-[11px] font-semibold uppercase tracking-wide text-[#5A574F]">
                                {storyline.name}
                            </span>
                        </div>
                        {allChapters.map((ch) => {
                            const cell = grid.get(cellKey(storyline.id, ch.id));
                            return (
                                <div
                                    key={ch.id}
                                    className="min-h-[80px] cursor-pointer border-l border-[#F0EEEA] p-1.5 hover:bg-[#FAFAF7]"
                                    style={{ width: COL_W }}
                                    onClick={() => {
                                        if (!cell?.plotPoints.length) {
                                            onCreatePlotPoint(storyline.id, ch.id);
                                        }
                                    }}
                                >
                                    <div className="flex flex-col gap-1">
                                        {cell?.plotPoints.map((pp) => (
                                            <PlotPointCard
                                                key={pp.id}
                                                plotPoint={pp}
                                                onClick={() => onSelectPlotPoint(pp)}
                                            />
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                ))}
            </div>
        </div>
    );
}
