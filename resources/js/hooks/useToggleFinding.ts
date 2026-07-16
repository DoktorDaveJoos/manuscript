import { useCallback } from 'react';
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

                await ensureSuccessfulResponse(
                    res,
                    t('finding.saveFailed.description'),
                );
            } catch {
                onUpdate(resolvedFindings);
                toast.error(t('finding.saveFailed.title'), {
                    description: t('finding.saveFailed.description'),
                });
            }
        },
        [bookId, reviewId, resolvedFindings, onUpdate, t],
    );
}
