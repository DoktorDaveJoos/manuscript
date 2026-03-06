import type { ChapterStatus } from '@/types/models';

const config: Record<ChapterStatus, { label: string; dotClass: string }> = {
    draft: { label: 'Draft', dotClass: 'bg-status-draft' },
    revised: { label: 'Revised', dotClass: 'bg-status-revised' },
    final: { label: 'Final', dotClass: 'bg-status-final' },
};

export default function StatusBadge({ status }: { status: ChapterStatus }) {
    const { label, dotClass } = config[status];

    return (
        <span className="flex items-center gap-1.5 text-xs text-ink-muted">
            <span className={`inline-block size-1.5 rounded-full ${dotClass}`} />
            {label}
        </span>
    );
}
