import { useAiPreparation, TOTAL_PHASES, phaseLabels } from '@/hooks/useAiPreparation';
import type { AiPreparationStatus } from '@/types/models';
import { Check, Lock, Sparkle } from '@phosphor-icons/react';

export default function AiPreparation({
    bookId,
    aiEnabled,
    initialStatus,
}: {
    bookId: number;
    aiEnabled: boolean;
    initialStatus: AiPreparationStatus | null;
}) {
    const { status, isRunning, starting, error, handleStart } = useAiPreparation(bookId, initialStatus);

    // AI not enabled
    if (!aiEnabled) {
        return (
            <div className="flex items-center justify-between rounded-lg bg-surface-card px-6 py-6">
                <div className="flex flex-col gap-2">
                    <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">
                        AI Preparation
                    </span>
                    <div className="flex items-center gap-2">
                        <Lock size={18} weight="fill" className="shrink-0 text-ink-faint" />
                        <span className="font-serif text-[20px] font-medium text-ink-muted">
                            Configure AI to unlock insights
                        </span>
                    </div>
                    <p className="text-[13px] text-ink-muted">
                        Add an API key in settings to enable AI-powered manuscript analysis.
                    </p>
                </div>
            </div>
        );
    }

    // Running state
    if (isRunning && status) {
        const completedCount = status.completed_phases?.length ?? 0;
        const currentPhase = status.current_phase;
        const phaseLabel = currentPhase ? phaseLabels[currentPhase] : 'Starting...';
        const overallProgress = Math.round((completedCount / TOTAL_PHASES) * 100);

        return (
            <div className="flex flex-col gap-4 rounded-lg bg-surface-card px-6 py-6">
                <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">
                    AI Preparation
                </span>
                <div className="flex items-center gap-3">
                    <span className="inline-block size-4 animate-spin rounded-full border-2 border-ink-faint border-t-ink" />
                    <div className="flex flex-col gap-0.5">
                        <span className="text-[14px] font-medium text-ink">{phaseLabel}...</span>
                        <span className="text-[12px] text-ink-faint">
                            Phase {completedCount + 1}/{TOTAL_PHASES} &middot; {overallProgress}% overall
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
                        AI Preparation
                    </span>
                    <div className="flex items-center gap-2">
                        <Check size={18} weight="bold" className="text-status-final" />
                        <span className="font-serif text-[20px] font-medium text-ink">AI ready</span>
                    </div>
                </div>
                <button
                    type="button"
                    onClick={handleStart}
                    className="text-[13px] font-medium text-ink-faint transition-colors hover:text-ink"
                >
                    Re-run
                </button>
            </div>
        );
    }

    // Not prepared / failed state — show CTA card
    return (
        <div className="flex items-center justify-between rounded-lg bg-surface-card p-5">
            <div className="flex flex-1 flex-col gap-2">
                <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">
                    AI Preparation
                </span>
                <div className="flex items-center gap-1.5">
                    <Sparkle size={18} weight="fill" className="shrink-0 text-accent" />
                    <span className="font-serif text-[20px] font-medium text-ink">
                        Unlock AI-powered insights
                    </span>
                </div>
                <p className="text-[13px] text-ink-muted">
                    Analyzes your manuscript to extract characters, map entities, understand your writing style, and
                    build a story bible — enabling all AI features.
                </p>
                {error && <span className="text-[12px] text-red-600">{error}</span>}
                {status?.status === 'failed' && (
                    <span className="text-[12px] text-red-600">{status.error_message ?? 'Preparation failed'}</span>
                )}
            </div>
            <div className="flex w-[200px] shrink-0 flex-col items-center gap-2">
                <button
                    type="button"
                    onClick={handleStart}
                    disabled={starting}
                    className="w-full justify-center rounded-lg bg-ink px-5 py-2.5 text-[13px] font-medium text-surface transition-colors hover:bg-ink/90 disabled:opacity-50"
                >
                    {starting ? 'Starting...' : 'Prepare manuscript'}
                </button>
                <span className="text-[11px] text-ink-faint">One-time setup, takes ~2 min</span>
            </div>
        </div>
    );
}
