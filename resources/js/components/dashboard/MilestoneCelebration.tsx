import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { jsonFetchHeaders } from '@/lib/utils';
import type { ManuscriptTarget } from '@/types/models';
import { dismissMilestone } from '@/actions/App/Http/Controllers/DashboardController';

export default function MilestoneCelebration({
    bookId,
    target,
}: {
    bookId: number;
    target: ManuscriptTarget;
}) {
    const { t, i18n } = useTranslation('dashboard');
    const [visible, setVisible] = useState(false);
    const [dismissed, setDismissed] = useState(false);

    useEffect(() => {
        // Fade in after mount
        const timer = setTimeout(() => setVisible(true), 50);
        return () => clearTimeout(timer);
    }, []);

    const handleDismiss = useCallback(async () => {
        setDismissed(true);
        try {
            await fetch(dismissMilestone.url(bookId), {
                method: 'PATCH',
                headers: jsonFetchHeaders(),
            });
        } catch {
            // Ignore errors
        }
    }, [bookId]);

    if (dismissed || !target.milestone_reached) return null;

    const reachedDate = target.milestone_reached_at
        ? new Date(target.milestone_reached_at).toLocaleDateString(i18n.language, {
              month: 'long',
              day: 'numeric',
              year: 'numeric',
          })
        : null;

    return (
        <div
            className={`rounded-xl border border-accent/20 bg-accent/5 px-8 py-8 transition-all duration-700 ${
                visible ? 'translate-y-0 opacity-100' : 'translate-y-2 opacity-0'
            }`}
        >
            <div className="flex items-start justify-between">
                <div>
                    <p className="font-serif text-[32px] leading-[40px] text-ink">
                        {t('milestone.words', { value: target.total_words.toLocaleString(i18n.language) })}
                    </p>
                    <p className="mt-0.5 font-serif text-[32px] leading-[40px] text-ink-muted">
                        {t('milestone.days', { count: target.days_writing })}
                    </p>
                </div>
                <button
                    type="button"
                    onClick={handleDismiss}
                    className="mt-1 text-xs text-ink-faint transition-colors hover:text-ink"
                >
                    {t('milestone.dismiss')}
                </button>
            </div>

            <div className="mt-6 flex gap-8">
                <div className="flex flex-col gap-0.5">
                    <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-faint">{t('milestone.target')}</span>
                    <span className="font-serif text-[18px] leading-[22px] text-ink">
                        {target.target_word_count?.toLocaleString(i18n.language)}
                    </span>
                </div>
                <div className="flex flex-col gap-0.5">
                    <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-faint">{t('milestone.written')}</span>
                    <span className="font-serif text-[18px] leading-[22px] text-ink">
                        {target.total_words.toLocaleString(i18n.language)}
                    </span>
                </div>
                <div className="flex flex-col gap-0.5">
                    <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-faint">
                        {t('milestone.daysWriting')}
                    </span>
                    <span className="font-serif text-[18px] leading-[22px] text-ink">{target.days_writing}</span>
                </div>
                {reachedDate && (
                    <div className="flex flex-col gap-0.5">
                        <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-faint">
                            {t('milestone.reached')}
                        </span>
                        <span className="font-serif text-[18px] leading-[22px] text-ink">{reachedDate}</span>
                    </div>
                )}
            </div>
        </div>
    );
}
