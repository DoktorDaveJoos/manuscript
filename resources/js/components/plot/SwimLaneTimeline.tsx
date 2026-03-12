import { buildTimelineGrid, cellKey } from '@/lib/plot-utils';
import type { Act, PlotPoint, Storyline } from '@/types/models';
import { useTranslation } from 'react-i18next';
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
    chapters?: ChapterColumn[];
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
    chapters = [],
    onSelectPlotPoint,
    onCreatePlotPoint,
}: Props) {
    const { t } = useTranslation('plot');
    const { grid } = buildTimelineGrid(acts, storylines, plotPoints);
    const ACT_COL_W = 240;
    const LABEL_W = 120;
    const hasActs = acts.length > 0;

    return (
        <div className="overflow-auto">
            <div className="inline-flex flex-col" style={{ minWidth: LABEL_W + Math.max(1, acts.length) * ACT_COL_W }}>
                {/* Act headers */}
                {hasActs && (
                    <div className="flex" style={{ paddingLeft: LABEL_W }}>
                        {acts.map((act, i) => (
                            <div
                                key={act.id}
                                className="flex items-center gap-2 border-b border-[#ECEAE4] px-3 py-2"
                                style={{ width: ACT_COL_W }}
                            >
                                <div
                                    className="h-3.5 w-1 rounded-sm"
                                    style={{ backgroundColor: ACT_COLORS[i] ?? '#C8B88A' }}
                                />
                                <span className="text-[11px] font-semibold uppercase tracking-wide text-[#5A574F]">
                                    {t('actTitle', { number: act.number, title: act.title })}
                                </span>
                            </div>
                        ))}
                    </div>
                )}

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
                        {hasActs ? (
                            acts.map((act) => {
                                const cell = grid.get(cellKey(storyline.id, act.id));
                                const cellChapters = cell?.chapters ?? [];
                                const firstChapter = cellChapters[0];
                                return (
                                    <div
                                        key={act.id}
                                        className="min-h-[80px] cursor-pointer border-l border-[#F0EEEA] p-1.5 hover:bg-[#FAFAF7]"
                                        style={{ width: ACT_COL_W }}
                                        onClick={() => {
                                            if (!cell?.plotPoints.length && firstChapter) {
                                                onCreatePlotPoint(storyline.id, firstChapter.id);
                                            }
                                        }}
                                    >
                                        {cellChapters.length > 0 && (
                                            <div className="mb-1.5 flex flex-wrap gap-1">
                                                {cellChapters.map((ch) => (
                                                    <span
                                                        key={ch.id}
                                                        className="rounded bg-[#F0EEEA] px-1.5 py-0.5 text-[11px] text-[#5A574F]"
                                                    >
                                                        {t('timeline.chapterAbbrev', { number: ch.reader_order + 1 })} &middot; {ch.title}
                                                    </span>
                                                ))}
                                            </div>
                                        )}
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
                            })
                        ) : (
                            /* No acts — single column with all chapters for this storyline */
                            <div
                                className="min-h-[80px] flex-1 border-l border-[#F0EEEA] p-1.5"
                                style={{ minWidth: ACT_COL_W }}
                            >
                                {(() => {
                                    const slChapters = chapters.filter((ch) => ch.storyline_id === storyline.id);
                                    return slChapters.length > 0 ? (
                                        <div className="flex flex-wrap gap-1">
                                            {slChapters.map((ch) => (
                                                <span
                                                    key={ch.id}
                                                    className="rounded bg-[#F0EEEA] px-1.5 py-0.5 text-[11px] text-[#5A574F]"
                                                >
                                                    {t('timeline.chapterAbbrev', { number: ch.reader_order + 1 })} &middot; {ch.title}
                                                </span>
                                            ))}
                                        </div>
                                    ) : null;
                                })()}
                            </div>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}
