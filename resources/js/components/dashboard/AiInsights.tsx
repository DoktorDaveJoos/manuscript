import { useTranslation } from 'react-i18next';
import type { AttentionItem, HealthMetrics } from '@/types/models';

function ScoreGauge({
    score,
    qualityLabel,
}: {
    score: number;
    qualityLabel: { good: string; fair: string; needsWork: string };
}) {
    const size = 96;
    const strokeWidth = 6;
    const radius = (size - strokeWidth) / 2;
    const circumference = 2 * Math.PI * radius;
    const progress = Math.min(100, Math.max(0, score));
    const dashOffset = circumference - (progress / 100) * circumference;

    return (
        <svg
            width={size}
            height={size}
            viewBox={`0 0 ${size} ${size}`}
            className="shrink-0"
        >
            <circle
                cx={size / 2}
                cy={size / 2}
                r={radius}
                fill="none"
                className="stroke-neutral-bg"
                strokeWidth={strokeWidth}
            />
            <circle
                cx={size / 2}
                cy={size / 2}
                r={radius}
                fill="none"
                className="stroke-accent"
                strokeWidth={strokeWidth}
                strokeLinecap="round"
                strokeDasharray={circumference}
                strokeDashoffset={dashOffset}
                transform={`rotate(-90 ${size / 2} ${size / 2})`}
            />
            <text
                x={size / 2}
                y={size / 2 - 2}
                textAnchor="middle"
                dominantBaseline="central"
                className="fill-ink font-serif text-[32px] font-normal"
            >
                {score}
            </text>
            <text
                x={size / 2}
                y={size / 2 + 20}
                textAnchor="middle"
                dominantBaseline="central"
                className="fill-ink-faint font-sans text-[11px]"
            >
                {score >= 70
                    ? qualityLabel.good
                    : score >= 50
                      ? qualityLabel.fair
                      : qualityLabel.needsWork}
            </text>
        </svg>
    );
}

function MetricBar({ label, score }: { label: string; score: number }) {
    return (
        <div className="flex items-center gap-2">
            <span className="w-[130px] text-[12px] text-ink-soft">{label}</span>
            <div className="flex-1">
                <div className="h-[4px] overflow-hidden rounded bg-neutral-bg">
                    <div
                        className="h-full rounded bg-accent"
                        style={{
                            width: `${Math.min(100, Math.max(0, score))}%`,
                        }}
                    />
                </div>
            </div>
            <span className="w-6 text-right text-[12px] font-semibold text-ink-soft">
                {score}
            </span>
        </div>
    );
}

const severityColor: Record<AttentionItem['severity'], string> = {
    high: 'bg-danger',
    medium: 'bg-accent',
    low: 'bg-ink-faint',
};

export default function AiInsights({
    healthMetrics,
}: {
    healthMetrics: HealthMetrics;
}) {
    const { t } = useTranslation('dashboard');

    return (
        <div className="flex gap-8">
            {/* Left — Manuscript Health */}
            <div className="flex flex-1 flex-col gap-4">
                <span className="text-[11px] font-medium tracking-[0.08em] text-ink-muted uppercase">
                    {t('aiInsights.manuscriptHealth')}
                </span>

                <ScoreGauge
                    score={healthMetrics.composite_score}
                    qualityLabel={{
                        good: t('aiInsights.quality.good', 'Good'),
                        fair: t('aiInsights.quality.fair', 'Fair'),
                        needsWork: t(
                            'aiInsights.quality.needsWork',
                            'Needs Work',
                        ),
                    }}
                />

                <div className="flex flex-col gap-[10px]">
                    {healthMetrics.metrics.map((metric) => (
                        <MetricBar
                            key={metric.label}
                            label={t(
                                'aiInsights.metrics.' + metric.label,
                                metric.label,
                            )}
                            score={metric.score}
                        />
                    ))}
                </div>
            </div>

            {/* Right — Attention Needed */}
            <div className="flex flex-1 flex-col gap-3">
                <span className="text-[11px] font-medium tracking-[0.08em] text-ink-muted uppercase">
                    {t('aiInsights.attentionItems', 'Attention Items')}
                </span>

                {healthMetrics.attention_items.length === 0 && (
                    <p className="text-[13px] text-ink-faint">
                        {t('aiInsights.noIssues')}
                    </p>
                )}

                <div className="flex flex-col gap-3">
                    {healthMetrics.attention_items.map((item, i) => {
                        let title: string;
                        let description: string;

                        if (item.description_key) {
                            title = t('aiInsights.attention.chapterTitle', {
                                order: item.chapter_order,
                                title: item.chapter_title,
                            });
                            const params = {
                                ...(item.description_params ?? {}),
                            };
                            if (
                                item.description_key === 'weakHook' &&
                                params.type
                            ) {
                                params.type = t(
                                    'aiInsights.hookType.' + params.type,
                                    String(params.type),
                                );
                            }
                            if (
                                item.description_key ===
                                    'informationDelivery' &&
                                params.type
                            ) {
                                params.type = t(
                                    'aiInsights.infoDelivery.' + params.type,
                                    String(params.type),
                                );
                            }
                            description = t(
                                'aiInsights.attention.' + item.description_key,
                                params,
                            );
                        } else {
                            title = item.title ?? '';
                            description = item.description ?? '';
                        }

                        return (
                            <div key={i} className="flex gap-3">
                                <span
                                    className={`mt-[2px] size-2 shrink-0 rounded-full ${severityColor[item.severity]}`}
                                />
                                <div className="flex flex-col gap-[3px]">
                                    <span className="text-[13px] font-semibold text-ink">
                                        {title}
                                    </span>
                                    <span className="text-[12px] leading-[1.4] text-ink-muted">
                                        {description}
                                    </span>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
