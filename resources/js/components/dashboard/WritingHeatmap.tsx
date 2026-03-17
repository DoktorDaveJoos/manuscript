import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { HeatmapDay } from '@/types/models';

const CELL = 10;
const GAP = 2;
const COLS = 53;
const ROWS = 7;
const DAY_LABEL_KEYS = [
    '',
    'heatmap.dayLabels.mon',
    '',
    'heatmap.dayLabels.wed',
    '',
    'heatmap.dayLabels.fri',
    '',
] as const;
const MONTH_LABEL_KEYS = [
    'heatmap.monthLabels.jan',
    'heatmap.monthLabels.feb',
    'heatmap.monthLabels.mar',
    'heatmap.monthLabels.apr',
    'heatmap.monthLabels.may',
    'heatmap.monthLabels.jun',
    'heatmap.monthLabels.jul',
    'heatmap.monthLabels.aug',
    'heatmap.monthLabels.sep',
    'heatmap.monthLabels.oct',
    'heatmap.monthLabels.nov',
    'heatmap.monthLabels.dec',
] as const;

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
    const { t, i18n } = useTranslation('dashboard');
    const [tooltip, setTooltip] = useState<{
        x: number;
        y: number;
        day: HeatmapDay;
    } | null>(null);

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
                const entry = lookup.get(dateStr) ?? {
                    date: dateStr,
                    words: 0,
                    goal_met: false,
                };
                cells.push({ col, row, day: entry });

                if (row === 0 && d.getMonth() !== lastMonth) {
                    lastMonth = d.getMonth();
                    months.push({ col, label: t(MONTH_LABEL_KEYS[lastMonth]) });
                }
            }
        }

        return { grid: cells, monthMarkers: months };
    }, [heatmap, t]);

    const leftPad = 30;
    const topPad = 16;
    const svgWidth = leftPad + COLS * (CELL + GAP);
    const svgHeight = topPad + ROWS * (CELL + GAP);

    return (
        <div className="rounded-xl border border-border-light bg-surface-card p-6">
            <div className="mb-3 flex items-center justify-between">
                <span className="text-[11px] font-medium tracking-[0.08em] text-ink-faint uppercase">
                    {t('heatmap.title', 'Writing Activity')}
                </span>
                <div className="flex items-center gap-1.5">
                    <span className="text-[10px] text-ink-faint">
                        {t('heatmap.legendLess', 'Less')}
                    </span>
                    <svg width="10" height="10">
                        <rect
                            width="10"
                            height="10"
                            rx="2"
                            className="fill-neutral-bg"
                        />
                    </svg>
                    <svg width="10" height="10">
                        <rect
                            width="10"
                            height="10"
                            rx="2"
                            className="fill-accent/25"
                        />
                    </svg>
                    <svg width="10" height="10">
                        <rect
                            width="10"
                            height="10"
                            rx="2"
                            className="fill-accent/50"
                        />
                    </svg>
                    <svg width="10" height="10">
                        <rect
                            width="10"
                            height="10"
                            rx="2"
                            className="fill-accent"
                        />
                    </svg>
                    <span className="text-[10px] text-ink-faint">
                        {t('heatmap.legendMore', 'More')}
                    </span>
                </div>
            </div>
            <svg
                width={svgWidth}
                height={svgHeight}
                viewBox={`0 0 ${svgWidth} ${svgHeight}`}
                className="w-full overflow-visible"
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
                {DAY_LABEL_KEYS.map(
                    (key, i) =>
                        key && (
                            <text
                                key={i}
                                x={0}
                                y={topPad + i * (CELL + GAP) + CELL - 2}
                                className="fill-ink-faint text-[10px]"
                            >
                                {t(key)}
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
                            const rect = (
                                e.target as SVGRectElement
                            ).getBoundingClientRect();
                            setTooltip({
                                x: rect.left + rect.width / 2,
                                y: rect.top,
                                day,
                            });
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
                    <span className="font-medium">
                        {t('heatmap.words', {
                            value: tooltip.day.words.toLocaleString(
                                i18n.language,
                            ),
                        })}
                    </span>
                    <span className="ml-1.5 text-surface/70">
                        {new Date(
                            tooltip.day.date + 'T12:00:00',
                        ).toLocaleDateString(i18n.language, {
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
