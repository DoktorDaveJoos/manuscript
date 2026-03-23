import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
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
        <div className="rounded-xl border border-border-light bg-surface-card p-6">
            <div className="flex items-start gap-4">
                <Check size={20} className="mt-0.5 shrink-0 text-accent" />

                <div className="flex flex-col gap-1.5">
                    <span className="text-[11px] font-medium tracking-[0.08em] text-accent uppercase">
                        {t('suggestedNext.label')}
                    </span>
                    <h3 className="font-serif text-lg font-semibold text-ink">
                        {suggestion.title}
                    </h3>
                    <p className="text-[13px] leading-[1.4] text-ink-soft">
                        {suggestion.description}
                    </p>
                    {suggestion.chapter_id && (
                        <Link
                            href={showChapter.url({
                                book: bookId,
                                chapter: suggestion.chapter_id,
                            })}
                            className="mt-1 text-[13px] font-medium text-accent"
                        >
                            {t('suggestedNext.openInEditor')} &rarr;
                        </Link>
                    )}
                </div>
            </div>
        </div>
    );
}
