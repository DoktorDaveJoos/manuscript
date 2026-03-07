import type { HealthSnapshot } from '@/types/models';
import { useMemo } from 'react';

const WIDTH = 720;
const HEIGHT = 180;
const PAD = { top: 20, right: 12, bottom: 28, left: 32 };
const INNER_W = WIDTH - PAD.left - PAD.right;
const INNER_H = HEIGHT - PAD.top - PAD.bottom;

type MetricKey = 'hooks' | 'pacing' | 'tension' | 'weave';
const METRICS: { key: MetricKey; label: string }[] = [
    { key: 'hooks', label: 'Hooks' },
    { key: 'pacing', label: 'Pacing' },
    { key: 'tension', label: 'Tension' },
    { key: 'weave', label: 'Weave' },
];

function toPath(points: { x: number; y: number }[]): string {
    if (points.length === 0) return '';
    return points.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x},${p.y}`).join(' ');
}

function toAreaPath(points: { x: number; y: number }[]): string {
    if (points.length === 0) return '';
    const line = toPath(points);
    const lastX = points[points.length - 1].x;
    const firstX = points[0].x;
    return `${line} L${lastX},${PAD.top + INNER_H} L${firstX},${PAD.top + INNER_H} Z`;
}

export default function HealthTimeline({ history }: { history: HealthSnapshot[] }) {
    const data = useMemo(() => {
        if (history.length < 2) return null;

        const xScale = (i: number) => PAD.left + (i / (history.length - 1)) * INNER_W;
        const yScale = (v: number) => PAD.top + INNER_H - (v / 100) * INNER_H;

        const compositePoints = history.map((d, i) => ({ x: xScale(i), y: yScale(d.composite) }));

        const metricLines = METRICS.map((m) => ({
            ...m,
            points: history.map((d, i) => ({ x: xScale(i), y: yScale(d[m.key]) })),
        }));

        // Date labels: first, middle, last
        const dateLabels: { x: number; label: string }[] = [];
        const indices = [0, Math.floor(history.length / 2), history.length - 1];
        for (const idx of indices) {
            const d = new Date(history[idx].date + 'T12:00:00');
            dateLabels.push({
                x: xScale(idx),
                label: d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
            });
        }

        return { compositePoints, metricLines, dateLabels };
    }, [history]);

    if (!data) {
        return (
            <p className="py-6 text-center text-[13px] text-ink-faint">
                Health history appears after your first analysis
            </p>
        );
    }

    return (
        <svg
            width={WIDTH}
            height={HEIGHT}
            viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
            className="w-full overflow-visible"
        >
            {/* Y-axis gridlines */}
            {[0, 25, 50, 75, 100].map((v) => {
                const y = PAD.top + INNER_H - (v / 100) * INNER_H;
                return (
                    <g key={v}>
                        <line
                            x1={PAD.left}
                            y1={y}
                            x2={PAD.left + INNER_W}
                            y2={y}
                            className="stroke-border-light"
                            strokeDasharray="2 3"
                        />
                        <text x={PAD.left - 6} y={y + 3} textAnchor="end" className="fill-ink-faint text-[10px]">
                            {v}
                        </text>
                    </g>
                );
            })}

            {/* Composite area fill */}
            <path d={toAreaPath(data.compositePoints)} className="fill-accent/10" />

            {/* Metric lines (thin, muted) */}
            {data.metricLines.map((m) => (
                <path
                    key={m.key}
                    d={toPath(m.points)}
                    fill="none"
                    className="stroke-ink-faint/40"
                    strokeWidth={1}
                />
            ))}

            {/* Composite line (thick, accent) */}
            <path d={toPath(data.compositePoints)} fill="none" className="stroke-ink" strokeWidth={2} />

            {/* Date labels */}
            {data.dateLabels.map((d, i) => (
                <text
                    key={i}
                    x={d.x}
                    y={HEIGHT - 4}
                    textAnchor="middle"
                    className="fill-ink-faint text-[10px]"
                >
                    {d.label}
                </text>
            ))}
        </svg>
    );
}
