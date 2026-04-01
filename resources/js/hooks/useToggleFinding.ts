import { useCallback } from 'react';
import { jsonFetchHeaders } from '@/lib/utils';
import { toggleFinding } from '@/actions/App/Http/Controllers/EditorialReviewController';

export function useToggleFinding(
    bookId: number,
    reviewId: number,
    resolvedFindings: string[],
    onUpdate: (resolved: string[]) => void,
) {
    return useCallback(
        async (key: string) => {
            const newResolved = resolvedFindings.includes(key)
                ? resolvedFindings.filter((k) => k !== key)
                : [...resolvedFindings, key];

            onUpdate(newResolved);

            try {
                const res = await fetch(
                    toggleFinding.url({ book: bookId, review: reviewId }),
                    {
                        method: 'POST',
                        headers: jsonFetchHeaders(),
                        body: JSON.stringify({ key }),
                    },
                );

                if (!res.ok) {
                    onUpdate(resolvedFindings);
                }
            } catch {
                onUpdate(resolvedFindings);
            }
        },
        [bookId, reviewId, resolvedFindings, onUpdate],
    );
}
