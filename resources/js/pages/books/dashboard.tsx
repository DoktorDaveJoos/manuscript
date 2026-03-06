import AiInsights from '@/components/dashboard/AiInsights';
import AiPreparation from '@/components/dashboard/AiPreparation';
import HealthTimeline from '@/components/dashboard/HealthTimeline';
import ManuscriptProgress from '@/components/dashboard/ManuscriptProgress';
import MilestoneCelebration from '@/components/dashboard/MilestoneCelebration';
import SuggestedNext from '@/components/dashboard/SuggestedNext';
import WritingGoal from '@/components/dashboard/WritingGoal';
import WritingHeatmap from '@/components/dashboard/WritingHeatmap';
import Sidebar from '@/components/editor/Sidebar';
import type {
    AiPreparationStatus,
    Book,
    DashboardStats,
    HeatmapDay,
    HealthMetrics,
    HealthSnapshot,
    ManuscriptTarget,
    StatusCounts,
    Storyline,
    SuggestedNext as SuggestedNextType,
    WritingGoalData,
} from '@/types/models';
import { Head } from '@inertiajs/react';

function StatCard({
    label,
    value,
    suffix,
}: {
    label: string;
    value: string | number;
    suffix?: string;
}) {
    return (
        <div className="flex flex-1 flex-col gap-1 rounded-lg bg-surface-card px-5 py-4">
            <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-faint">{label}</span>
            <span className="font-serif text-[26px] leading-[30px] font-medium text-ink">
                {typeof value === 'number' ? value.toLocaleString('en-US') : value}
                {suffix && <span className="ml-1 font-sans text-[12px] font-normal text-ink-muted">{suffix}</span>}
            </span>
        </div>
    );
}

function ChapterStatusBar({ counts }: { counts: StatusCounts }) {
    const total = counts.final + counts.revised + counts.draft;
    if (total === 0) return null;

    const segments: { count: number; label: string; color: string }[] = [];
    if (counts.final > 0) segments.push({ count: counts.final, label: 'final', color: 'bg-status-final' });
    if (counts.revised > 0) segments.push({ count: counts.revised, label: 'revised', color: 'bg-status-revised' });
    if (counts.draft > 0) segments.push({ count: counts.draft, label: 'draft', color: 'bg-status-draft' });

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
}: {
    book: Book & { storylines?: Storyline[] };
    stats: DashboardStats;
    status_counts: StatusCounts;
    health_metrics: HealthMetrics | null;
    suggested_next: SuggestedNextType | null;
    ai_preparation?: AiPreparationStatus | null;
    writing_goal: WritingGoalData;
    writing_heatmap: HeatmapDay[];
    health_history: HealthSnapshot[];
    manuscript_target: ManuscriptTarget;
}) {
    const storylines = book.storylines ?? [];

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
                                <p className="text-[14px] text-ink-muted">by {book.author}</p>
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
                            aiEnabled={book.ai_enabled}
                            initialStatus={ai_preparation ?? null}
                        />

                        {/* Chapter Status Bar */}
                        {stats.chapter_count > 0 && <ChapterStatusBar counts={status_counts} />}

                        {/* AI Insights */}
                        {health_metrics && <AiInsights healthMetrics={health_metrics} />}

                        {/* Health Timeline */}
                        <HealthTimeline history={health_history} />

                        {/* Suggested Next */}
                        {suggested_next && <SuggestedNext suggestion={suggested_next} bookId={book.id} />}

                        {/* Stats Grid */}
                        <div className="flex gap-4">
                            <StatCard label="Words" value={stats.total_words} />
                            <StatCard label="Pages" value={stats.estimated_pages} suffix="est." />
                            <StatCard label="Reading Time" value={stats.reading_time_minutes} suffix="min" />
                            <StatCard label="Chapters" value={stats.chapter_count} />
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
