import { Head, Link, usePage } from '@inertiajs/react';
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
import { index as settingsIndex } from '@/routes/settings';
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
        pollError,
        handleStart,
        handleResume,
        selectReview,
        updateResolved,
    } = useEditorialReview(book.id, latestReview);
    const {
        ai_configured: aiConfigured = false,
        ai_key_recovery_needed: aiKeyRecoveryNeeded = false,
        ai_provider_label: aiProviderLabel = null,
    } = usePage<{
        ai_configured?: boolean;
        ai_key_recovery_needed?: boolean;
        ai_provider_label?: string | null;
    }>().props;

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
    const hasFailure = error !== null || review?.status === 'failed';
    const failureCode = error?.code ?? review?.error_code ?? 'unknown';
    const settingsHref = settingsIndex({
        query: { section: 'ai-features' },
    });
    const needsSettings =
        !aiConfigured ||
        [
            'no_provider',
            'invalid_key',
            'insufficient_credits',
            'model_unavailable',
            'context_too_long',
            'bad_request',
        ].includes(failureCode);
    const configurationTitle = aiKeyRecoveryNeeded
        ? t('configuration.recovery.title')
        : aiProviderLabel
          ? t('configuration.credentials.title')
          : t('configuration.missing.title');
    const configurationDescription = aiKeyRecoveryNeeded
        ? t('configuration.recovery.description', {
              provider: aiProviderLabel ?? t('configuration.providerFallback'),
          })
        : aiProviderLabel
          ? t('configuration.credentials.description', {
                provider: aiProviderLabel,
            })
          : t('configuration.missing.description');

    const failureLocation = (() => {
        if (!review?.progress) {
            return null;
        }

        if (
            review.progress.phase === 'analyzing' &&
            review.progress.current_chapter
        ) {
            return t('failed.location.analyzing', {
                current: review.progress.current_chapter,
                total: review.progress.total_chapters ?? '?',
            });
        }

        if (
            review.progress.phase === 'synthesizing' &&
            review.progress.current_section
        ) {
            return t('failed.location.synthesizing', {
                section: t(`section.${review.progress.current_section}`),
            });
        }

        if (review.progress.phase === 'pending') {
            return t('failed.location.pending');
        }

        if (review.progress.phase === 'summarizing') {
            return t('failed.location.summarizing');
        }

        return null;
    })();

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

                        {!aiConfigured && (
                            <Alert
                                variant="destructive"
                                className="mb-4"
                                data-testid="editorial-review-ai-configuration"
                            >
                                <AlertTitle>{configurationTitle}</AlertTitle>
                                <AlertDescription>
                                    {configurationDescription}
                                </AlertDescription>
                                <div className="mt-2">
                                    <Button asChild variant="primary" size="sm">
                                        <Link href={settingsHref}>
                                            {t('configuration.openSettings')}
                                        </Link>
                                    </Button>
                                </div>
                            </Alert>
                        )}

                        {pollError && !hasFailure && (
                            <Alert variant="info" className="mb-4">
                                <AlertTitle>
                                    {t('progress.connectionTitle')}
                                </AlertTitle>
                                <AlertDescription>{pollError}</AlertDescription>
                            </Alert>
                        )}

                        {hasFailure && (
                            <div className="flex flex-1 flex-col items-center justify-center gap-4">
                                <Alert
                                    variant="destructive"
                                    className="max-w-xl"
                                >
                                    <AlertTitle>{t('failed.title')}</AlertTitle>
                                    <AlertDescription>
                                        {t(`failed.reasons.${failureCode}`, {
                                            defaultValue: t(
                                                'failed.reasons.unknown',
                                            ),
                                        })}
                                    </AlertDescription>
                                </Alert>
                                {error && (
                                    <p className="max-w-xl text-center text-[13px] text-ink-muted">
                                        {error.message}
                                    </p>
                                )}
                                {failureLocation && (
                                    <p className="max-w-xl text-center text-[13px] text-ink-muted">
                                        {failureLocation}
                                    </p>
                                )}
                                {error && review?.status !== 'failed' && (
                                    <div className="flex items-center gap-3">
                                        {needsSettings && (
                                            <Button asChild variant="primary">
                                                <Link href={settingsHref}>
                                                    {t(
                                                        'configuration.openSettings',
                                                    )}
                                                </Link>
                                            </Button>
                                        )}
                                        {aiConfigured && (
                                            <Button
                                                variant={
                                                    needsSettings
                                                        ? 'secondary'
                                                        : 'primary'
                                                }
                                                onClick={handleStartReview}
                                                disabled={starting}
                                            >
                                                {t('request.tryAgain')}
                                            </Button>
                                        )}
                                    </div>
                                )}
                                {review?.status === 'failed' && (
                                    <>
                                        <p className="max-w-xl text-center text-[13px] text-ink-muted">
                                            {t('failed.progressKept')}
                                        </p>
                                        <div className="flex items-center gap-3">
                                            {needsSettings && (
                                                <Button
                                                    asChild
                                                    variant="primary"
                                                >
                                                    <Link href={settingsHref}>
                                                        {t(
                                                            'configuration.openSettings',
                                                        )}
                                                    </Link>
                                                </Button>
                                            )}
                                            {aiConfigured && (
                                                <Button
                                                    variant={
                                                        needsSettings
                                                            ? 'secondary'
                                                            : 'primary'
                                                    }
                                                    onClick={handleResumeReview}
                                                    disabled={starting}
                                                >
                                                    {t('failed.continue')}
                                                </Button>
                                            )}
                                        </div>
                                    </>
                                )}
                            </div>
                        )}

                        {!hasFailure && !review && (
                            <EditorialReviewEmptyState
                                onStart={handleStartReview}
                                starting={starting}
                                canStart={aiConfigured}
                            />
                        )}

                        {!hasFailure && isRunning && review && (
                            <EditorialReviewProgress review={review} />
                        )}

                        {!hasFailure &&
                            review?.status === 'completed' &&
                            review && (
                                <EditorialReviewReport
                                    key={review.id}
                                    review={review}
                                    reviews={completedReviews}
                                    chapters={chapters}
                                    editedChaptersCount={editedChaptersCount}
                                    onSelectReview={selectReview}
                                    onStartNew={handleStartReview}
                                    starting={starting}
                                    canStart={aiConfigured}
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
