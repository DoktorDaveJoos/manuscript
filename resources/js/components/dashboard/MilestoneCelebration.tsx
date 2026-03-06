import { dismissMilestone } from '@/actions/App/Http/Controllers/DashboardController';
import { jsonFetchHeaders } from '@/lib/utils';
import type { ManuscriptTarget } from '@/types/models';
import { useCallback, useEffect, useState } from 'react';

export default function MilestoneCelebration({
    bookId,
    target,
}: {
    bookId: number;
    target: ManuscriptTarget;
}) {
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
        ? new Date(target.milestone_reached_at).toLocaleDateString('en-US', {
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
                        {target.total_words.toLocaleString('en-US')} words.
                    </p>
                    <p className="mt-0.5 font-serif text-[32px] leading-[40px] text-ink-muted">
                        {target.days_writing} day{target.days_writing !== 1 ? 's' : ''}.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={handleDismiss}
                    className="mt-1 text-xs text-ink-faint transition-colors hover:text-ink"
                >
                    Dismiss
                </button>
            </div>

            <div className="mt-6 flex gap-8">
                <div className="flex flex-col gap-0.5">
                    <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-faint">Target</span>
                    <span className="font-serif text-[18px] leading-[22px] text-ink">
                        {target.target_word_count?.toLocaleString('en-US')}
                    </span>
                </div>
                <div className="flex flex-col gap-0.5">
                    <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-faint">Written</span>
                    <span className="font-serif text-[18px] leading-[22px] text-ink">
                        {target.total_words.toLocaleString('en-US')}
                    </span>
                </div>
                <div className="flex flex-col gap-0.5">
                    <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-faint">
                        Days writing
                    </span>
                    <span className="font-serif text-[18px] leading-[22px] text-ink">{target.days_writing}</span>
                </div>
                {reachedDate && (
                    <div className="flex flex-col gap-0.5">
                        <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-faint">
                            Reached
                        </span>
                        <span className="font-serif text-[18px] leading-[22px] text-ink">{reachedDate}</span>
                    </div>
                )}
            </div>
        </div>
    );
}
