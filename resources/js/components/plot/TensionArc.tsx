import { ChevronUp } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export type TensionData = {
    chapter_id: number;
    reader_order: number;
    tension_score: number;
    title?: string;
};

type Props = {
    data: TensionData[];
    chapterCount: number;
    labelWidth: number;
    columnWidth: number;
    onCollapse: () => void;
};

function buildSmoothPath(points: { x: number; y: number }[]): string {
    if (points.length === 0) return '';
    if (points.length === 1) return `M${points[0].x},${points[0].y}`;

    let d = `M${points[0].x},${points[0].y}`;

    for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[Math.max(0, i - 1)];
        const p1 = points[i];
        const p2 = points[i + 1];
        const p3 = points[Math.min(points.length - 1, i + 2)];

        const tension = 0.3;
        const cp1x = p1.x + (p2.x - p0.x) * tension;
        const cp1y = p1.y + (p2.y - p0.y) * tension;
        const cp2x = p2.x - (p3.x - p1.x) * tension;
        const cp2y = p2.y - (p3.y - p1.y) * tension;

        d += ` C${cp1x},${cp1y} ${cp2x},${cp2y} ${p2.x},${p2.y}`;
    }

    return d;
}

export default function TensionArc({ data, chapterCount, labelWidth, columnWidth, onCollapse }: Props) {
    const { t } = useTranslation('plot');
    const H = 60;
    const sortedData = [...data].sort((a, b) => a.reader_order - b.reader_order);
    const totalWidth = labelWidth + chapterCount * columnWidth;

    const points = sortedData.map((d) => ({
        x: labelWidth + (d.reader_order + 0.5) * columnWidth,
        y: H - (d.tension_score / 10) * (H - 12) - 6,
        score: d.tension_score,
    }));

    const linePath = buildSmoothPath(points);

    const fillPath =
        points.length > 0
            ? `${linePath} L${points[points.length - 1].x},${H} L${points[0].x},${H} Z`
            : '';

    return (
        <div className="inline-flex items-start" style={{ minWidth: totalWidth }}>
            {/* Left label */}
            <div
                className="flex shrink-0 flex-col justify-center gap-0.5 px-3 py-2"
                style={{ width: labelWidth }}
            >
                <div className="flex items-center gap-1.5">
                    <span className="text-[10px] font-semibold uppercase tracking-wide text-ink-soft">
                        {t('tensionArc.label')}
                    </span>
                    <button
                        type="button"
                        onClick={onCollapse}
                        className="flex size-4 items-center justify-center rounded text-ink-faint transition-colors hover:text-ink-soft"
                        title={t('tensionArc.collapseTitle')}
                    >
                        <ChevronUp size={10} strokeWidth={2.5} />
                    </button>
                </div>
                <span className="text-[9px] text-ink-faint">{t('tensionArc.aiGenerated')}</span>
            </div>

            {/* SVG chart */}
            <svg
                width={chapterCount * columnWidth}
                height={H}
                viewBox={`${labelWidth} 0 ${chapterCount * columnWidth} ${H}`}
                className="block"
            >
                <defs>
                    <linearGradient id="tension-fill-gradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" style={{ stopColor: 'var(--color-accent)' }} stopOpacity={0.2} />
                        <stop offset="100%" style={{ stopColor: 'var(--color-accent)' }} stopOpacity={0} />
                    </linearGradient>
                </defs>

                {/* Gradient fill area */}
                {fillPath && (
                    <path d={fillPath} fill="url(#tension-fill-gradient)" />
                )}

                {/* Line */}
                {linePath && (
                    <path d={linePath} fill="none" stroke="var(--color-accent)" strokeWidth={2} />
                )}

                {/* Data points and labels */}
                {points.map((p, i) => (
                    <g key={sortedData[i].chapter_id}>
                        <circle cx={p.x} cy={p.y} r={3} fill="var(--color-accent)" />
                        <text
                            x={p.x}
                            y={H - 2}
                            textAnchor="middle"
                            fill="#B0A99F"
                            fontSize={9}
                        >
                            {p.score}
                        </text>
                    </g>
                ))}
            </svg>
        </div>
    );
}
