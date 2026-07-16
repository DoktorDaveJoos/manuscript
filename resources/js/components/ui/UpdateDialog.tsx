import Button from '@/components/ui/Button';
import Dialog from '@/components/ui/Dialog';
import { useAutoUpdater } from '@/hooks/useAutoUpdater';
import { Download, Package } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface Props {
    currentVersion: string;
}

export default function UpdateDialog({ currentVersion }: Props) {
    const { t } = useTranslation('settings');
    const { state, checkForUpdates, downloadUpdate, installUpdate, dismissUpdate } =
        useAutoUpdater();

    if (
        state.status !== 'available' &&
        state.status !== 'downloading' &&
        state.status !== 'ready' &&
        state.status !== 'error'
    ) {
        return null;
    }

    if (state.status === 'error') {
        return (
            <Dialog
                onClose={dismissUpdate}
                title={t('appearance.update.dialogTitle')}
                width={440}
                className="shadow-xl"
            >
                <div className="flex size-12 items-center justify-center rounded-xl bg-delete/10">
                    <Download className="size-6 text-delete" />
                </div>
                <div className="mt-5 flex flex-col gap-2">
                    <h2 className="text-xl font-semibold tracking-[-0.01em] text-ink">
                        {t('appearance.update.dialogTitle')}
                    </h2>
                    <p className="text-sm leading-[1.5] text-ink-muted">
                        {state.error ?? t('appearance.update.errorGeneric')}
                    </p>
                </div>
                <div className="mt-7 flex justify-end gap-3">
                    <Button variant="secondary" size="lg" type="button" onClick={dismissUpdate}>
                        {t('appearance.update.later')}
                    </Button>
                    <Button variant="primary" size="lg" type="button" onClick={checkForUpdates}>
                        {t('appearance.update.retry')}
                    </Button>
                </div>
            </Dialog>
        );
    }

    const isDownloading = state.status === 'downloading';
    const isReady = state.status === 'ready';

    const handleUpdateNow = () => {
        if (state.status === 'available') {
            downloadUpdate();
        } else if (isReady) {
            installUpdate();
        }
    };

    return (
        <Dialog
            onClose={dismissUpdate}
            title={t('appearance.update.dialogTitle')}
            width={440}
            className="shadow-xl"
        >
            {/* Icon */}
            <div className="flex size-12 items-center justify-center rounded-xl bg-accent-light">
                <Download className="size-6 text-accent" />
            </div>

            {/* Text */}
            <div className="mt-5 flex flex-col gap-2">
                <h2 className="text-xl font-semibold tracking-[-0.01em] text-ink">
                    {t('appearance.update.dialogTitle')}
                </h2>
                <p className="text-sm leading-[1.5] text-ink-muted">
                    {t('appearance.update.dialogDescription')}
                </p>
            </div>

            {/* Version info card */}
            <div className="mt-6 flex items-center gap-3 rounded-lg bg-surface-warm px-5 py-4">
                <Package className="size-5 shrink-0 text-accent" />
                <div className="flex flex-col gap-0.5">
                    <span className="text-sm font-medium text-ink">
                        {t('appearance.update.newVersion', { version: state.version ?? '—' })}
                    </span>
                    <span className="text-xs text-ink-faint">
                        {t('appearance.update.currentVersion', { version: currentVersion })}
                    </span>
                </div>
            </div>

            {/* Progress bar */}
            {isDownloading && (
                <div className="mt-4 h-1.5 overflow-hidden rounded-full bg-border-light">
                    <div
                        className="h-full rounded-full bg-accent transition-all duration-300"
                        style={{ width: `${state.progress}%` }}
                    />
                </div>
            )}

            {/* Buttons */}
            <div className="mt-7 flex justify-end gap-3">
                {!isDownloading && (
                    <Button variant="secondary" size="lg" type="button" onClick={dismissUpdate}>
                        {t('appearance.update.later')}
                    </Button>
                )}
                <Button variant="primary" size="lg" type="button" onClick={handleUpdateNow} disabled={isDownloading}>
                    {isDownloading
                        ? t('appearance.update.downloading', { progress: state.progress })
                        : isReady
                          ? t('appearance.update.restart')
                          : t('appearance.update.updateNow')}
                </Button>
            </div>
        </Dialog>
    );
}
