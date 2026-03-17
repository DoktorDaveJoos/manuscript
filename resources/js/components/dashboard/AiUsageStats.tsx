import { router } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { resetUsage } from '@/actions/App/Http/Controllers/AiController';
import { formatCompactCount, jsonFetchHeaders } from '@/lib/utils';
import type { AiUsage, AiUsageFeatureBreakdown, AiUsageMonthly } from '@/types/models';

const FEATURE_LABELS: Record<string, string> = {
    chat: 'AI Chat',
    analysis: 'Story Analysis',
    craft_metrics: 'Craft Metrics',
    preparation: 'AI Preparation',
    suggestions: 'Suggestions',
    embedding: 'Embeddings',
    writing_style: 'Writing Style',
    beautify: 'Beautify',
    entity_extraction: 'Entity Extraction',
    story_bible: 'Story Bible',
    other: 'Other',
};

function CostBreakdownTable({ breakdown }: { breakdown: AiUsageFeatureBreakdown[] }) {
    const { t } = useTranslation('dashboard');
    if (breakdown.length === 0) return null;

    const totalTokens = breakdown.reduce((sum, f) => sum + f.tokens, 0);
    const totalCost = breakdown.reduce((sum, f) => sum + f.cost_micro, 0);

    return (
        <div className="rounded-xl border border-border-light bg-surface-card p-6">
            <span className="mb-4 block text-[10px] font-medium uppercase tracking-[0.08em] text-ink-muted">
                {t('aiUsage.costBreakdown', 'Cost Breakdown')}
            </span>
            <table className="w-full text-[13px]">
                <thead>
                    <tr className="border-b border-border-light text-left text-[10px] font-medium uppercase tracking-[0.08em] text-ink-muted">
                        <th className="pb-3 font-medium">{t('aiUsage.feature', 'Feature')}</th>
                        <th className="pb-3 text-right font-medium">{t('aiUsage.tokensCol', 'Tokens')}</th>
                        <th className="pb-3 text-right font-medium">{t('aiUsage.costCol', 'Cost')}</th>
                        <th className="pb-3 text-right font-medium">{t('aiUsage.shareCol', 'Share')}</th>
                    </tr>
                </thead>
                <tbody>
                    {breakdown.map((f) => {
                        const share = totalTokens > 0 ? (f.tokens / totalTokens) * 100 : 0;
                        const cost = f.cost_micro / 1_000_000;
                        return (
                            <tr key={f.feature} className="border-b border-border-subtle">
                                <td className="py-3 font-medium text-ink">{FEATURE_LABELS[f.feature] ?? f.feature}</td>
                                <td className="py-3 text-right text-ink-soft">{formatCompactCount(f.tokens)}</td>
                                <td className="py-3 text-right text-ink-soft">${cost.toFixed(4)}</td>
                                <td className="py-3">
                                    <div className="flex items-center justify-end gap-2">
                                        <div className="h-[4px] w-[55px] overflow-hidden rounded-[2px] bg-neutral-bg">
                                            <div
                                                className="h-full rounded-[2px] bg-accent"
                                                style={{ width: `${share}%` }}
                                            />
                                        </div>
                                        <span className="text-ink-soft">{Math.round(share)}%</span>
                                    </div>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
                <tfoot>
                    <tr className="text-[12px] font-semibold">
                        <td className="pt-2.5 text-ink">{t('aiUsage.total', 'Total')}</td>
                        <td className="pt-2.5 text-right text-ink">{formatCompactCount(totalTokens)}</td>
                        <td className="pt-2.5 text-right text-ink">${(totalCost / 1_000_000).toFixed(4)}</td>
                        <td className="pt-2.5 pl-4 text-ink-muted">100%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

function TokenUsageChart({ monthly }: { monthly: AiUsageMonthly[] }) {
    const { t } = useTranslation('dashboard');
    if (monthly.length === 0) return null;

    const maxTokens = Math.max(...monthly.map((m) => m.tokens), 1);
    const chartH = 140;
    const barW = 40;
    const gap = 16;
    const chartW = monthly.length * (barW + gap) - gap + 40;

    // Calculate nice gridline intervals
    const gridMax = Math.ceil(maxTokens / 50_000) * 50_000;
    const gridStep = gridMax <= 100_000 ? 25_000 : 50_000;
    const gridLines: number[] = [];
    for (let v = 0; v <= gridMax; v += gridStep) {
        gridLines.push(v);
    }

    function formatGridLabel(v: number): string {
        if (v === 0) return '0';
        if (v >= 1_000_000) return `${v / 1_000_000}M`;
        return `${v / 1_000}K`;
    }

    return (
        <div className="rounded-xl border border-border-light bg-surface-card p-6">
            <span className="mb-4 block text-[10px] font-medium uppercase tracking-[0.08em] text-ink-muted">
                {t('aiUsage.tokenUsage', 'Token Usage')}
            </span>
            <svg
                width={chartW}
                height={chartH + 30}
                viewBox={`0 0 ${chartW} ${chartH + 30}`}
                className="w-full overflow-visible"
            >
                {/* Gridlines */}
                {gridLines.map((v) => {
                    const y = chartH - (v / gridMax) * chartH;
                    return (
                        <g key={v}>
                            <line
                                x1={36}
                                y1={y}
                                x2={chartW}
                                y2={y}
                                className="stroke-border-light"
                                strokeDasharray="2 3"
                            />
                            <text x={32} y={y + 3} textAnchor="end" className="fill-ink-faint text-[9px]">
                                {formatGridLabel(v)}
                            </text>
                        </g>
                    );
                })}

                {/* Bars */}
                {monthly.map((m, i) => {
                    const barH = (m.tokens / gridMax) * chartH;
                    const x = 40 + i * (barW + gap);
                    const y = chartH - barH;
                    const isLast = i === monthly.length - 1;

                    return (
                        <g key={m.month}>
                            <rect
                                x={x}
                                y={y}
                                width={barW}
                                height={Math.max(barH, 1)}
                                rx={4}
                                className={isLast ? 'fill-accent' : 'fill-accent/40'}
                            />
                            {/* Value label */}
                            <text
                                x={x + barW / 2}
                                y={y - 6}
                                textAnchor="middle"
                                className="fill-ink-muted text-[9px]"
                            >
                                {formatCompactCount(m.tokens)}
                            </text>
                            {/* Month label */}
                            <text
                                x={x + barW / 2}
                                y={chartH + 16}
                                textAnchor="middle"
                                className="fill-ink-faint text-[10px]"
                            >
                                {m.month.slice(5)}
                            </text>
                        </g>
                    );
                })}
            </svg>
        </div>
    );
}

export default function AiUsageStats({ bookId, usage }: { bookId: number; usage: AiUsage }) {
    const { t, i18n } = useTranslation('dashboard');
    const [confirming, setConfirming] = useState(false);

    const handleReset = useCallback(async () => {
        if (!confirming) {
            setConfirming(true);
            return;
        }

        try {
            await fetch(resetUsage.url(bookId), {
                method: 'POST',
                headers: jsonFetchHeaders(),
            });
            router.reload({ only: ['ai_usage'] });
        } catch {
            // Ignore errors
        } finally {
            setConfirming(false);
        }
    }, [bookId, confirming]);

    if (usage.input_tokens + usage.output_tokens === 0) return null;

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <button
                    type="button"
                    onClick={handleReset}
                    onBlur={() => setConfirming(false)}
                    className="text-[12px] text-ink-faint transition-colors hover:text-ink"
                >
                    {confirming ? t('aiUsage.confirmReset') : t('aiUsage.reset')}
                </button>
            </div>

            {/* Summary cards */}
            <div className="flex gap-4">
                <div className="flex flex-1 flex-col items-center gap-2 rounded-xl border border-border-light bg-surface-card p-6">
                    <span className="text-[10px] font-medium uppercase tracking-[0.08em] text-ink-muted">
                        {t('aiUsage.tokensUsed', 'Tokens Used')}
                    </span>
                    <span className="font-serif text-[32px] font-bold text-ink">
                        {formatCompactCount(usage.input_tokens + usage.output_tokens)}
                    </span>
                    <span className="text-[12px] text-ink-muted">{t('aiUsage.tokens', 'tokens')}</span>
                </div>
                <div className="flex flex-1 flex-col items-center gap-2 rounded-xl border border-border-light bg-surface-card p-6">
                    <span className="text-[10px] font-medium uppercase tracking-[0.08em] text-ink-muted">
                        {t('aiUsage.estCost')}
                    </span>
                    <span className="font-serif text-[32px] font-bold text-ink">{usage.cost_display}</span>
                    <span className="text-[12px] text-ink-muted">{t('aiUsage.thisMonth', 'this month')}</span>
                </div>
                <div className="flex flex-1 flex-col items-center gap-2 rounded-xl border border-border-light bg-surface-card p-6">
                    <span className="text-[10px] font-medium uppercase tracking-[0.08em] text-ink-muted">
                        {t('aiUsage.apiRequests', 'API Requests')}
                    </span>
                    <span className="font-serif text-[32px] font-bold text-ink">
                        {usage.request_count.toLocaleString(i18n.language)}
                    </span>
                    <span className="text-[12px] text-ink-muted">{t('aiUsage.totalLabel', 'total')}</span>
                </div>
                <div className="flex flex-1 flex-col items-center gap-2 rounded-xl border border-border-light bg-surface-card p-6">
                    <span className="text-[10px] font-medium uppercase tracking-[0.08em] text-ink-muted">
                        {t('aiUsage.avgCost', 'Avg. Cost')}
                    </span>
                    <span className="font-serif text-[32px] font-bold text-ink">{usage.avg_cost_display}</span>
                    <span className="text-[12px] text-ink-muted">{t('aiUsage.perRequest', 'per request')}</span>
                </div>
            </div>

            {usage.reset_at && (
                <span className="text-[12px] text-ink-faint">
                    {t('aiUsage.trackingSince', {
                        date: new Date(usage.reset_at).toLocaleDateString(i18n.language, {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric',
                        }),
                    })}
                </span>
            )}

            {/* Cost Breakdown + Token Chart */}
            <div className="flex gap-4">
                <div className="flex-1">
                    <CostBreakdownTable breakdown={usage.features_breakdown} />
                </div>
                <div className="flex-1">
                    <TokenUsageChart monthly={usage.monthly_usage} />
                </div>
            </div>
        </div>
    );
}
