import { useTranslation } from 'react-i18next';
import { STATUS_COLORS, TYPE_STYLES } from '@/lib/plot-constants';
import type { PlotPoint } from '@/types/models';

type Props = {
    plotPoint: PlotPoint;
    onClick: () => void;
};

export default function PlotPointCard({ plotPoint, onClick }: Props) {
    const { t } = useTranslation('plot');

    return (
        <button
            onClick={(e) => {
                e.stopPropagation();
                onClick();
            }}
            className="w-full rounded border border-border bg-surface-card px-2.5 py-2 text-left shadow-[0_1px_2px_rgba(0,0,0,0.04)] transition-shadow hover:shadow-[0_2px_4px_rgba(0,0,0,0.08)]"
        >
            <div className="flex items-start gap-1.5">
                <div
                    className="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full"
                    style={{ backgroundColor: STATUS_COLORS[plotPoint.status] ?? '#B0A99F' }}
                />
                <span className="text-xs font-medium leading-tight text-ink">{plotPoint.title}</span>
            </div>
            <div className="mt-1.5">
                <span
                    className={`inline-block rounded px-1.5 py-0.5 text-[10px] font-medium ${TYPE_STYLES[plotPoint.type] ?? ''}`}
                >
                    {t(`type.${plotPoint.type}`)}
                </span>
            </div>
        </button>
    );
}
