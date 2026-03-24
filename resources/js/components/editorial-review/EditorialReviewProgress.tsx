import { useTranslation } from 'react-i18next';
import type { EditorialReview } from '@/types/models';

export default function EditorialReviewProgress({
    review,
}: {
    review: EditorialReview;
}) {
    const { t } = useTranslation('editorial-review');

    const progress = review.progress;
    const phase = progress?.phase ?? 'pending';

    let phaseLabel: string;
    let progressPercent = 0;

    if (phase === 'refreshing') {
        phaseLabel = t('progress.refreshing');
        if (progress?.current_chapter && progress?.total_chapters) {
            progressPercent = Math.round(
                (progress.current_chapter / progress.total_chapters) * 30,
            );
        }
    } else if (phase === 'analyzing') {
        phaseLabel =
            progress?.current_chapter && progress?.total_chapters
                ? t('progress.analyzing', {
                      current: progress.current_chapter,
                      total: progress.total_chapters,
                  })
                : t('progress.analyzing', { current: 1, total: '?' });
        if (progress?.current_chapter && progress?.total_chapters) {
            progressPercent =
                30 +
                Math.round(
                    (progress.current_chapter / progress.total_chapters) * 40,
                );
        } else {
            progressPercent = 35;
        }
    } else if (phase === 'synthesizing') {
        phaseLabel = progress?.current_section
            ? t('progress.synthesizing', {
                  section: t(`section.${progress.current_section}`),
              })
            : t('progress.synthesizing', { section: '...' });
        progressPercent = 75;
    } else {
        phaseLabel = t('progress.pending');
        progressPercent = 5;
    }

    return (
        <div className="flex flex-1 flex-col items-center justify-center gap-6 px-12 py-10">
            <div className="flex size-12 items-center justify-center rounded-full bg-ink/[0.06]">
                <span className="inline-block size-5 animate-spin rounded-full border-2 border-ink-faint border-t-ink" />
            </div>
            <div className="flex w-full max-w-sm flex-col gap-3">
                <div className="flex flex-col items-center gap-1 text-center">
                    <span className="text-sm font-medium text-ink">
                        {phaseLabel}
                    </span>
                    <span className="text-xs text-ink-faint">
                        {progressPercent}%
                    </span>
                </div>
                <div className="h-1.5 overflow-hidden rounded bg-neutral-bg">
                    <div
                        className="h-full rounded bg-accent transition-all duration-500"
                        style={{ width: `${progressPercent}%` }}
                    />
                </div>
            </div>
        </div>
    );
}
