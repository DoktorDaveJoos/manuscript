import { router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    store,
    updateStatus,
} from '@/actions/App/Http/Controllers/PlotPointController';
import { NEXT_STATUS, STATUS_COLORS, TYPE_STYLES } from '@/lib/plot-constants';
import type { PlotPoint } from '@/types/models';

type Props = {
    plotPoints: PlotPoint[];
    bookId: number;
    chapterId: number;
};

export default function ChapterBeats({ plotPoints, bookId, chapterId }: Props) {
    const { t } = useTranslation('editor');
    const { t: tPlot } = useTranslation('plot');
    const handleCycleStatus = (plotPoint: PlotPoint) => {
        const nextStatus = NEXT_STATUS[plotPoint.status];
        router.patch(
            updateStatus.url({ book: bookId, plotPoint: plotPoint.id }),
            { status: nextStatus },
            { preserveScroll: true },
        );
    };

    const handleAdd = () => {
        router.post(
            store.url({ book: bookId }),
            {
                title: t('beats.newBeat'),
                type: 'setup',
                intended_chapter_id: chapterId,
            },
            { preserveScroll: true },
        );
    };

    return (
        <div className="px-2.5 py-3">
            <div className="mb-2 flex items-center justify-between px-1.5">
                <span className="text-[11px] font-semibold tracking-[0.05em] text-ink-muted uppercase">
                    {t('beats.title')}
                </span>
                <button
                    onClick={handleAdd}
                    className="flex items-center gap-0.5 text-[11px] text-ink-muted transition-colors hover:text-ink"
                >
                    <Plus size={10} strokeWidth={2.5} />
                    {t('beats.add')}
                </button>
            </div>

            {plotPoints.length === 0 ? (
                <p className="px-1.5 text-[11px] text-ink-faint">
                    {t('beats.empty')}
                </p>
            ) : (
                <ul className="flex flex-col gap-0.5">
                    {plotPoints.map((pp) => (
                        <li
                            key={pp.id}
                            className="flex items-center gap-2 rounded-md px-1.5 py-1"
                        >
                            <button
                                onClick={() => handleCycleStatus(pp)}
                                className="shrink-0"
                                title={t('beats.statusTooltip', {
                                    status: pp.status,
                                })}
                            >
                                <span
                                    className="block h-2 w-2 rounded-full"
                                    style={{
                                        backgroundColor:
                                            STATUS_COLORS[pp.status],
                                    }}
                                />
                            </button>
                            <span className="min-w-0 flex-1 truncate text-[12px] text-ink">
                                {pp.title}
                            </span>
                            <span
                                className={`shrink-0 rounded px-1 py-0.5 text-[9px] leading-none font-medium ${TYPE_STYLES[pp.type] ?? ''}`}
                            >
                                {tPlot(`typeShort.${pp.type}`)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
