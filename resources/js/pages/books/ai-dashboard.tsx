import { Head, Link } from '@inertiajs/react';
import { CheckCircle, Sparkles } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { index as editorialReviewIndex } from '@/actions/App/Http/Controllers/EditorialReviewController';
import AiDashboardEmptyState from '@/components/ai-dashboard/AiDashboardEmptyState';
import AiUsageSummary from '@/components/ai-dashboard/AiUsageSummary';
import AnalyzedChaptersTable from '@/components/ai-dashboard/AnalyzedChaptersTable';
import CommandCenter from '@/components/ai-dashboard/CommandCenter';
import ManuscriptHealthCard from '@/components/ai-dashboard/ManuscriptHealthCard';
import Sidebar from '@/components/editor/Sidebar';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import type { AiPreparationStatus, Book, HealthMetric } from '@/types/models';

type AiUsageData = {
    input_tokens: number;
    output_tokens: number;
    cost_display: string;
    reset_at: string | null;
    request_count: number;
};

type AnalyzedChaptersData = {
    data: {
        id: number;
        title: string;
        reader_order: number;
        score: number | null;
        word_count: number;
        estimated_pages: number;
        findings_count: number;
        storyline_name: string | null;
        is_analyzed: boolean;
    }[];
    current_page: number;
    last_page: number;
    total: number;
};

export default function AiDashboardPage({
    book,
    is_prepared,
    ai_preparation,
    health_metrics,
    analyzed_chapters,
    ai_usage,
}: {
    book: Book;
    is_prepared: boolean;
    ai_preparation: AiPreparationStatus | null;
    health_metrics: { composite_score: number; metrics: HealthMetric[] } | null;
    analyzed_chapters: AnalyzedChaptersData | null;
    ai_usage: AiUsageData;
}) {
    const { t } = useTranslation('ai-dashboard');
    const storylines = useSidebarStorylines();

    return (
        <>
            <Head title={`AI Dashboard — ${book.title}`} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar book={book} storylines={storylines} />

                <main className="flex min-w-0 flex-1 flex-col overflow-y-auto">
                    {/* Page header */}
                    <div className="flex flex-col gap-4 px-12 pt-10">
                        <div className="flex items-start justify-between">
                            <div className="flex flex-col gap-1">
                                <div className="flex items-center gap-2">
                                    <Sparkles
                                        size={16}
                                        className="text-ink-muted"
                                    />
                                    <h1 className="font-serif text-[24px] font-semibold text-ink">
                                        {t('title')}
                                    </h1>
                                </div>
                                <p className="text-[14px] text-ink-muted">
                                    {t('subtitle')}
                                </p>
                            </div>
                            {is_prepared && (
                                <div className="flex items-center gap-1.5 rounded-full bg-neutral-bg px-3 py-1">
                                    <CheckCircle
                                        size={14}
                                        className="text-ink-muted"
                                    />
                                    <span className="text-[12px] font-medium text-ink-muted">
                                        {t('badge.prepared')}
                                    </span>
                                </div>
                            )}
                        </div>

                        {/* Tab nav */}
                        <div className="flex gap-1 border-b border-border-light">
                            <span className="border-b-2 border-ink px-3 pb-2 text-[13px] font-medium text-ink">
                                {t('tabs.dashboard')}
                            </span>
                            <Link
                                href={editorialReviewIndex.url(book)}
                                className="border-b-2 border-transparent px-3 pb-2 text-[13px] font-medium text-ink-muted transition-colors hover:text-ink"
                            >
                                {t('tabs.editorialReview')}
                            </Link>
                        </div>
                    </div>

                    {/* Content */}
                    <div className="flex flex-1 flex-col p-8">
                        {!is_prepared ? (
                            <AiDashboardEmptyState
                                bookId={book.id}
                                initialStatus={ai_preparation}
                            />
                        ) : (
                            <div className="flex flex-col gap-6">
                                <CommandCenter
                                    bookId={book.id}
                                    initialStatus={ai_preparation}
                                />

                                <div className="border-t border-border-light" />

                                {health_metrics && (
                                    <>
                                        <ManuscriptHealthCard
                                            compositeScore={
                                                health_metrics.composite_score
                                            }
                                            metrics={health_metrics.metrics}
                                        />
                                        <div className="border-t border-border-light" />
                                    </>
                                )}

                                {analyzed_chapters && (
                                    <>
                                        <AnalyzedChaptersTable
                                            chapters={analyzed_chapters}
                                        />
                                        <div className="border-t border-border-light" />
                                    </>
                                )}

                                <AiUsageSummary
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
