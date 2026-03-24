import { Head, Link } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { index as aiDashboardIndex } from '@/actions/App/Http/Controllers/AiDashboardController';
import AiChatDrawer from '@/components/editor/AiChatDrawer';
import Sidebar from '@/components/editor/Sidebar';
import EditorialReviewEmptyState from '@/components/editorial-review/EditorialReviewEmptyState';
import EditorialReviewProgress from '@/components/editorial-review/EditorialReviewProgress';
import EditorialReviewReport from '@/components/editorial-review/EditorialReviewReport';
import Button from '@/components/ui/Button';
import { useEditorialReview } from '@/hooks/useEditorialReview';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import type { Book, Chapter, EditorialReview } from '@/types/models';

export default function EditorialReviewPage({
    book,
    reviews,
    latestReview,
    chapters,
}: {
    book: Book;
    reviews: EditorialReview[];
    latestReview: EditorialReview | null;
    chapters: Chapter[];
}) {
    const { t } = useTranslation('editorial-review');
    const storylines = useSidebarStorylines();
    const { review, isRunning, starting, error, handleStart, selectReview } =
        useEditorialReview(book.id, latestReview);

    const [chatContext, setChatContext] = useState<{
        reviewId: number;
        sectionType?: string;
        findingIndex?: number;
    } | null>(null);

    const handleDiscussFinding = (
        sectionType: string,
        findingIndex: number,
    ) => {
        if (review) {
            setChatContext({
                reviewId: review.id,
                sectionType,
                findingIndex,
            });
        }
    };

    const completedReviews = useMemo(
        () => reviews.filter((r) => r.status === 'completed'),
        [reviews],
    );

    return (
        <>
            <Head title={`Editorial Review — ${book.title}`} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar book={book} storylines={storylines} />

                <main className="flex min-w-0 flex-1 flex-col overflow-y-auto">
                    <div className="flex flex-col gap-4 px-12 pt-10">
                        <div className="flex items-start justify-between">
                            <div className="flex flex-col gap-1">
                                <div className="flex items-center gap-2">
                                    <Sparkles
                                        size={16}
                                        className="text-ink-muted"
                                    />
                                    <h1 className="text-xl font-semibold tracking-[-0.01em] text-ink">
                                        {t('title')}
                                    </h1>
                                </div>
                                <p className="text-[13px] text-ink-muted">
                                    {t('subtitle')}
                                </p>
                            </div>
                        </div>

                        <div className="flex gap-1 border-b border-border-light">
                            <Link
                                href={aiDashboardIndex.url(book)}
                                className="border-b-2 border-transparent px-3 pb-2 text-[13px] font-medium text-ink-muted transition-colors hover:text-ink"
                            >
                                {t('tabs.dashboard')}
                            </Link>
                            <span className="border-b-2 border-ink px-3 pb-2 text-[13px] font-medium text-ink">
                                {t('tabs.editorialReview')}
                            </span>
                        </div>
                    </div>

                    <div className="flex flex-1 flex-col px-12 py-6">
                        {error && (
                            <div className="mb-4 rounded-lg bg-delete-bg px-4 py-3 text-[13px] text-delete">
                                {error}
                            </div>
                        )}

                        {review?.status === 'failed' && review && (
                            <div className="flex flex-1 flex-col items-center justify-center gap-4">
                                <p className="text-sm font-medium text-delete">
                                    {t('failed.title')}
                                </p>
                                {review.error_message && (
                                    <p className="max-w-md text-center text-[13px] text-ink-muted">
                                        {review.error_message}
                                    </p>
                                )}
                                <Button
                                    variant="primary"
                                    onClick={handleStart}
                                    disabled={starting}
                                >
                                    {t('failed.retry')}
                                </Button>
                            </div>
                        )}

                        {!review && (
                            <EditorialReviewEmptyState
                                onStart={handleStart}
                                starting={starting}
                            />
                        )}

                        {isRunning && review && (
                            <EditorialReviewProgress review={review} />
                        )}

                        {review?.status === 'completed' && review && (
                            <EditorialReviewReport
                                review={review}
                                reviews={completedReviews}
                                chapters={chapters}
                                onSelectReview={selectReview}
                                onStartNew={handleStart}
                                starting={starting}
                                onDiscussFinding={handleDiscussFinding}
                            />
                        )}
                    </div>
                </main>

                {chatContext && (
                    <AiChatDrawer
                        book={book}
                        onClose={() => setChatContext(null)}
                        editorialReview={chatContext}
                    />
                )}
            </div>
        </>
    );
}
