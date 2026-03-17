import { cn } from '@/lib/utils';
import type { ChapterStatus } from '@/types/models';
import { useTranslation } from 'react-i18next';

export default function StatusBadge({ status, className }: { status: ChapterStatus; className?: string }) {
    const { t } = useTranslation('editor');

    return (
        <span className={cn('rounded-[4px] bg-neutral-bg px-2 py-0.5 text-[11px] font-medium text-ink-muted', className)}>
            {t(`status.${status}`)}
        </span>
    );
}
