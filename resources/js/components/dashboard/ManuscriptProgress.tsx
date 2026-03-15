import { useTranslation } from 'react-i18next';
import type { ManuscriptTarget } from '@/types/models';

export default function ManuscriptProgress({ target }: { target: ManuscriptTarget }) {
    const { t, i18n } = useTranslation('dashboard');
    if (!target.target_word_count || target.milestone_reached) return null;

    return (
        <div>
            <div className="h-[3px] overflow-hidden rounded-[2px] bg-neutral-bg">
                <div
                    className="h-full rounded-[2px] bg-gradient-to-r from-accent to-accent-dark transition-all duration-700"
                    style={{ width: `${target.progress_percent}%` }}
                />
            </div>
            <p className="mt-2 text-[12px] text-ink-muted">
                {target.total_words.toLocaleString(i18n.language)} / {target.target_word_count.toLocaleString(i18n.language)}{' '}
                <span className="text-ink-faint">{t('manuscriptProgress.towardFirstDraft')}</span>
            </p>
        </div>
    );
}
