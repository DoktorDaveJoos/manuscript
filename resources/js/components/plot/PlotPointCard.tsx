import type { PlotPoint } from '@/types/models';

const TYPE_STYLES: Record<string, string> = {
    setup: 'bg-[#EDE8F5] text-[#6B5A8E]',
    conflict: 'bg-[#F5E8E8] text-[#8E5A5A]',
    turning_point: 'bg-[#F5EDE0] text-[#8A7A5A]',
    resolution: 'bg-[#E8F0E8] text-[#5A8E5A]',
    worldbuilding: 'bg-[#E8EDF5] text-[#5A6B8E]',
};

const TYPE_LABELS: Record<string, string> = {
    setup: 'Setup',
    conflict: 'Conflict',
    turning_point: 'Turning point',
    resolution: 'Resolution',
    worldbuilding: 'Worldbuilding',
};

const STATUS_COLORS: Record<string, string> = {
    planned: '#D4A843',
    fulfilled: '#6DBB7B',
    abandoned: '#B0A99F',
};

type Props = {
    plotPoint: PlotPoint;
    onClick: () => void;
};

export default function PlotPointCard({ plotPoint, onClick }: Props) {
    return (
        <button
            onClick={(e) => {
                e.stopPropagation();
                onClick();
            }}
            className="w-full rounded border border-[#ECEAE4] bg-white px-2.5 py-2 text-left shadow-[0_1px_2px_rgba(0,0,0,0.04)] transition-shadow hover:shadow-[0_2px_4px_rgba(0,0,0,0.08)]"
        >
            <div className="flex items-start gap-1.5">
                <div
                    className="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full"
                    style={{ backgroundColor: STATUS_COLORS[plotPoint.status] ?? '#B0A99F' }}
                />
                <span className="text-xs font-medium leading-tight text-[#2D2A26]">{plotPoint.title}</span>
            </div>
            <div className="mt-1.5">
                <span
                    className={`inline-block rounded px-1.5 py-0.5 text-[10px] font-medium ${TYPE_STYLES[plotPoint.type] ?? ''}`}
                >
                    {TYPE_LABELS[plotPoint.type] ?? plotPoint.type}
                </span>
            </div>
        </button>
    );
}
