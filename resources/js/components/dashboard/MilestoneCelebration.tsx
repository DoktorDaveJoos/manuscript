import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { dismissMilestone } from '@/actions/App/Http/Controllers/DashboardController';
import { Card } from '@/components/ui/Card';
import SectionLabel from '@/components/ui/SectionLabel';
import { jsonFetchHeaders } from '@/lib/utils';
import type { ManuscriptTarget } from '@/types/models';

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
        ? new Date(target.milestone_reached_at).toLocaleDateString(
              i18n.language,
              {
                  month: 'long',
                  day: 'numeric',
                  year: 'numeric',
              },
          )
        : null;

    return (
        <Card
            className={`px-9 py-8 transition-all duration-700 ${
                visible
                    ? 'translate-y-0 opacity-100'
                    : 'translate-y-2 opacity-0'
            }`}
        >
            <div className="flex items-end justify-between">
                <div>
                    <p className="font-serif text-[32px] leading-[1] font-semibold text-ink">
                        {t('milestone.words', {
                            value: target.total_words.toLocaleString(
                                i18n.language,
                            ),
                        })}
                    </p>
                    <p className="mt-1 font-serif text-[32px] leading-[1] font-normal text-ink-faint">
                        {t('milestone.days', { count: target.days_writing })}
                    </p>
                </div>

                <div className="flex items-end gap-8">
                    <div className="flex flex-col gap-0.5">
                        <SectionLabel variant="section">
                            {t('milestone.totalWords')}
                        </SectionLabel>
                        <span className="font-serif text-xl leading-[1] font-semibold text-ink">
                            {target.target_word_count?.toLocaleString(
                                i18n.language,
                            ) ?? '—'}
                        </span>
                    </div>
                    <div className="flex flex-col gap-0.5">
                        <SectionLabel variant="section">
                            {t('milestone.wordCount')}
                        </SectionLabel>
                        <span className="font-serif text-xl leading-[1] font-semibold text-ink">
                            {target.total_words.toLocaleString(i18n.language)}
                        </span>
                    </div>
                    <div className="flex flex-col gap-0.5">
                        <SectionLabel variant="section">
                            {t('milestone.dailyAvg')}
                        </SectionLabel>
                        <span className="font-serif text-xl leading-[1] font-semibold text-ink">
                            {target.days_writing > 0
                                ? Math.round(
                                      target.total_words / target.days_writing,
                                  ).toLocaleString(i18n.language)
                                : '—'}
                        </span>
                    </div>
                    <div className="flex flex-col gap-0.5">
                        <SectionLabel variant="section">
                            {t('milestone.lastEdit')}
                        </SectionLabel>
                        <span className="font-serif text-xl leading-[1] font-semibold text-ink">
                            {reachedDate ?? '—'}
                        </span>
                    </div>
                </div>

                <button
                    type="button"
                    onClick={handleDismiss}
                    className="mb-1 shrink-0 text-xs text-ink-faint transition-colors hover:text-ink"
                >
                    {t('milestone.dismiss')}
                </button>
            </div>
        </Card>
    );
}
