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
import { useAiFeatures } from '@/hooks/useAiFeatures';
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
        <div className="flex flex-1 flex-col gap-1 rounded-lg bg-surface-card px-5 py-4">
            <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-faint">{label}</span>
            <span className="font-serif text-[26px] leading-[30px] font-medium text-ink">
                {typeof value === 'number' ? value.toLocaleString(locale) : value}
                {suffix && <span className="ml-1 font-sans text-[12px] font-normal text-ink-muted">{suffix}</span>}
            </span>
        </div>
    );
}

function ChapterStatusBar({ counts }: { counts: StatusCounts }) {
    const { t } = useTranslation('dashboard');
    const total = counts.final + counts.revised + counts.draft;
    if (total === 0) return null;

    const segments: { count: number; label: string; color: string }[] = [];
    if (counts.final > 0) segments.push({ count: counts.final, label: t('statusBar.final'), color: 'bg-status-final' });
    if (counts.revised > 0) segments.push({ count: counts.revised, label: t('statusBar.revised'), color: 'bg-status-revised' });
    if (counts.draft > 0) segments.push({ count: counts.draft, label: t('statusBar.draft'), color: 'bg-status-draft' });

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-center overflow-hidden rounded-[4px]">
                {segments.map((s) => (
                    <div
                        key={s.label}
                        className={`h-2 ${s.color}`}
                        style={{ flexGrow: s.count, flexShrink: 1, flexBasis: '0%' }}
                    />
                ))}
            </div>
            <div className="flex items-center gap-4">
                {segments.map((s) => (
                    <span key={s.label} className="flex items-center gap-1.5 text-[12px] text-ink-muted">
                        <span className={`size-2 rounded-[2px] ${s.color}`} />
                        {s.count} {s.label}
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
    writing_goal: WritingGoalData;
    writing_heatmap: HeatmapDay[];
    health_history: HealthSnapshot[];
    manuscript_target: ManuscriptTarget;
    ai_usage: AiUsage;
}) {
    const { t, i18n } = useTranslation('dashboard');
    const storylines = useSidebarStorylines();
    const { visible: aiVisible } = useAiFeatures();

    return (
        <>
            <Head title={`Dashboard — ${book.title}`} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar book={book} storylines={storylines} />

                <main className="flex min-w-0 flex-1 flex-col items-center overflow-y-auto px-10 py-12">
                    <div className="flex w-[720px] flex-col gap-10">
                        {/* Milestone Celebration */}
                        {manuscript_target.milestone_reached && !manuscript_target.milestone_dismissed && (
                            <MilestoneCelebration bookId={book.id} target={manuscript_target} />
                        )}

                        {/* Book Header */}
                        <div className="flex flex-col gap-[6px]">
                            <h1 className="font-serif text-[34px] leading-[40px] tracking-[-0.01em] text-ink">
                                {book.title}
                            </h1>
                            {book.author && (
                                <p className="text-[14px] text-ink-muted">{t('header.by', { author: book.author })}</p>
                            )}
                        </div>

                        {/* Today's Writing */}
                        <WritingGoal
                            bookId={book.id}
                            writingGoal={writing_goal}
                            targetWordCount={manuscript_target.target_word_count}
                        />

                        {/* Writing Heatmap */}
                        <WritingHeatmap
                            heatmap={writing_heatmap}
                            dailyGoal={writing_goal.daily_word_count_goal}
                        />

                        {/* Manuscript Progress */}
                        <ManuscriptProgress target={manuscript_target} />

                        {/* AI Preparation */}
                        <AiPreparation
                            bookId={book.id}
                            initialStatus={ai_preparation ?? null}
                        />

                        {/* Chapter Status Bar */}
                        {stats.chapter_count > 0 && <ChapterStatusBar counts={status_counts} />}

                        {/* AI Insights */}
                        {aiVisible && health_metrics && <AiInsights healthMetrics={health_metrics} />}

                        {/* Health Timeline */}
                        <HealthTimeline history={health_history} />

                        {/* AI Usage Stats */}
                        {aiVisible && <AiUsageStats bookId={book.id} usage={ai_usage} />}

                        {/* Suggested Next */}
                        {aiVisible && suggested_next && <SuggestedNext suggestion={suggested_next} bookId={book.id} />}

                        {/* Stats Grid */}
                        <div className="flex gap-4">
                            <StatCard label={t('stats.words')} value={stats.total_words} locale={i18n.language} />
                            <StatCard label={t('stats.pages')} value={stats.estimated_pages} suffix={t('stats.pagesEst')} locale={i18n.language} />
                            <StatCard label={t('stats.readingTime')} value={stats.reading_time_minutes} suffix={t('stats.readingTimeMin')} locale={i18n.language} />
                            <StatCard label={t('stats.chapters')} value={stats.chapter_count} locale={i18n.language} />
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
