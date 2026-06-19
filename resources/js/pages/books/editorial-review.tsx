import { Head } from '@inertiajs/react';
import { Sparkles, X } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import AiChatDrawer from '@/components/editor/AiChatDrawer';
import Sidebar from '@/components/editor/Sidebar';
import EditorialReviewEmptyState from '@/components/editorial-review/EditorialReviewEmptyState';
import EditorialReviewProgress from '@/components/editorial-review/EditorialReviewProgress';
import EditorialReviewReport from '@/components/editorial-review/EditorialReviewReport';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import PageHeader from '@/components/ui/PageHeader';
import SlidePanel from '@/components/ui/SlidePanel';
import { useEditorialReview } from '@/hooks/useEditorialReview';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import { track } from '@/lib/analytics';
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
    editedChaptersCount,
}: {
    book: Book;
    reviews: EditorialReview[];
    latestReview: EditorialReview | null;
    chapters: Chapter[];
    editedChaptersCount: number | null;
}) {
    const { t } = useTranslation('editorial-review');
    const storylines = useSidebarStorylines();
    const {
        review,
        isRunning,
        starting,
        error,
        handleStart,
        handleResume,
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

    const handleStartReview = useCallback(() => {
        track('ai_feature_used', { type: 'editorial' });
        void handleStart();
    }, [handleStart]);

    const handleResumeReview = useCallback(() => {
        track('ai_feature_used', { type: 'editorial' });
        void handleResume();
    }, [handleResume]);

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

                        {(error || review?.status === 'failed') && (
                            <div className="flex flex-1 flex-col items-center justify-center gap-4">
                                <Alert
                                    variant="destructive"
                                    className="max-w-xl"
                                >
                                    <AlertTitle>{t('failed.title')}</AlertTitle>
                                    <AlertDescription>
                                        {review?.status === 'failed'
                                            ? t(
                                                  `failed.reasons.${review.error_code ?? 'unknown'}`,
                                                  {
                                                      defaultValue: t(
                                                          'failed.reasons.unknown',
                                                      ),
                                                  },
                                              )
                                            : error}
                                    </AlertDescription>
                                </Alert>
                                {review?.status === 'failed' && (
                                    <>
                                        <p className="max-w-xl text-center text-[13px] text-ink-muted">
                                            {t('failed.progressKept')}
                                        </p>
                                        {(!review.error_code ||
                                            review.error_code === 'unknown') &&
                                            review.error_message && (
                                                <p className="max-w-xl text-center text-xs text-ink-faint">
                                                    {review.error_message}
                                                </p>
                                            )}
                                        <Button
                                            variant="primary"
                                            onClick={handleResumeReview}
                                            disabled={starting}
                                        >
                                            {t('failed.continue')}
                                        </Button>
                                    </>
                                )}
                            </div>
                        )}

                        {!review && (
                            <EditorialReviewEmptyState
                                onStart={handleStartReview}
                                starting={starting}
                            />
                        )}

                        {isRunning && review && (
                            <EditorialReviewProgress review={review} />
                        )}

                        {review?.status === 'completed' && review && (
                            <EditorialReviewReport
                                key={review.id}
                                review={review}
                                reviews={completedReviews}
                                chapters={chapters}
                                editedChaptersCount={editedChaptersCount}
                                onSelectReview={selectReview}
                                onStartNew={handleStartReview}
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
