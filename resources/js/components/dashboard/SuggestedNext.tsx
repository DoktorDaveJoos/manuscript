import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { show as showChapter } from '@/actions/App/Http/Controllers/ChapterController';
import Button from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import SectionLabel from '@/components/ui/SectionLabel';
import { formatTimeAgo } from '@/lib/utils';
import type { SuggestedNext as SuggestedNextType } from '@/types/models';

export default function SuggestedNext({
    suggestion,
    bookId,
}: {
    suggestion: SuggestedNextType;
    bookId: number;
}) {
    const { t } = useTranslation('dashboard');

    const description =
        suggestion.description ||
        (suggestion.last_edited_at
            ? t('suggestedNext.lastEdited', {
                  timeAgo: formatTimeAgo(
                      suggestion.last_edited_at,
                      t,
                      'aiInsights.timeAgo',
                  ),
              })
            : '');

    return (
        <div className="flex flex-col gap-3">
            <SectionLabel>{t('suggestedNext.pickUpLabel')}</SectionLabel>
            <Card className="flex items-center justify-between gap-4 p-6">
                <div className="flex min-w-0 flex-col gap-1">
                    <h3 className="text-base font-semibold text-ink">
                        {suggestion.title}
                    </h3>
                    {description && (
                        <p className="text-[13px] leading-[1.4] text-ink-muted">
                            {description}
                        </p>
                    )}
                </div>
                {suggestion.chapter_id && (
                    <Button asChild>
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
            </Card>
        </div>
    );
}
