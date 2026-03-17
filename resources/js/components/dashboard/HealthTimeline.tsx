import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import type { HealthSnapshot } from '@/types/models';

const VIEW_W = 720;
const VIEW_H = 180;
const PAD = { top: 20, right: 12, bottom: 28, left: 32 };
const INNER_W = VIEW_W - PAD.left - PAD.right;
const INNER_H = VIEW_H - PAD.top - PAD.bottom;

type MetricKey =
    | 'hooks'
    | 'pacing'
    | 'tension'
    | 'weave'
    | 'scene_purpose'
    | 'tension_dynamics'
    | 'emotional_arc'
    | 'craft';

function toPath(points: { x: number; y: number }[]): string {
    if (points.length === 0) return '';
    return points
        .map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x},${p.y}`)
        .join(' ');
}

function toAreaPath(points: { x: number; y: number }[]): string {
    if (points.length === 0) return '';
    const line = toPath(points);
    const lastX = points[points.length - 1].x;
    const firstX = points[0].x;
    return `${line} L${lastX},${PAD.top + INNER_H} L${firstX},${PAD.top + INNER_H} Z`;
}

export default function HealthTimeline({
    history,
}: {
    history: HealthSnapshot[];
}) {
    const { t, i18n } = useTranslation('dashboard');

    const data = useMemo(() => {
        if (history.length === 0) return null;

        const hasNewMetrics = history[0]?.scene_purpose != null;
        const metrics: { key: MetricKey }[] = hasNewMetrics
            ? [
                  { key: 'scene_purpose' },
                  { key: 'pacing' },
                  { key: 'tension_dynamics' },
                  { key: 'hooks' },
                  { key: 'emotional_arc' },
                  { key: 'craft' },
              ]
            : [
                  { key: 'hooks' },
                  { key: 'pacing' },
                  { key: 'tension' },
                  { key: 'weave' },
              ];

        const xScale = (i: number) =>
            history.length === 1
                ? PAD.left + INNER_W / 2
                : PAD.left + (i / (history.length - 1)) * INNER_W;
        const yScale = (v: number) => PAD.top + INNER_H - (v / 100) * INNER_H;

        const compositePoints = history.map((d, i) => ({
            x: xScale(i),
            y: yScale(d.composite),
        }));

        const metricLines = metrics.map((m) => ({
            ...m,
            points: history.map((d, i) => ({
                x: xScale(i),
                y: yScale(d[m.key] ?? 0),
            })),
        }));

        // Date labels
        const dateLabels: { x: number; label: string }[] = [];
        if (history.length === 1) {
            const d = new Date(history[0].date + 'T12:00:00');
            dateLabels.push({
                x: xScale(0),
                label: d.toLocaleDateString(i18n.language, {
                    month: 'short',
                    day: 'numeric',
                }),
            });
        } else {
            const indices = [
                0,
                Math.floor(history.length / 2),
                history.length - 1,
            ];
            for (const idx of indices) {
                const d = new Date(history[idx].date + 'T12:00:00');
                dateLabels.push({
                    x: xScale(idx),
                    label: d.toLocaleDateString(i18n.language, {
                        month: 'short',
                        day: 'numeric',
                    }),
                });
            }
        }

        return { compositePoints, metricLines, dateLabels };
    }, [history, i18n.language]);

    if (!data) {
        return (
            <p className="py-6 text-center text-[13px] text-ink-faint">
                {t('healthTimeline.noHistory')}
            </p>
        );
    }

    return (
        <div className="overflow-hidden rounded-xl border border-border-light bg-surface-card">
            <svg
                viewBox={`0 0 ${VIEW_W} ${VIEW_H}`}
                preserveAspectRatio="xMidYMid meet"
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
                            <text
                                x={PAD.left - 6}
                                y={y + 3}
                                textAnchor="end"
                                className="fill-ink-faint text-[10px]"
                            >
                                {v}
                            </text>
                        </g>
                    );
                })}

                {/* Composite area fill */}
                <path
                    d={toAreaPath(data.compositePoints)}
                    className="fill-accent/5"
                />

                {/* Metric lines (thin, muted) */}
                {data.metricLines.map((m) =>
                    m.points.length === 1 ? (
                        <circle
                            key={m.key}
                            cx={m.points[0].x}
                            cy={m.points[0].y}
                            r={2.5}
                            className="fill-ink-faint/40"
                        />
                    ) : (
                        <path
                            key={m.key}
                            d={toPath(m.points)}
                            fill="none"
                            className="stroke-ink-faint/40"
                            strokeWidth={1}
                        />
                    ),
                )}

                {/* Composite line (thick, accent) */}
                <path
                    d={toPath(data.compositePoints)}
                    fill="none"
                    className="stroke-accent"
                    strokeWidth={2.5}
                />

                {/* Data point dots */}
                {(data.compositePoints.length === 1
                    ? data.compositePoints
                    : [
                          data.compositePoints[0],
                          data.compositePoints[
                              Math.floor(data.compositePoints.length / 2)
                          ],
                          data.compositePoints[data.compositePoints.length - 1],
                      ]
                ).map((p, i) => (
                    <circle
                        key={i}
                        cx={p.x}
                        cy={p.y}
                        r={4}
                        className="fill-accent"
                    />
                ))}

                {/* Date labels */}
                {data.dateLabels.map((d, i) => (
                    <text
                        key={i}
                        x={d.x}
                        y={VIEW_H - 4}
                        textAnchor="middle"
                        className="fill-ink-faint text-[10px]"
                    >
                        {d.label}
                    </text>
                ))}
            </svg>
        </div>
    );
}
