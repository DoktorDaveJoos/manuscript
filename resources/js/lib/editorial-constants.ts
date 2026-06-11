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

/** Quality bands shared by the summary score and the dimension tiles. */
export type ScoreQuality = 'good' | 'fair' | 'needsWork';

export function scoreQuality(score: number): ScoreQuality {
    if (score >= 76) return 'good';
    if (score >= 60) return 'fair';
    return 'needsWork';
}

export const qualityBarColor: Record<ScoreQuality, string> = {
    good: 'bg-status-final',
    fair: 'bg-status-revised',
    needsWork: 'bg-status-draft',
};

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
