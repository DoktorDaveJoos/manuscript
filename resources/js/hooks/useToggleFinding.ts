import { useCallback, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { toggleFinding } from '@/actions/App/Http/Controllers/EditorialReviewController';
import { ensureSuccessfulResponse, jsonFetchHeaders } from '@/lib/utils';

export function useToggleFinding(
    bookId: number,
    reviewId: number,
    resolvedFindings: string[],
    onUpdate: (resolved: string[]) => void,
) {
    const { t } = useTranslation('editorial-review');
    const latestResolvedRef = useRef(resolvedFindings);
    latestResolvedRef.current = resolvedFindings;

    return useCallback(
        async (key: string) => {
            const wasResolved = latestResolvedRef.current.includes(key);
            const nextResolved = wasResolved
                ? latestResolvedRef.current.filter((k) => k !== key)
                : [...latestResolvedRef.current, key];

            latestResolvedRef.current = nextResolved;
            onUpdate(nextResolved);

            try {
                const res = await fetch(
                    toggleFinding.url({ book: bookId, review: reviewId }),
                    {
                        method: 'POST',
                        headers: jsonFetchHeaders(),
                        body: JSON.stringify({ key }),
                    },
                );

                await ensureSuccessfulResponse(
                    res,
                    t('finding.saveFailed.description'),
                );
            } catch {
                // Roll back only this key against the latest state so
                // concurrent toggles on other findings are never clobbered.
                const rolledBack = wasResolved
                    ? latestResolvedRef.current.includes(key)
                        ? latestResolvedRef.current
                        : [...latestResolvedRef.current, key]
                    : latestResolvedRef.current.filter((k) => k !== key);
                latestResolvedRef.current = rolledBack;
                onUpdate(rolledBack);
                toast.error(t('finding.saveFailed.title'), {
                    description: t('finding.saveFailed.description'),
                });
            }
        },
        [bookId, reviewId, onUpdate, t],
    );
}
