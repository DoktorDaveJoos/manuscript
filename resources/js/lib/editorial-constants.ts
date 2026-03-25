import type { FindingSeverity } from '@/types/models';

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
