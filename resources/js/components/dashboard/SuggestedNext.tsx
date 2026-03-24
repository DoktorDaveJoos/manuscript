import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import type { SuggestedNext as SuggestedNextType } from '@/types/models';
import { show as showChapter } from '@/actions/App/Http/Controllers/ChapterController';

export default function SuggestedNext({
    suggestion,
    bookId,
}: {
    suggestion: SuggestedNextType;
    bookId: number;
}) {
    const { t } = useTranslation('dashboard');

    return (
        <div className="flex flex-col gap-3">
            <span className="text-[11px] font-medium tracking-[0.08em] text-ink-muted uppercase">
                {t('suggestedNext.label')}
            </span>
            <div className="flex items-center justify-between rounded-xl border border-border-light bg-surface-card p-6">
                <div className="flex flex-col gap-1">
                    <h3 className="text-base font-semibold text-ink">
                        {suggestion.title}
                    </h3>
                    <p className="text-[13px] text-ink-muted">
                        {suggestion.description}
                        {suggestion.chapter_id &&
                            ` ${t('suggestedNext.pickUpMessage')}`}
                    </p>
                </div>
                {suggestion.chapter_id && (
                    <Button variant="primary" size="sm" asChild>
                        <Link
                            href={showChapter.url({
                                book: bookId,
                                chapter: suggestion.chapter_id,
                            })}
                        >
                            {t('suggestedNext.continueWriting')}
                        </Link>
                    </Button>
                )}
            </div>
        </div>
    );
}
