import type { HeatmapDay } from '@/types/models';
import { useMemo, useState } from 'react';

const CELL = 12;
const GAP = 2;
const COLS = 53;
const ROWS = 7;
const DAY_LABELS = ['', 'Mon', '', 'Wed', '', 'Fri', ''];
const MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

function intensityClass(words: number, dailyGoal: number | null): string {
    if (words === 0) return 'fill-neutral-bg';
    if (!dailyGoal || dailyGoal <= 0) {
        if (words >= 1000) return 'fill-accent';
        if (words >= 500) return 'fill-accent/70';
        if (words >= 100) return 'fill-accent/40';
        return 'fill-accent/20';
    }
    const ratio = words / dailyGoal;
    if (ratio >= 1) return 'fill-accent';
    if (ratio >= 0.66) return 'fill-accent/70';
    if (ratio >= 0.33) return 'fill-accent/40';
    return 'fill-accent/20';
}

export default function WritingHeatmap({
    heatmap,
    dailyGoal,
}: {
    heatmap: HeatmapDay[];
    dailyGoal: number | null;
}) {
    const [tooltip, setTooltip] = useState<{ x: number; y: number; day: HeatmapDay } | null>(null);

    const { grid, monthMarkers } = useMemo(() => {
        const lookup = new Map<string, HeatmapDay>();
        for (const day of heatmap) {
            lookup.set(day.date, day);
        }

        const today = new Date();
        const todayDay = today.getDay(); // 0=Sun
        const startDate = new Date(today);
        startDate.setDate(startDate.getDate() - (COLS - 1) * 7 - todayDay);

        const cells: { col: number; row: number; day: HeatmapDay }[] = [];
        const months: { col: number; label: string }[] = [];
        let lastMonth = -1;

        for (let col = 0; col < COLS; col++) {
            for (let row = 0; row < ROWS; row++) {
                const d = new Date(startDate);
                d.setDate(d.getDate() + col * 7 + row);
                if (d > today) continue;

                const dateStr = d.toISOString().slice(0, 10);
                const entry = lookup.get(dateStr) ?? { date: dateStr, words: 0, goal_met: false };
                cells.push({ col, row, day: entry });

                if (row === 0 && d.getMonth() !== lastMonth) {
                    lastMonth = d.getMonth();
                    months.push({ col, label: MONTH_LABELS[lastMonth] });
                }
            }
        }

        return { grid: cells, monthMarkers: months };
    }, [heatmap]);

    const leftPad = 28;
    const topPad = 16;
    const svgWidth = leftPad + COLS * (CELL + GAP);
    const svgHeight = topPad + ROWS * (CELL + GAP);

    return (
        <div className="relative">
            <svg
                width={svgWidth}
                height={svgHeight}
                viewBox={`0 0 ${svgWidth} ${svgHeight}`}
                className="overflow-visible"
            >
                {/* Month labels */}
                {monthMarkers.map((m, i) => (
                    <text
                        key={i}
                        x={leftPad + m.col * (CELL + GAP)}
                        y={10}
                        className="fill-ink-faint text-[10px]"
                    >
                        {m.label}
                    </text>
                ))}

                {/* Day labels */}
                {DAY_LABELS.map(
                    (label, i) =>
                        label && (
                            <text
                                key={i}
                                x={0}
                                y={topPad + i * (CELL + GAP) + CELL - 2}
                                className="fill-ink-faint text-[10px]"
                            >
                                {label}
                            </text>
                        ),
                )}

                {/* Cells */}
                {grid.map(({ col, row, day }) => (
                    <rect
                        key={day.date}
                        x={leftPad + col * (CELL + GAP)}
                        y={topPad + row * (CELL + GAP)}
                        width={CELL}
                        height={CELL}
                        rx={2}
                        className={`${intensityClass(day.words, dailyGoal)} transition-colors`}
                        onMouseEnter={(e) => {
                            const rect = (e.target as SVGRectElement).getBoundingClientRect();
                            setTooltip({ x: rect.left + rect.width / 2, y: rect.top, day });
                        }}
                        onMouseLeave={() => setTooltip(null)}
                    />
                ))}
            </svg>

            {tooltip && (
                <div
                    className="pointer-events-none fixed z-50 -translate-x-1/2 -translate-y-full rounded-md bg-ink px-2.5 py-1.5 text-xs text-surface shadow-md"
                    style={{ left: tooltip.x, top: tooltip.y - 6 }}
                >
                    <span className="font-medium">{tooltip.day.words.toLocaleString('en-US')} words</span>
                    <span className="ml-1.5 text-surface/70">
                        {new Date(tooltip.day.date + 'T12:00:00').toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric',
                        })}
                    </span>
                </div>
            )}
        </div>
    );
}
