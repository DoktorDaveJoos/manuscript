import { useAiFeatures } from '@/hooks/useAiFeatures';
import { useAiPreparation, TOTAL_PHASES } from '@/hooks/useAiPreparation';
import type { AiPreparationStatus } from '@/types/models';
import { Link } from '@inertiajs/react';
import { Check, Lock, Sparkle } from '@phosphor-icons/react';
import { useTranslation } from 'react-i18next';

export default function AiPreparation({
    bookId,
    initialStatus,
}: {
    bookId: number;
    initialStatus: AiPreparationStatus | null;
}) {
    const { t } = useTranslation('ai');
    const { visible, usable, licensed, configured } = useAiFeatures();
    const { status, isRunning, starting, error, handleStart } = useAiPreparation(bookId, initialStatus);

    if (!visible) return null;

    // Not usable — show contextual guidance
    if (!usable) {
        let heading = '';
        let description = '';
        let linkContent: React.ReactNode = null;

        if (!licensed) {
            heading = t('preparation.requiresPro');
            description = t('preparation.upgradeDescription');
            linkContent = (
                <a
                    href="https://getmanuscript.app"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-[13px] font-medium text-accent transition-colors hover:text-accent/80"
                >
                    {t('preparation.learnMore')}
                </a>
            );
        } else if (!configured) {
            heading = t('preparation.setupProvider');
            description = t('preparation.addApiKey');
            linkContent = (
                <Link
                    href="/settings/ai"
                    className="text-[13px] font-medium text-accent transition-colors hover:text-accent/80"
                >
                    {t('preparation.goToSettings')}
                </Link>
            );
        }

        return (
            <div className="flex items-center justify-between rounded-lg bg-surface-card px-6 py-6">
                <div className="flex flex-col gap-2">
                    <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">
                        {t('preparation.title')}
                    </span>
                    <div className="flex items-center gap-2">
                        <Lock size={18} weight="fill" className="shrink-0 text-ink-faint" />
                        <span className="font-serif text-[20px] font-medium text-ink-muted">
                            {heading}
                        </span>
                    </div>
                    <p className="text-[13px] text-ink-muted">
                        {description}
                    </p>
                    {linkContent}
                </div>
            </div>
        );
    }

    // Running state
    if (isRunning && status) {
        const completedCount = status.completed_phases?.length ?? 0;
        const currentPhase = status.current_phase;
        const phaseLabel = currentPhase ? t(`phase.${currentPhase}`) : t('preparation.starting');
        const overallProgress = Math.round((completedCount / TOTAL_PHASES) * 100);

        return (
            <div className="flex flex-col gap-4 rounded-lg bg-surface-card px-6 py-6">
                <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">
                    {t('preparation.title')}
                </span>
                <div className="flex items-center gap-3">
                    <span className="inline-block size-4 animate-spin rounded-full border-2 border-ink-faint border-t-ink" />
                    <div className="flex flex-col gap-0.5">
                        <span className="text-[14px] font-medium text-ink">{phaseLabel}...</span>
                        <span className="text-[12px] text-ink-faint">
                            {t('preparation.phaseProgress', { current: completedCount + 1, total: TOTAL_PHASES, percent: overallProgress })}
                        </span>
                    </div>
                </div>
                <div className="h-1.5 overflow-hidden rounded-[3px] bg-neutral-bg">
                    <div
                        className="h-full rounded-[3px] bg-accent transition-all duration-500"
                        style={{ width: `${overallProgress}%` }}
                    />
                </div>
            </div>
        );
    }

    // Completed state
    if (status?.status === 'completed') {
        return (
            <div className="flex items-center justify-between rounded-lg bg-surface-card px-6 py-6">
                <div className="flex flex-col gap-2">
                    <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">
                        {t('preparation.title')}
                    </span>
                    <div className="flex items-center gap-2">
                        <Check size={18} weight="bold" className="text-status-final" />
                        <span className="font-serif text-[20px] font-medium text-ink">{t('preparation.aiReady')}</span>
                    </div>
                </div>
                <button
                    type="button"
                    onClick={handleStart}
                    className="text-[13px] font-medium text-ink-faint transition-colors hover:text-ink"
                >
                    {t('preparation.reRun')}
                </button>
            </div>
        );
    }

    // Not prepared / failed state — show CTA card
    return (
        <div className="flex items-center justify-between rounded-lg bg-surface-card p-5">
            <div className="flex flex-1 flex-col gap-2">
                <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">
                    {t('preparation.title')}
                </span>
                <div className="flex items-center gap-1.5">
                    <Sparkle size={18} weight="fill" className="shrink-0 text-accent" />
                    <span className="font-serif text-[20px] font-medium text-ink">
                        {t('preparation.unlockInsights')}
                    </span>
                </div>
                <p className="text-[13px] text-ink-muted">
                    {t('preparation.unlockDescription')}
                </p>
                {error && <span className="text-[12px] text-red-600">{error}</span>}
                {status?.status === 'failed' && (
                    <span className="text-[12px] text-red-600">{status.error_message ?? t('preparation.failed')}</span>
                )}
            </div>
            <div className="flex w-[200px] shrink-0 flex-col items-center gap-2">
                <button
                    type="button"
                    onClick={handleStart}
                    disabled={starting}
                    className="w-full justify-center rounded-lg bg-ink px-5 py-2.5 text-[13px] font-medium text-surface transition-colors hover:bg-ink/90 disabled:opacity-50"
                >
                    {starting ? t('preparation.starting') : t('preparation.prepareManuscript')}
                </button>
                <span className="text-[11px] text-ink-faint">{t('preparation.setupTime')}</span>
            </div>
        </div>
    );
}
