import type {
    EditorialReviewChapterNote,
    EditorialReviewSection,
    FindingSeverity,
} from '@/types/models';

export function getChapterNote(
    chapterNotes: EditorialReviewChapterNote[] | undefined,
    chapterId: number,
): string | null {
    if (!chapterNotes) return null;
    const note = chapterNotes.find((n) => n.chapter_id === chapterId);
    return note?.notes?.chapter_note ?? null;
}

export const severityOrder: Record<FindingSeverity, number> = {
    critical: 0,
    warning: 1,
    suggestion: 2,
};

export type ChapterFindingStats = {
    total: number;
    resolved: number;
};

export function buildChapterStats(
    sections: EditorialReviewSection[],
    resolvedSet: Set<string>,
): Map<number, ChapterFindingStats> {
    const stats = new Map<number, ChapterFindingStats>();

    for (const section of sections) {
        if (!section.findings) continue;
        for (const finding of section.findings) {
            for (const chapterId of finding.chapter_references) {
                const existing = stats.get(chapterId) ?? {
                    total: 0,
                    resolved: 0,
                };
                existing.total++;
                if (resolvedSet.has(finding.key)) {
                    existing.resolved++;
                }
                stats.set(chapterId, existing);
            }
        }
    }

    return stats;
}

export const severityDotColor: Record<FindingSeverity, string> = {
    critical: 'bg-delete',
    warning: 'bg-accent',
    suggestion: 'bg-ink-faint',
};

export const severityTextColor: Record<FindingSeverity, string> = {
    critical: 'text-delete',
    warning: 'text-accent',
    suggestion: 'text-ink-faint',
};

export const severityBadgeVariant: Record<
    FindingSeverity,
    'destructive' | 'warning' | 'secondary'
> = {
    critical: 'destructive',
    warning: 'warning',
    suggestion: 'secondary',
};
