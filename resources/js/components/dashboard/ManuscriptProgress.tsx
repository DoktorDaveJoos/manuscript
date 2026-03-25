import { useTranslation } from 'react-i18next';
import type { ManuscriptTarget } from '@/types/models';

function getProgressKey(percent: number): string {
    if (percent <= 10) return 'manuscriptProgress.label.starting';
    if (percent <= 25) return 'manuscriptProgress.label.building';
    if (percent <= 50) return 'manuscriptProgress.label.great';
    if (percent <= 75) return 'manuscriptProgress.label.halfway';
    return 'manuscriptProgress.label.almostThere';
}

export default function ManuscriptProgress({
    target,
}: {
    target: ManuscriptTarget;
}) {
    const { t, i18n } = useTranslation('dashboard');
    if (!target.target_word_count || target.milestone_reached) return null;

    return (
        <div className="flex flex-col gap-2">
            <div className="h-1 overflow-hidden rounded-sm bg-neutral-bg">
                <div
                    className="h-full rounded-sm bg-ink transition-all duration-700"
                    style={{ width: `${target.progress_percent}%` }}
                />
            </div>
            <div className="flex items-center gap-1">
                <span className="text-[12px] text-ink-muted">
                    {target.total_words.toLocaleString(i18n.language)} /{' '}
                    {target.target_word_count.toLocaleString(i18n.language)}
                </span>
                <span className="text-[12px] text-ink-faint">
                    {t('manuscriptProgress.towardFirstDraft')}
                </span>
                <span className="ml-auto rounded-full bg-neutral-bg px-2 py-1 text-[11px] font-semibold text-ink-soft">
                    {target.progress_percent}% &mdash;{' '}
                    {t(getProgressKey(target.progress_percent))}
                </span>
            </div>
        </div>
    );
}
