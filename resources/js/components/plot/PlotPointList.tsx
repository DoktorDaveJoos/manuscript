import { STATUS_COLORS, TYPE_LABELS, TYPE_STYLES } from '@/lib/plot-constants';
import type { Act, PlotPoint, Storyline } from '@/types/models';

type Props = {
    acts: (Act & { chapters: { id: number; title: string }[] })[];
    plotPoints: PlotPoint[];
    storylines: Storyline[];
    onSelectPlotPoint: (pp: PlotPoint) => void;
};

function PlotPointRow({
    plotPoint,
    storylines,
    onClick,
}: {
    plotPoint: PlotPoint;
    storylines: Storyline[];
    onClick: () => void;
}) {
    const storyline = storylines.find((s) => s.id === plotPoint.storyline_id);

    return (
        <button
            onClick={onClick}
            className="w-full rounded border border-[#ECEAE4] bg-white px-3 py-2.5 text-left transition-shadow hover:shadow-[0_2px_4px_rgba(0,0,0,0.08)]"
        >
            <div className="flex items-center gap-2">
                <div
                    className="h-2 w-2 flex-shrink-0 rounded-full"
                    style={{ backgroundColor: STATUS_COLORS[plotPoint.status] ?? '#B0A99F' }}
                />
                <span className="flex-1 truncate text-[13px] font-medium text-[#2D2A26]">{plotPoint.title}</span>
                <span
                    className={`inline-block rounded px-1.5 py-0.5 text-[10px] font-medium ${TYPE_STYLES[plotPoint.type] ?? ''}`}
                >
                    {TYPE_LABELS[plotPoint.type] ?? plotPoint.type}
                </span>
            </div>
            {plotPoint.description && (
                <p className="mt-1 truncate pl-4 text-[12px] text-[#8A857D]">{plotPoint.description}</p>
            )}
            {storyline && <p className="mt-0.5 pl-4 text-[11px] text-[#B0A99F]">{storyline.name}</p>}
        </button>
    );
}

export default function PlotPointList({ acts, plotPoints, storylines, onSelectPlotPoint }: Props) {
    const assignedActIds = new Set(acts.map((a) => a.id));
    const unassigned = plotPoints.filter((pp) => !pp.act_id || !assignedActIds.has(pp.act_id));

    return (
        <div className="flex flex-col gap-6">
            {acts.map((act) => {
                const actPlotPoints = plotPoints.filter((pp) => pp.act_id === act.id);
                if (actPlotPoints.length === 0) return null;

                return (
                    <div key={act.id} className="flex flex-col gap-2">
                        <h3 className="text-[13px] font-semibold text-[#2D2A26]">
                            Act {act.number} &mdash; {act.title}
                        </h3>
                        <div className="flex flex-col gap-1.5">
                            {actPlotPoints.map((pp) => (
                                <PlotPointRow
                                    key={pp.id}
                                    plotPoint={pp}
                                    storylines={storylines}
                                    onClick={() => onSelectPlotPoint(pp)}
                                />
                            ))}
                        </div>
                    </div>
                );
            })}

            {unassigned.length > 0 && (
                <div className="flex flex-col gap-2">
                    <h3 className="text-[13px] font-semibold text-[#8A857D]">Unassigned</h3>
                    <div className="flex flex-col gap-1.5">
                        {unassigned.map((pp) => (
                            <PlotPointRow
                                key={pp.id}
                                plotPoint={pp}
                                storylines={storylines}
                                onClick={() => onSelectPlotPoint(pp)}
                            />
                        ))}
                    </div>
                </div>
            )}

            {plotPoints.length === 0 && (
                <div className="flex items-center justify-center py-20 text-[13px] text-[#8A857D]">
                    No plot points yet
                </div>
            )}
        </div>
    );
}
