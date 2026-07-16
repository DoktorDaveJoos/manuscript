import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    progress,
    resume,
    show,
    store,
} from '@/actions/App/Http/Controllers/EditorialReviewController';
import { jsonFetchHeaders } from '@/lib/utils';
import type { EditorialReview } from '@/types/models';

export type EditorialReviewRequestError = {
    code: string | null;
    message: string;
};

type EditorialReviewResponse = {
    message?: string;
    error_code?: string | null;
    review?: EditorialReview;
};

type EditorialReviewProgressResponse = Pick<
    EditorialReview,
    'status' | 'progress' | 'error_message' | 'error_code'
>;

async function responsePayload(
    response: Response,
): Promise<EditorialReviewResponse> {
    try {
        return (await response.json()) as EditorialReviewResponse;
    } catch {
        return {};
    }
}

function safeResponseMessage(
    response: Response,
    payload: EditorialReviewResponse,
    fallback: string,
): string {
    if (
        payload.message &&
        (payload.error_code !== undefined || response.status < 500)
    ) {
        return payload.message;
    }

    return fallback;
}

export function useEditorialReview(
    bookId: number,
    initialReview: EditorialReview | null,
) {
    const { t } = useTranslation('editorial-review');
    const [review, setReview] = useState<EditorialReview | null>(initialReview);
    const [starting, setStarting] = useState(false);
    const [error, setError] = useState<EditorialReviewRequestError | null>(
        null,
    );
    const [pollError, setPollError] = useState<string | null>(null);
    const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const pollingRef = useRef(false);
    const pollFailuresRef = useRef(0);

    const isRunning = !!(
        review &&
        ['pending', 'analyzing', 'synthesizing'].includes(review.status)
    );
    const reviewId = review?.id;

    // Sync when server provides fresh review data (e.g., after router.reload())
    useEffect(() => {
        setReview(initialReview);
        setError(null);
    }, [initialReview]);

    useEffect(() => {
        if (!isRunning || reviewId === undefined) {
            if (pollRef.current) {
                clearInterval(pollRef.current);
                pollRef.current = null;
            }
            return;
        }

        pollFailuresRef.current = 0;

        pollRef.current = setInterval(async () => {
            if (pollingRef.current) {
                return;
            }

            pollingRef.current = true;

            try {
                const res = await fetch(
                    progress.url({ book: bookId, review: reviewId }),
                    { headers: { Accept: 'application/json' } },
                );
                if (!res.ok) {
                    throw new Error();
                }

                const data =
                    (await res.json()) as EditorialReviewProgressResponse;
                pollFailuresRef.current = 0;
                setPollError(null);

                if (data.status === 'failed') {
                    setReview((prev) =>
                        prev
                            ? {
                                  ...prev,
                                  status: data.status,
                                  progress: data.progress,
                                  error_message: data.error_message,
                                  error_code: data.error_code,
                              }
                            : prev,
                    );

                    if (pollRef.current) {
                        clearInterval(pollRef.current);
                        pollRef.current = null;
                    }
                    router.reload();
                    return;
                }

                if (data.status === 'completed') {
                    if (pollRef.current) {
                        clearInterval(pollRef.current);
                        pollRef.current = null;
                    }
                    router.reload();
                    return;
                }

                setReview((prev) => {
                    if (
                        !prev ||
                        (prev.status === data.status &&
                            prev.error_message === data.error_message &&
                            JSON.stringify(prev.progress) ===
                                JSON.stringify(data.progress))
                    ) {
                        return prev;
                    }
                    return {
                        ...prev,
                        status: data.status,
                        progress: data.progress,
                        error_message: data.error_message,
                        error_code: data.error_code,
                    };
                });
            } catch {
                pollFailuresRef.current += 1;
                if (pollFailuresRef.current >= 3) {
                    setPollError(t('progress.connectionLost'));
                }
            } finally {
                pollingRef.current = false;
            }
        }, 2000);

        return () => {
            if (pollRef.current) clearInterval(pollRef.current);
        };
    }, [bookId, isRunning, reviewId, t]);

    const handleStart = useCallback(async () => {
        setStarting(true);
        setError(null);
        setPollError(null);

        try {
            const res = await fetch(store.url(bookId), {
                method: 'POST',
                headers: jsonFetchHeaders(),
            });

            const data = await responsePayload(res);

            if (!res.ok) {
                if (data.review) {
                    setReview(data.review);
                }

                if (data.error_code === 'already_running' && data.review) {
                    return;
                }

                setError({
                    code: data.error_code ?? null,
                    message: safeResponseMessage(
                        res,
                        data,
                        t('request.startFailed'),
                    ),
                });

                return;
            }

            if (data.review) {
                setReview(data.review);
            }
        } catch {
            setError({
                code: 'connection_failed',
                message: t('request.connectionFailed'),
            });
        } finally {
            setStarting(false);
        }
    }, [bookId, t]);

    const handleResume = useCallback(async () => {
        if (!review) {
            return;
        }

        setStarting(true);
        setError(null);
        setPollError(null);

        try {
            const res = await fetch(
                resume.url({ book: bookId, review: review.id }),
                {
                    method: 'POST',
                    headers: jsonFetchHeaders(),
                },
            );

            const data = await responsePayload(res);

            if (!res.ok) {
                if (data.review) {
                    setReview(data.review);
                }

                if (data.error_code === 'already_running' && data.review) {
                    return;
                }

                setError({
                    code: data.error_code ?? null,
                    message: safeResponseMessage(
                        res,
                        data,
                        t('request.resumeFailed'),
                    ),
                });

                return;
            }

            if (data.review) {
                setReview(data.review);
            }
        } catch {
            setError({
                code: 'connection_failed',
                message: t('request.connectionFailed'),
            });
        } finally {
            setStarting(false);
        }
    }, [bookId, review, t]);

    const selectReview = useCallback(
        (selected: EditorialReview) => {
            router.visit(show.url({ book: bookId, review: selected.id }));
        },
        [bookId],
    );

    const updateResolved = useCallback((resolved: string[]) => {
        setReview((prev) =>
            prev ? { ...prev, resolved_findings: resolved } : prev,
        );
    }, []);

    return {
        review,
        isRunning,
        starting,
        error,
        pollError,
        handleStart,
        handleResume,
        selectReview,
        updateResolved,
    };
}
