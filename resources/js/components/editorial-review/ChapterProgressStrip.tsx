import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { show as chapterShow } from '@/actions/App/Http/Controllers/ChapterController';
import SectionLabel from '@/components/ui/SectionLabel';
import { buildChapterStats } from '@/lib/editorial-constants';
import { cn } from '@/lib/utils';
import type { Chapter, EditorialReviewSection } from '@/types/models';

function ProgressRing({
    resolved,
    total,
    size = 24,
}: {
    resolved: number;
    total: number;
    size?: number;
}) {
    const strokeWidth = 2.5;
    const radius = (size - strokeWidth) / 2;
    const circumference = 2 * Math.PI * radius;
    const progress = total > 0 ? resolved / total : 0;
    const offset = circumference * (1 - progress);

    return (
        <svg width={size} height={size} className="-rotate-90">
            <circle
                cx={size / 2}
                cy={size / 2}
                r={radius}
                fill="none"
                stroke="currentColor"
                strokeWidth={strokeWidth}
                className="text-border-light"
            />
            {total > 0 && (
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={strokeWidth}
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                    strokeLinecap="round"
                    className={cn(
                        progress === 1 ? 'text-status-final' : 'text-accent',
                    )}
                />
            )}
        </svg>
    );
}

export default function ChapterProgressStrip({
    chapters,
    sections,
    resolvedSet,
    bookId,
}: {
    chapters: Chapter[];
    sections: EditorialReviewSection[];
    resolvedSet: Set<string>;
    bookId: number;
}) {
    const { t } = useTranslation('editorial-review');
    const chapterStats = useMemo(
        () => buildChapterStats(sections, resolvedSet),
        [sections, resolvedSet],
    );

    if (chapterStats.size === 0) return null;

    return (
        <div className="flex flex-col gap-2">
            <SectionLabel>{t('chapterStrip.title')}</SectionLabel>
            <div className="flex gap-2 overflow-x-auto pb-1">
                {chapters.map((chapter) => {
                    const stats = chapterStats.get(chapter.id);
                    const total = stats?.total ?? 0;
                    const resolved = stats?.resolved ?? 0;
                    const hasFindings = total > 0;

                    return (
                        <a
                            key={chapter.id}
                            href={chapterShow.url({
                                book: bookId,
                                chapter: chapter.id,
                            })}
                            className={cn(
                                'flex shrink-0 flex-col items-center gap-1 rounded-lg border px-3 py-2 transition-colors',
                                hasFindings
                                    ? 'hover:border-border-medium border-border-light bg-surface-card'
                                    : 'border-transparent bg-neutral-bg opacity-50',
                            )}
                        >
                            <ProgressRing resolved={resolved} total={total} />
                            <span className="text-[11px] font-medium text-ink">
                                {chapter.reader_order + 1}
                            </span>
                            {hasFindings && (
                                <span className="text-[10px] text-ink-faint">
                                    {resolved}/{total}
                                </span>
                            )}
                        </a>
                    );
                })}
            </div>
        </div>
    );
}
