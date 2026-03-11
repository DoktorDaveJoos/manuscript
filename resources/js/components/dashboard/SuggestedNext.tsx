import { Link } from '@inertiajs/react';
import { CheckCircle } from '@phosphor-icons/react';
import { useTranslation } from 'react-i18next';
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
        <div className="flex items-start gap-4">
            <CheckCircle size={20} weight="fill" className="mt-0.5 shrink-0 text-accent" />

            <div className="flex flex-col gap-1">
                <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-accent">
                    {t('suggestedNext.label')}
                </span>
                <h3 className="font-serif text-[20px] font-medium leading-[26px] text-ink">
                    {suggestion.title}
                </h3>
                <p className="text-[13px] leading-[20px] text-ink-muted">{suggestion.description}</p>
                {suggestion.chapter_id && (
                    <Link
                        href={showChapter.url({ book: bookId, chapter: suggestion.chapter_id })}
                        className="mt-1 text-[13px] font-medium text-accent"
                    >
                        {t('suggestedNext.openInEditor')} &rarr;
                    </Link>
                )}
            </div>
        </div>
    );
}
