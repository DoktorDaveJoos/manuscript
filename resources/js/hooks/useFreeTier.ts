import { usePage } from '@inertiajs/react';
import type { FreeTier } from '@/types/models';

export function useFreeTier() {
    const { free_tier } = usePage<{ free_tier: FreeTier }>().props;

    const isPro = free_tier === null;
    const isFree = !isPro;

    return {
        isPro,
        isFree,
        books: free_tier?.books ?? null,
        storylines: free_tier?.storylines ?? null,
        wikiEntries: free_tier?.wiki_entries ?? null,
        exportFreeFormats: free_tier?.export_free_formats ?? [],

        canCreateBook:
            isPro ||
            (free_tier?.books?.count ?? 0) < (free_tier?.books?.limit ?? 1),
        canCreateStoryline:
            isPro ||
            (free_tier?.storylines?.count ?? 0) <
                (free_tier?.storylines?.limit ?? 1),
        canCreateWikiEntry:
            isPro ||
            (free_tier?.wiki_entries?.count ?? 0) <
                (free_tier?.wiki_entries?.limit ?? 5),
        canExportFormat: (format: string) =>
            isPro || (free_tier?.export_free_formats ?? []).includes(format),
    };
}
