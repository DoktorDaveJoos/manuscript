import { useTranslation } from 'react-i18next';
import SectionLabel from '@/components/ui/SectionLabel';
import type { HealthMetric } from '@/types/models';

function qualityMessage(score: number, t: (key: string) => string): string {
    if (score >= 80) return t('health.quality.excellent');
    if (score >= 65) return t('health.quality.good');
    if (score >= 45) return t('health.quality.fair');
    return t('health.quality.needsWork');
}

export default function ManuscriptHealthCard({
    compositeScore,
    metrics,
}: {
    compositeScore: number;
    metrics: HealthMetric[];
}) {
    const { t } = useTranslation('ai-dashboard');

    return (
        <div className="flex flex-col gap-3">
            <SectionLabel>{t('health.label')}</SectionLabel>
            <div className="flex gap-6 rounded-lg bg-surface-card p-6">
                {/* Score */}
                <div className="flex w-[160px] shrink-0 flex-col items-center justify-center gap-1">
                    <span className="font-serif text-[32px] text-ink">
                        {compositeScore}
                    </span>
                    <span className="text-[12px] text-ink-muted">
                        {t('health.overallScore')}
                    </span>
                    <span className="text-[11px] text-ink-faint">
                        {t('health.outOf')}
                    </span>
                </div>

                {/* Detail */}
                <div className="flex flex-1 flex-col gap-4">
                    <p className="text-[14px] text-ink-soft">
                        {qualityMessage(compositeScore, t)}
                    </p>
                    <div className="flex flex-wrap gap-3">
                        {metrics.map((m) => (
                            <div
                                key={m.label}
                                className="flex items-center gap-2 rounded-md bg-neutral-bg px-3 py-1.5"
                            >
                                <span className="text-[12px] text-ink-muted">
                                    {t(`health.metrics.${m.label}`, m.label)}
                                </span>
                                <span className="text-[12px] font-semibold text-ink">
                                    {m.score}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
