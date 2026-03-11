import type { ChapterStatus } from '@/types/models';
import { useTranslation } from 'react-i18next';

const dotClass: Record<ChapterStatus, string> = {
    draft: 'bg-status-draft',
    revised: 'bg-status-revised',
    final: 'bg-status-final',
};

export default function StatusBadge({ status }: { status: ChapterStatus }) {
    const { t } = useTranslation('editor');

    return (
        <span className="flex items-center gap-1.5 text-xs text-ink-muted">
            <span className={`inline-block size-1.5 rounded-full ${dotClass[status]}`} />
            {t(`status.${status}`)}
        </span>
    );
}
