import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
    retry as retryPreparation,
    start as startPreparation,
    status as preparationStatus,
} from '@/actions/App/Http/Controllers/AiPreparationController';
import { jsonFetchHeaders } from '@/lib/utils';
import type { AiPreparationStatus } from '@/types/models';

export const TOTAL_PHASES = 7;

export function useAiPreparation(
    bookId: number,
    initialStatus: AiPreparationStatus | null,
) {
    const [status, setStatus] = useState<AiPreparationStatus | null>(
        initialStatus,
    );
    const [starting, setStarting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const isRunning = !!(
        status && !['completed', 'failed'].includes(status.status)
    );

    useEffect(() => {
        if (!isRunning) {
            if (pollRef.current) {
                clearInterval(pollRef.current);
                pollRef.current = null;
            }
            return;
        }

        pollRef.current = setInterval(async () => {
            try {
                const res = await fetch(preparationStatus.url(bookId), {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) throw new Error();
                const data = await res.json();
                setStatus((prev) => {
                    if (
                        prev &&
                        prev.status === data.status &&
                        prev.current_phase === data.current_phase &&
                        prev.current_phase_progress ===
                            data.current_phase_progress &&
                        prev.current_phase_total === data.current_phase_total
                    ) {
                        return prev;
                    }
                    return data;
                });

                if (data?.status === 'completed') {
                    router.reload();
                }
            } catch {
                // Silently retry on next interval
            }
        }, 2000);

        return () => {
            if (pollRef.current) clearInterval(pollRef.current);
        };
    }, [bookId, isRunning]);

    const dispatch = useCallback(
        async (url: string, fallbackMessage: string) => {
            setStarting(true);
            setError(null);

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: jsonFetchHeaders(),
                });

                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    throw new Error(data.message || fallbackMessage);
                }

                const data = await res.json();
                setStatus(data);
            } catch (e) {
                setError((e as Error).message);
            } finally {
                setStarting(false);
            }
        },
        [],
    );

    const handleStart = useCallback(
        () => dispatch(startPreparation.url(bookId), 'Failed to start'),
        [bookId, dispatch],
    );

    const handleRetry = useCallback(
        () => dispatch(retryPreparation.url(bookId), 'Failed to retry'),
        [bookId, dispatch],
    );

    return { status, isRunning, starting, error, handleStart, handleRetry };
}
