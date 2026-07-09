import { cn } from '@/lib/utils';
import type { ChapterStatus } from '@/types/models';

const statusDotClass: Record<ChapterStatus, string> = {
    draft: 'bg-status-draft',
    revised: 'bg-status-revised',
    final: 'bg-status-final',
};

export default function StatusDot({
    status,
    className,
}: {
    status: ChapterStatus;
    className?: string;
}) {
    return (
        <span
            data-testid="chapter-status-dot"
            data-status={status}
            className={cn(
                'inline-block size-[7px] shrink-0 rounded-full',
                statusDotClass[status],
                className,
            )}
        />
    );
}
