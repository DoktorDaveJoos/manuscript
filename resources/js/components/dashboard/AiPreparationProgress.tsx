import { useAiPreparation, TOTAL_PHASES } from '@/hooks/useAiPreparation';
import type { AiPreparationStatus } from '@/types/models';
import { Check, Lock } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function AiPreparationProgress({
    bookId,
    aiEnabled,
    initialStatus,
    licensed = true,
}: {
    bookId: number;
    aiEnabled: boolean;
    initialStatus: AiPreparationStatus | null;
    licensed?: boolean;
}) {
    const { t } = useTranslation('ai');
    const { status, isRunning, starting, error, handleStart } = useAiPreparation(bookId, initialStatus);

    if (!licensed) {
        return (
            <button
                type="button"
                disabled
                className="flex cursor-not-allowed items-center gap-2 rounded-md border border-border bg-surface-card px-4 py-2 text-sm text-ink-faint"
                title={t('preparationProgress.requiresProTitle')}
            >
                <Lock size={14} className="shrink-0" />
                {t('preparationProgress.prepareForAi')}
                <span className="rounded bg-ink-faint/10 px-1 py-0.5 text-[10px] font-medium">PRO</span>
            </button>
        );
    }

    if (!aiEnabled) {
        return (
            <button
                type="button"
                disabled
                className="cursor-not-allowed rounded-md border border-border bg-surface-card px-4 py-2 text-sm text-ink-faint"
                title={t('preparationProgress.configureApiKeyTitle')}
            >
                {t('preparationProgress.prepareForAi')}
            </button>
        );
    }

    if (isRunning && status) {
        const completedCount = status.completed_phases?.length ?? 0;
        const currentPhase = status.current_phase;
        const phaseLabel = currentPhase ? t(`phase.${currentPhase}`) : t('preparation.starting');
        const phaseProgress =
            status.current_phase_total > 0
                ? Math.round((status.current_phase_progress / status.current_phase_total) * 100)
                : 0;
        const overallProgress = Math.round((completedCount / TOTAL_PHASES) * 100);
        const hasErrors = status.phase_errors && status.phase_errors.length > 0;

        return (
            <div className="flex items-center gap-3 rounded-md border border-border bg-surface-card px-4 py-2">
                <span className="inline-block size-3 animate-spin rounded-full border-2 border-ink-faint border-t-ink" />
                <div className="flex flex-col gap-0.5">
                    <span className="text-sm text-ink">{phaseLabel}...</span>
                    <div className="flex items-center gap-2">
                        <span className="text-xs text-ink-faint">
                            {t('preparationProgress.phaseCounter', { current: completedCount + 1, total: TOTAL_PHASES })}
                        </span>
                        {status.current_phase_total > 1 && (
                            <span className="text-xs text-ink-faint">
                                &middot; {t('preparationProgress.phaseDetail', { progress: status.current_phase_progress, total: status.current_phase_total, percent: phaseProgress })}
                            </span>
                        )}
                        <span className="text-xs text-ink-faint">&middot; {t('preparationProgress.overallProgress', { percent: overallProgress })}</span>
                        {hasErrors && <span className="text-xs text-amber-600">&middot; {t('preparationProgress.warning', { count: status.phase_errors!.length })}</span>}
                    </div>
                </div>
            </div>
        );
    }

    if (status?.status === 'completed') {
        const hasErrors = status.phase_errors && status.phase_errors.length > 0;

        return (
            <div className="flex items-center gap-2 rounded-md border border-border bg-surface-card px-4 py-2">
                <Check size={14} strokeWidth={2.5} className="text-status-final" />
                <span className="text-sm text-ink-muted">{t('preparation.aiReady')}</span>
                {hasErrors && (
                    <span className="text-xs text-amber-600">{t('preparationProgress.warningParens', { count: status.phase_errors!.length })}</span>
                )}
                <button
                    type="button"
                    onClick={handleStart}
                    className="ml-2 text-xs text-ink-faint transition-colors hover:text-ink"
                >
                    {t('preparation.reRun')}
                </button>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-1">
            <button
                type="button"
                onClick={handleStart}
                disabled={starting}
                className="rounded-md border border-border bg-surface-card px-4 py-2 text-sm text-ink transition-colors hover:bg-neutral-bg disabled:opacity-50"
            >
                {starting ? t('preparation.starting') : t('preparationProgress.prepareForAi')}
            </button>
            {error && <span className="text-xs text-red-600">{error}</span>}
            {status?.status === 'failed' && (
                <span className="text-xs text-red-600">{status.error_message ?? t('preparation.failed')}</span>
            )}
        </div>
    );
}
