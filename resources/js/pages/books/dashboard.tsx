import ActionsRow from '@/components/dashboard/ActionsRow';
import AiInsights from '@/components/dashboard/AiInsights';
import NormalizePreview from '@/components/dashboard/NormalizePreview';
import StoryBibleSummary from '@/components/dashboard/StoryBibleSummary';
import SuggestedNext from '@/components/dashboard/SuggestedNext';
import Sidebar from '@/components/editor/Sidebar';
import ProgressBar from '@/components/onboarding/ProgressBar';
import { useLicense } from '@/hooks/useLicense';
import type {
    AiPreparationStatus,
    Book,
    DashboardStats,
    HealthMetrics,
    StatusCounts,
    StoryBible,
    Storyline,
    SuggestedNext as SuggestedNextType,
} from '@/types/models';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

function StatCard({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="flex flex-1 flex-col gap-1 rounded-lg bg-white px-5 py-4">
            <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-[#B0AAA2]">{label}</span>
            <span className="font-serif text-[26px] leading-[30px] font-medium text-ink">
                {typeof value === 'number' ? value.toLocaleString('en-US') : value}
            </span>
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
    story_bible,
}: {
    book: Book & { storylines?: Storyline[] };
    stats: DashboardStats;
    status_counts: StatusCounts;
    health_metrics: HealthMetrics | null;
    suggested_next: SuggestedNextType | null;
    ai_preparation?: AiPreparationStatus | null;
    story_bible?: StoryBible | null;
}) {
    const { isActive: isLicensed } = useLicense();
    const [showNormalize, setShowNormalize] = useState(false);
    const storylines = book.storylines ?? [];

    return (
        <>
            <Head title={`Dashboard — ${book.title}`} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar book={book} storylines={storylines} />

                <main className="flex min-w-0 flex-1 flex-col items-center overflow-y-auto px-10 py-12">
                    <div className="flex w-[720px] flex-col gap-10">
                        {/* Book Header */}
                        <div>
                            <h1 className="font-serif text-[34px] leading-[40px] tracking-[-0.01em] text-[#2D2A26]">
                                {book.title}
                            </h1>
                            <div className="mt-2 flex items-center gap-1.5">
                                {book.author && (
                                    <span className="text-[14px] text-[#8A857D]">by {book.author}</span>
                                )}
                                {book.author && <span className="text-[14px] text-[#D1CCC4]">&middot;</span>}
                                <span className="text-[13px] text-[#B0AAA2]">
                                    {stats.total_words.toLocaleString('en-US')} words
                                </span>
                                <span className="text-[14px] text-[#D1CCC4]">&middot;</span>
                                <span className="text-[13px] text-[#B0AAA2]">
                                    {stats.chapter_count} chapter{stats.chapter_count !== 1 ? 's' : ''}
                                </span>
                            </div>
                        </div>

                        {/* Actions Row */}
                        <ActionsRow
                            bookId={book.id}
                            aiEnabled={book.ai_enabled}
                            aiPreparation={ai_preparation ?? null}
                            onNormalize={() => setShowNormalize(true)}
                            storylines={storylines}
                            licensed={isLicensed}
                        />

                        {/* Progress Bar */}
                        {stats.chapter_count > 0 && <ProgressBar counts={status_counts} />}

                        {/* AI Insights */}
                        {health_metrics && <AiInsights healthMetrics={health_metrics} />}

                        {/* Story Bible */}
                        {story_bible && <StoryBibleSummary storyBible={story_bible} />}

                        {/* Suggested Next */}
                        {suggested_next && <SuggestedNext suggestion={suggested_next} bookId={book.id} />}

                        {/* Stats Grid */}
                        <div className="flex gap-4">
                            <StatCard label="Words" value={stats.total_words} />
                            <StatCard label="Pages (est.)" value={stats.estimated_pages} />
                            <StatCard label="Reading Time (min)" value={stats.reading_time_minutes} />
                            <StatCard label="Chapters" value={stats.chapter_count} />
                        </div>
                    </div>
                </main>
            </div>

            {showNormalize && (
                <NormalizePreview bookId={book.id} onClose={() => setShowNormalize(false)} />
            )}
        </>
    );
}
