import { useCallback, useEffect, useRef, useState } from 'react';
import {
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
                setStatus(data);
            } catch {
                // Silently retry on next interval
            }
        }, 2000);

        return () => {
            if (pollRef.current) clearInterval(pollRef.current);
        };
    }, [bookId, isRunning]);

    const handleStart = useCallback(async () => {
        setStarting(true);
        setError(null);

        try {
            const res = await fetch(startPreparation.url(bookId), {
                method: 'POST',
                headers: jsonFetchHeaders(),
            });

            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw new Error(data.message || 'Failed to start');
            }

            const data = await res.json();
            setStatus(data);
        } catch (e) {
            setError((e as Error).message);
        } finally {
            setStarting(false);
        }
    }, [bookId]);

    return { status, isRunning, starting, error, handleStart };
}
