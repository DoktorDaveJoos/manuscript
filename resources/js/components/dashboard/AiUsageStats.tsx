import { router } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { formatCompactCount, jsonFetchHeaders } from '@/lib/utils';
import type { AiUsage } from '@/types/models';
import { resetUsage } from '@/actions/App/Http/Controllers/AiController';

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
                <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">
                    {t('aiUsage.title')}
                </span>
                <button
                    type="button"
                    onClick={handleReset}
                    onBlur={() => setConfirming(false)}
                    className="text-[12px] text-ink-faint transition-colors hover:text-ink"
                >
                    {confirming ? t('aiUsage.confirmReset') : t('aiUsage.reset')}
                </button>
            </div>

            <div className="flex gap-4">
                <div className="flex flex-1 flex-col gap-1 rounded-lg bg-surface-card px-5 py-4">
                    <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-faint">{t('aiUsage.input')}</span>
                    <span className="font-serif text-[22px] leading-[26px] font-medium text-ink">
                        {formatCompactCount(usage.input_tokens)}
                    </span>
                </div>
                <div className="flex flex-1 flex-col gap-1 rounded-lg bg-surface-card px-5 py-4">
                    <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-faint">{t('aiUsage.output')}</span>
                    <span className="font-serif text-[22px] leading-[26px] font-medium text-ink">
                        {formatCompactCount(usage.output_tokens)}
                    </span>
                </div>
                <div className="flex flex-1 flex-col gap-1 rounded-lg bg-surface-card px-5 py-4">
                    <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-faint">
                        {t('aiUsage.estCost')}
                    </span>
                    <span className="font-serif text-[22px] leading-[26px] font-medium text-ink">
                        {usage.cost_display}
                    </span>
                </div>
            </div>

            {usage.reset_at && (
                <span className="text-[12px] text-ink-faint">
                    {t('aiUsage.trackingSince', { date: new Date(usage.reset_at).toLocaleDateString(i18n.language, { month: 'short', day: 'numeric', year: 'numeric' }) })}
                </span>
            )}
        </div>
    );
}
