import { router } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import SectionLabel from '@/components/ui/SectionLabel';
import { formatCompactCount, jsonFetchHeaders } from '@/lib/utils';
import { resetUsage } from '@/actions/App/Http/Controllers/AiController';

type AiUsageSummary = {
    input_tokens: number;
    output_tokens: number;
    cost_display: string;
    reset_at: string | null;
    request_count: number;
};

export default function AiUsageSummary({
    bookId,
    usage,
}: {
    bookId: number;
    usage: AiUsageSummary;
}) {
    const { t, i18n } = useTranslation('ai-dashboard');
    const [confirming, setConfirming] = useState(false);

    const totalTokens = usage.input_tokens + usage.output_tokens;
    const wordsProcessed = Math.round(totalTokens / 1.3 / 1000);

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

    const sinceDate = usage.reset_at
        ? new Date(usage.reset_at).toLocaleDateString(i18n.language, {
              month: 'short',
              day: 'numeric',
          })
        : null;

    return (
        <div className="flex flex-col gap-3">
            <div className="flex items-center justify-between">
                <SectionLabel>{t('usage.label')}</SectionLabel>
                <button
                    type="button"
                    onClick={handleReset}
                    onBlur={() => setConfirming(false)}
                    className="text-[12px] text-ink-faint transition-colors hover:text-ink"
                >
                    {confirming ? t('usage.confirmReset') : t('usage.reset')}
                </button>
            </div>
            <div className="flex gap-4">
                {/* Tokens */}
                <div className="flex flex-1 flex-col gap-2 rounded-lg bg-surface-card p-4">
                    <span className="font-sans text-[24px] font-semibold text-ink">
                        {formatCompactCount(totalTokens)}
                    </span>
                    <span className="text-[12px] text-ink-muted">
                        {t('usage.tokensUsed')}
                    </span>
                    <span className="text-[11px] text-ink-faint">
                        {t('usage.wordsProcessed', { count: wordsProcessed })}
                    </span>
                </div>

                {/* Cost */}
                <div className="flex flex-1 flex-col gap-2 rounded-lg bg-surface-card p-4">
                    <span className="font-sans text-[24px] font-semibold text-ink">
                        {usage.cost_display}
                    </span>
                    <span className="text-[12px] text-ink-muted">
                        {t('usage.estimatedCost')}
                    </span>
                    <span className="text-[11px] text-ai-green">
                        {t('usage.withinBudget')}
                    </span>
                </div>

                {/* Requests */}
                <div className="flex flex-1 flex-col gap-2 rounded-lg bg-surface-card p-4">
                    <span className="font-sans text-[24px] font-semibold text-ink">
                        {usage.request_count.toLocaleString(i18n.language)}
                    </span>
                    <span className="text-[12px] text-ink-muted">
                        {t('usage.apiRequests')}
                    </span>
                    {sinceDate && (
                        <span className="text-[11px] text-ink-faint">
                            {t('usage.since', { date: sinceDate })}
                        </span>
                    )}
                </div>
            </div>
        </div>
    );
}
