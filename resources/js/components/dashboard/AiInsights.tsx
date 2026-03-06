import type { AttentionItem, HealthMetrics } from '@/types/models';

function formatTimeAgo(dateString: string): string {
    const now = new Date();
    const date = new Date(dateString);
    const seconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (seconds < 60) return 'just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} hour${hours === 1 ? '' : 's'} ago`;
    const days = Math.floor(hours / 24);
    return `${days} day${days === 1 ? '' : 's'} ago`;
}

function ScoreGauge({ score }: { score: number }) {
    const size = 96;
    const strokeWidth = 6;
    const radius = (size - strokeWidth) / 2;
    const circumference = 2 * Math.PI * radius;
    const progress = Math.min(100, Math.max(0, score));
    const dashOffset = circumference - (progress / 100) * circumference;

    return (
        <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} className="shrink-0">
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
                className="stroke-status-final"
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
                className="fill-ink font-serif text-[32px] font-medium"
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
                of 100
            </text>
        </svg>
    );
}

function MetricBar({ label, score }: { label: string; score: number }) {
    return (
        <div className="flex items-center gap-3">
            <span className="w-[52px] text-[13px] text-ink-muted">{label}</span>
            <div className="flex-1">
                <div className="h-[5px] overflow-hidden rounded-[3px] bg-neutral-bg">
                    <div
                        className={`h-full rounded-[3px] ${score >= 80 ? 'bg-status-final' : 'bg-accent'}`}
                        style={{ width: `${Math.min(100, Math.max(0, score))}%` }}
                    />
                </div>
            </div>
            <span className="w-6 text-right text-[13px] font-medium text-ink">{score}</span>
        </div>
    );
}

const severityColor: Record<AttentionItem['severity'], string> = {
    high: 'bg-danger',
    medium: 'bg-accent',
    low: 'bg-ink-faint',
};

export default function AiInsights({ healthMetrics }: { healthMetrics: HealthMetrics }) {
    return (
        <div className="flex gap-8">
            {/* Left — Manuscript Health */}
            <div className="flex flex-1 flex-col gap-5">
                <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">
                    Manuscript Health
                </span>

                <div className="flex items-center gap-5">
                    <ScoreGauge score={healthMetrics.composite_score} />
                    <span className="text-[12px] text-ink-muted">
                        Last analyzed{' '}
                        <span className="text-ink-faint">{formatTimeAgo(healthMetrics.last_analyzed_at)}</span>
                    </span>
                </div>

                <div className="flex flex-col gap-[10px]">
                    {healthMetrics.metrics.map((metric) => (
                        <MetricBar key={metric.label} label={metric.label} score={metric.score} />
                    ))}
                </div>
            </div>

            {/* Right — Attention Needed */}
            <div className="flex flex-1 flex-col gap-4">
                <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">
                    Attention Needed
                </span>

                {healthMetrics.attention_items.length === 0 && (
                    <p className="text-[13px] text-ink-faint">No issues found.</p>
                )}

                <div className="flex flex-col gap-4">
                    {healthMetrics.attention_items.map((item, i) => (
                        <div key={i} className="flex gap-2.5">
                            <span
                                className={`mt-1.5 size-[7px] shrink-0 rounded-full ${severityColor[item.severity]}`}
                            />
                            <div className="flex flex-col gap-0.5">
                                <span className="text-[13px] font-medium text-ink">{item.title}</span>
                                <span className="text-[12px] leading-[18px] text-ink-faint">
                                    {item.description}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
