import type {
    EditorialReviewChapterNote,
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
