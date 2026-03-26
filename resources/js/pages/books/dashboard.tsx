import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import ManuscriptProgress from '@/components/dashboard/ManuscriptProgress';
import SuggestedNext from '@/components/dashboard/SuggestedNext';
import WritingGoal from '@/components/dashboard/WritingGoal';
import WritingHeatmap from '@/components/dashboard/WritingHeatmap';
import Sidebar from '@/components/editor/Sidebar';
import Button from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import PageHeader from '@/components/ui/PageHeader';
import ProFeatureLock from '@/components/ui/ProFeatureLock';
import SectionLabel from '@/components/ui/SectionLabel';
import { useFreeTier } from '@/hooks/useFreeTier';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import type {
    Book,
    DashboardStats,
    HeatmapDay,
    ManuscriptTarget,
    StatusCounts,
    SuggestedNext as SuggestedNextType,
    WritingGoalData,
} from '@/types/models';

function StatCard({
    label,
    value,
    suffix,
}: {
    label: string;
    value: string | number;
    suffix?: string;
}) {
    const { i18n } = useTranslation();
    return (
        <Card className="flex flex-1 flex-col items-center gap-2 p-6 text-center">
            <SectionLabel>{label}</SectionLabel>
            <div className="flex items-end gap-1">
                <span className="font-serif text-[32px] leading-[1] font-semibold text-ink">
                    {typeof value === 'number'
                        ? value.toLocaleString(i18n.language)
                        : value}
                </span>
                {suffix && (
                    <span className="pb-1 font-sans text-[13px] font-normal text-ink-muted">
                        {suffix}
                    </span>
                )}
            </div>
        </Card>
    );
}

function ChapterStatusBar({ counts }: { counts: StatusCounts }) {
    const { t } = useTranslation('dashboard');
    const total = counts.final + counts.revised + counts.draft;
    if (total === 0) return null;

    const segments: { count: number; label: string; color: string }[] = [];
    if (counts.final > 0)
        segments.push({
            count: counts.final,
            label: t('statusBar.final'),
            color: 'bg-status-final',
        });
    if (counts.revised > 0)
        segments.push({
            count: counts.revised,
            label: t('statusBar.revised'),
            color: 'bg-status-revised',
        });
    if (counts.draft > 0)
        segments.push({
            count: counts.draft,
            label: t('statusBar.draft'),
            color: 'bg-status-draft',
        });

    return (
        <div className="flex flex-col gap-3">
            <div className="flex items-center overflow-hidden rounded-sm">
                {segments.map((s) => (
                    <div
                        key={s.label}
                        className={`h-1 ${s.color}`}
                        style={{
                            flexGrow: s.count,
                            flexShrink: 1,
                            flexBasis: '0%',
                        }}
                    />
                ))}
            </div>
            <div className="flex items-center gap-4">
                {segments.map((s, i) => (
                    <span key={s.label} className="contents">
                        {i > 0 && (
                            <span className="text-[12px] font-medium text-ink-faint">
                                &middot;
                            </span>
                        )}
                        <span className="flex items-center gap-1.5 text-[12px] text-ink-soft">
                            <span
                                className={`size-2 rounded-full ${s.color}`}
                            />
                            {s.count} {s.label}
                        </span>
                    </span>
                ))}
            </div>
        </div>
    );
}

export default function Dashboard({
    book,
    stats,
    status_counts,
    suggested_next,
    writing_goal,
    writing_heatmap,
    manuscript_target,
}: {
    book: Book;
    stats: DashboardStats;
    status_counts: StatusCounts;
    suggested_next: SuggestedNextType | null;
    writing_goal: WritingGoalData | null;
    writing_heatmap: HeatmapDay[];
    manuscript_target: ManuscriptTarget | null;
}) {
    const { t } = useTranslation('dashboard');
    const storylines = useSidebarStorylines();
    const { isFree } = useFreeTier();

    return (
        <>
            <Head title={`Dashboard — ${book.title}`} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar book={book} storylines={storylines} />

                <main className="flex min-w-0 flex-1 flex-col overflow-y-auto p-12">
                    <div className="flex w-full flex-col gap-8">
                        {/* Book Header */}
                        <PageHeader
                            title={book.title}
                            subtitle={
                                book.author
                                    ? t('header.by', {
                                          author: book.author,
                                      })
                                    : undefined
                            }
                        >
                            {writing_goal && writing_goal.streak > 0 && (
                                <p className="text-sm text-ink-soft">
                                    {t('header.greeting', {
                                        count: writing_goal.streak,
                                    })}
                                </p>
                            )}
                        </PageHeader>

                        {/* Stats Row */}
                        <div className="flex gap-4">
                            <StatCard
                                label={t('stats.words')}
                                value={stats.total_words}
                            />
                            <StatCard
                                label={t('stats.pages')}
                                value={stats.estimated_pages}
                                suffix={t('stats.pagesEst')}
                            />
                            <StatCard
                                label={t('stats.readingTime')}
                                value={stats.reading_time_minutes}
                                suffix={t('stats.readingTimeMin')}
                            />
                            <StatCard
                                label={t('stats.chapters')}
                                value={stats.chapter_count}
                            />
                        </div>

                        <div className="h-px bg-border-light" />

                        {/* Today's Writing + Heatmap + Manuscript Progress */}
                        {isFree ? (
                            <ProFeatureLock feature="dashboard">
                                <div className="flex h-[200px] gap-6 opacity-50">
                                    <Card className="w-[340px] shrink-0" />
                                    <Card className="min-w-0 flex-1" />
                                </div>
                            </ProFeatureLock>
                        ) : (
                            <>
                                <div className="flex gap-6">
                                    <div className="w-[340px] shrink-0">
                                        <WritingGoal
                                            bookId={book.id}
                                            writingGoal={writing_goal!}
                                            targetWordCount={
                                                manuscript_target!
                                                    .target_word_count
                                            }
                                        />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <WritingHeatmap
                                            heatmap={writing_heatmap}
                                            dailyGoal={
                                                writing_goal!
                                                    .daily_word_count_goal
                                            }
                                        />
                                    </div>
                                </div>

                                <ManuscriptProgress
                                    target={manuscript_target!}
                                />
                            </>
                        )}

                        <div className="h-px bg-border-light" />

                        {/* Chapter Progress */}
                        {stats.chapter_count > 0 && (
                            <>
                                <div className="flex flex-col gap-3">
                                    <SectionLabel>
                                        {t('sections.chapterProgress')}
                                    </SectionLabel>
                                    <ChapterStatusBar counts={status_counts} />
                                </div>
                                <div className="h-px bg-border-light" />
                            </>
                        )}

                        {/* Suggested Next */}
                        {suggested_next && (
                            <SuggestedNext
                                suggestion={suggested_next}
                                bookId={book.id}
                            />
                        )}

                        {/* Feedback Section */}
                        <div className="flex flex-col items-center gap-4 rounded-lg bg-surface p-6">
                            <h2 className="font-serif text-xl font-semibold text-ink">
                                {t('feedback.title')}
                            </h2>
                            <p className="text-center text-sm text-ink-soft">
                                {t('feedback.description')}
                            </p>
                            <Button asChild>
                                <a
                                    href="https://getmanuscript.app/app/feedback"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    {t('feedback.button')}
                                </a>
                            </Button>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
