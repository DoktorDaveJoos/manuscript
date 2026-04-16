import { AlertCircle, Check } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Spinner } from '@/components/ui/spinner';

export type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';

interface SaveStatusIndicatorProps {
    status: SaveStatus;
    namespace?: string;
}

export default function SaveStatusIndicator({
    status,
    namespace = 'publish',
}: SaveStatusIndicatorProps) {
    const { t } = useTranslation(namespace);

    if (status === 'idle') return null;

    if (status === 'saving') {
        return (
            <span className="animate-in fade-in inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium text-ink-muted">
                <Spinner className="size-3" />
                {t('saveStatus.saving')}
            </span>
        );
    }

    if (status === 'error') {
        return (
            <span className="animate-in fade-in inline-flex items-center gap-1.5 rounded-full bg-danger/10 px-3 py-1 text-xs font-medium text-danger">
                <AlertCircle className="size-3" />
                {t('saveStatus.error')}
            </span>
        );
    }

    return (
        <span className="animate-in fade-in inline-flex items-center gap-1.5 rounded-full bg-status-final/10 px-3 py-1 text-xs font-medium text-status-final">
            <Check className="size-3" />
            {t('saveStatus.saved')}
        </span>
    );
}
