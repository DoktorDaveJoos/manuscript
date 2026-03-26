import { Head, Link } from '@inertiajs/react';
import { Sparkles, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { index as aiDashboardIndex } from '@/actions/App/Http/Controllers/AiDashboardController';
import AiChatDrawer from '@/components/editor/AiChatDrawer';
import Sidebar from '@/components/editor/Sidebar';
import EditorialReviewEmptyState from '@/components/editorial-review/EditorialReviewEmptyState';
import EditorialReviewProgress from '@/components/editorial-review/EditorialReviewProgress';
import EditorialReviewReport from '@/components/editorial-review/EditorialReviewReport';
import { Alert } from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import PageHeader from '@/components/ui/PageHeader';
import SlidePanel from '@/components/ui/SlidePanel';
import { useEditorialReview } from '@/hooks/useEditorialReview';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import type {
    Book,
    Chapter,
    EditorialReview,
    EditorialSectionType,
    FindingSeverity,
    OnDiscussFinding,
} from '@/types/models';

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
    const {
        review,
        isRunning,
        starting,
        error,
        handleStart,
        selectReview,
        updateResolved,
    } = useEditorialReview(book.id, latestReview);

    const [alertDismissed, setAlertDismissed] = useState(false);
    const [chatContext, setChatContext] = useState<{
        reviewId: number;
        sectionType?: EditorialSectionType;
        findingIndex?: number;
        findingDescription?: string;
        findingSeverity?: FindingSeverity;
        sectionLabel?: string;
    } | null>(null);

    const handleDiscussFinding: OnDiscussFinding = (
        sectionType,
        findingIndex,
        finding,
    ) => {
        if (review) {
            setChatContext({
                reviewId: review.id,
                sectionType,
                findingIndex,
                findingDescription: finding.description,
                findingSeverity: finding.severity,
                sectionLabel: finding.sectionLabel,
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
                        <PageHeader
                            title={t('title')}
                            subtitle={t('subtitle')}
                            icon={
                                <Sparkles
                                    size={16}
                                    className="text-ink-muted"
                                />
                            }
                        />

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
                        {!alertDismissed && (
                            <Alert variant="info" className="mb-4">
                                <div className="flex items-center justify-between gap-3">
                                    <span>{t('experimentalAlert')}</span>
                                    <button
                                        type="button"
                                        onClick={() => setAlertDismissed(true)}
                                        className="shrink-0 text-ink-faint transition-colors hover:text-ink"
                                    >
                                        <X size={14} />
                                    </button>
                                </div>
                            </Alert>
                        )}

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
                                onResolvedChange={updateResolved}
                            />
                        )}
                    </div>
                </main>

                <SlidePanel
                    open={chatContext !== null}
                    onClose={() => setChatContext(null)}
                    storageKey="manuscript:editorial-chat-width"
                    defaultWidth={320}
                    maxWidth={700}
                >
                    {chatContext && (
                        <AiChatDrawer
                            key={`${chatContext.reviewId}-${chatContext.sectionType ?? 'general'}-${chatContext.findingIndex ?? 'none'}`}
                            book={book}
                            title={t('ai:discussWithAi')}
                            onClose={() => setChatContext(null)}
                            editorialReview={chatContext}
                        />
                    )}
                </SlidePanel>
            </div>
        </>
    );
}
