import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AiInsights from '@/components/dashboard/AiInsights';
import AiPreparation from '@/components/dashboard/AiPreparation';
import AiUsageStats from '@/components/dashboard/AiUsageStats';
import HealthTimeline from '@/components/dashboard/HealthTimeline';
import ManuscriptProgress from '@/components/dashboard/ManuscriptProgress';
import MilestoneCelebration from '@/components/dashboard/MilestoneCelebration';
import SuggestedNext from '@/components/dashboard/SuggestedNext';
import WritingGoal from '@/components/dashboard/WritingGoal';
import WritingHeatmap from '@/components/dashboard/WritingHeatmap';
import Sidebar from '@/components/editor/Sidebar';
import ProFeatureLock from '@/components/ui/ProFeatureLock';
import { useAiFeatures } from '@/hooks/useAiFeatures';
import { useFreeTier } from '@/hooks/useFreeTier';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import type {
    AiPreparationStatus,
    AiUsage,
    Book,
    DashboardStats,
    HeatmapDay,
    HealthMetrics,
    HealthSnapshot,
    ManuscriptTarget,
    StatusCounts,
    SuggestedNext as SuggestedNextType,
    WritingGoalData,
} from '@/types/models';

function StatCard({
    label,
    value,
    suffix,
    locale,
}: {
    label: string;
    value: string | number;
    suffix?: string;
    locale: string;
}) {
    return (
        <div className="flex flex-1 flex-col items-center gap-2 rounded-xl border border-border-light bg-surface-card p-5 text-center">
            <span className="text-[11px] font-medium tracking-[0.08em] text-ink-muted uppercase">
                {label}
            </span>
            <div className="flex items-end gap-1">
                <span className="font-serif text-[32px] leading-[1] font-normal text-ink">
                    {typeof value === 'number'
                        ? value.toLocaleString(locale)
                        : value}
                </span>
                {suffix && (
                    <span className="pb-1 font-sans text-[13px] font-normal text-ink-muted">
                        {suffix}
                    </span>
                )}
            </div>
        </div>
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
        <div className="flex flex-col gap-2.5">
            <div className="flex items-center overflow-hidden rounded">
                {segments.map((s) => (
                    <div
                        key={s.label}
                        className={`h-[3px] ${s.color}`}
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
    health_metrics,
    suggested_next,
    ai_preparation,
    writing_goal,
    writing_heatmap,
    health_history,
    manuscript_target,
    ai_usage,
}: {
    book: Book;
    stats: DashboardStats;
    status_counts: StatusCounts;
    health_metrics: HealthMetrics | null;
    suggested_next: SuggestedNextType | null;
    ai_preparation?: AiPreparationStatus | null;
    writing_goal: WritingGoalData | null;
    writing_heatmap: HeatmapDay[];
    health_history: HealthSnapshot[];
    manuscript_target: ManuscriptTarget | null;
    ai_usage: AiUsage | null;
}) {
    const { t, i18n } = useTranslation('dashboard');
    const storylines = useSidebarStorylines();
    const { visible: aiVisible } = useAiFeatures();
    const { isFree } = useFreeTier();

    return (
        <>
            <Head title={`Dashboard — ${book.title}`} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar book={book} storylines={storylines} />

                <main className="flex min-w-0 flex-1 flex-col overflow-y-auto px-12 py-10">
                    <div className="flex w-full flex-col gap-7">
                        {/* Milestone Celebration */}
                        {manuscript_target?.milestone_reached &&
                            !manuscript_target.milestone_dismissed && (
                                <MilestoneCelebration
                                    bookId={book.id}
                                    target={manuscript_target}
                                />
                            )}

                        {/* Book Header */}
                        <div className="flex flex-col gap-1">
                            <h1 className="text-xl font-semibold tracking-[-0.01em] text-ink">
                                {book.title}
                            </h1>
                            {book.author && (
                                <p className="text-[13px] text-ink-muted">
                                    {t('header.by', { author: book.author })}
                                </p>
                            )}
                        </div>

                        {/* Today's Writing + Heatmap */}
                        {isFree ? (
                            <ProFeatureLock feature="dashboard">
                                <div className="flex h-[200px] gap-6 opacity-50">
                                    <div className="w-[360px] shrink-0 rounded-xl border border-border-light bg-surface-card" />
                                    <div className="min-w-0 flex-1 rounded-xl border border-border-light bg-surface-card" />
                                </div>
                            </ProFeatureLock>
                        ) : (
                            <>
                                <div className="flex gap-6">
                                    <div className="w-[360px] shrink-0">
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

                                {/* Manuscript Progress */}
                                <ManuscriptProgress
                                    target={manuscript_target!}
                                />
                            </>
                        )}

                        {/* AI Preparation */}
                        <AiPreparation
                            bookId={book.id}
                            initialStatus={ai_preparation ?? null}
                        />

                        {/* Chapter Status */}
                        {stats.chapter_count > 0 && (
                            <div className="flex flex-col gap-3">
                                <span className="text-[11px] font-medium tracking-[0.08em] text-ink-muted uppercase">
                                    {t(
                                        'sections.chapterStatus',
                                        'Chapter Status',
                                    )}
                                </span>
                                <ChapterStatusBar counts={status_counts} />
                            </div>
                        )}

                        {/* AI Insights */}
                        {aiVisible && health_metrics && (
                            <div className="flex flex-col gap-3">
                                <span className="text-[11px] font-medium tracking-[0.08em] text-ink-muted uppercase">
                                    {t('sections.aiInsights', 'AI Insights')}
                                </span>
                                <AiInsights healthMetrics={health_metrics} />
                            </div>
                        )}

                        {/* Health Timeline */}
                        {aiVisible && (
                            <div className="flex flex-col gap-3">
                                <span className="text-[11px] font-medium tracking-[0.08em] text-ink-muted uppercase">
                                    {t(
                                        'sections.healthTimeline',
                                        'Health Timeline',
                                    )}
                                </span>
                                <HealthTimeline history={health_history} />
                            </div>
                        )}

                        {/* Suggested Next */}
                        {aiVisible && suggested_next && (
                            <SuggestedNext
                                suggestion={suggested_next}
                                bookId={book.id}
                            />
                        )}

                        {/* Stats */}
                        <div className="flex flex-col gap-3">
                            <span className="text-[11px] font-medium tracking-[0.08em] text-ink-muted uppercase">
                                {t('sections.stats', 'Stats')}
                            </span>
                            <div className="flex gap-4">
                                <StatCard
                                    label={t('stats.words')}
                                    value={stats.total_words}
                                    locale={i18n.language}
                                />
                                <StatCard
                                    label={t('stats.pages')}
                                    value={stats.estimated_pages}
                                    suffix={t('stats.pagesEst')}
                                    locale={i18n.language}
                                />
                                <StatCard
                                    label={t('stats.readingTime')}
                                    value={stats.reading_time_minutes}
                                    suffix={t('stats.readingTimeMin')}
                                    locale={i18n.language}
                                />
                                <StatCard
                                    label={t('stats.chapters')}
                                    value={stats.chapter_count}
                                    locale={i18n.language}
                                />
                            </div>
                        </div>

                        {/* AI Usage Stats */}
                        {aiVisible && ai_usage && (
                            <div className="flex flex-col gap-3">
                                <span className="text-[11px] font-medium tracking-[0.08em] text-ink-muted uppercase">
                                    {t('sections.aiUsage', 'AI Usage & Costs')}
                                </span>
                                <AiUsageStats
                                    bookId={book.id}
                                    usage={ai_usage}
                                />
                            </div>
                        )}
                    </div>
                </main>
            </div>
        </>
    );
}
