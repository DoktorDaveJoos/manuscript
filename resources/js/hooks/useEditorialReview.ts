import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
    progress,
    store,
} from '@/actions/App/Http/Controllers/EditorialReviewController';
import { jsonFetchHeaders } from '@/lib/utils';
import type { EditorialReview } from '@/types/models';

export function useEditorialReview(
    bookId: number,
    initialReview: EditorialReview | null,
) {
    const [review, setReview] = useState<EditorialReview | null>(initialReview);
    const [starting, setStarting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const isRunning = !!(
        review &&
        ['pending', 'analyzing', 'synthesizing'].includes(review.status)
    );

    // Sync when server provides fresh review data (e.g., after router.reload())
    useEffect(() => {
        if (initialReview) {
            setReview(initialReview);
        }
    }, [initialReview]);

    useEffect(() => {
        if (!isRunning || !review) {
            if (pollRef.current) {
                clearInterval(pollRef.current);
                pollRef.current = null;
            }
            return;
        }

        pollRef.current = setInterval(async () => {
            try {
                const res = await fetch(
                    progress.url({ book: bookId, review: review.id }),
                    { headers: { Accept: 'application/json' } },
                );
                if (!res.ok) throw new Error();
                const data = await res.json();

                setReview((prev) =>
                    prev
                        ? {
                              ...prev,
                              status: data.status,
                              progress: data.progress,
                              error_message: data.error_message,
                          }
                        : prev,
                );

                if (data.status === 'completed' || data.status === 'failed') {
                    router.reload();
                }
            } catch {
                // Silently retry on next interval
            }
        }, 2000);

        return () => {
            if (pollRef.current) clearInterval(pollRef.current);
        };
    }, [bookId, isRunning, review?.id]);

    const handleStart = useCallback(async () => {
        setStarting(true);
        setError(null);

        try {
            const res = await fetch(store.url(bookId), {
                method: 'POST',
                headers: jsonFetchHeaders(),
            });

            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw new Error(data.message || 'Failed to start');
            }

            const data = await res.json();
            setReview(data);
        } catch (e) {
            setError((e as Error).message);
        } finally {
            setStarting(false);
        }
    }, [bookId]);

    const selectReview = useCallback((selected: EditorialReview) => {
        setReview(selected);
    }, []);

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
        handleStart,
        selectReview,
        updateResolved,
    };
}
