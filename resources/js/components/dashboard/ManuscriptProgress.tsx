import { useTranslation } from 'react-i18next';
import type { ManuscriptTarget } from '@/types/models';

export default function ManuscriptProgress({
    target,
}: {
    target: ManuscriptTarget;
}) {
    const { t, i18n } = useTranslation('dashboard');
    if (!target.target_word_count || target.milestone_reached) return null;

    return (
        <div>
            <div className="h-[3px] overflow-hidden rounded bg-neutral-bg">
                <div
                    className="h-full rounded bg-ink transition-all duration-700"
                    style={{ width: `${target.progress_percent}%` }}
                />
            </div>
            <div className="mt-2 flex items-center gap-3">
                <p className="text-[12px] text-ink-muted">
                    {target.total_words.toLocaleString(i18n.language)} /{' '}
                    {target.target_word_count.toLocaleString(i18n.language)}{' '}
                    <span className="text-ink-faint">
                        {t('manuscriptProgress.towardFirstDraft')}
                    </span>
                </p>
                <span className="rounded-full bg-neutral-bg px-2.5 py-0.5 text-[11px] font-medium text-ink-soft">
                    {target.progress_percent}% —{' '}
                    {t('manuscriptProgress.greatProgress')}
                </span>
            </div>
        </div>
    );
}
