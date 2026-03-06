import { getXsrfToken } from '@/lib/csrf';
import type { AiPreparationStatus, PreparationPhase } from '@/types/models';
import { useCallback, useEffect, useRef, useState } from 'react';

const TOTAL_PHASES = 7;

const phaseLabels: Record<PreparationPhase, string> = {
    chunking: 'Splitting chunks',
    embedding: 'Generating embeddings',
    writing_style: 'Extracting style',
    chapter_analysis: 'Analyzing chapters',
    character_extraction: 'Extracting characters',
    story_bible: 'Building story bible',
    health_analysis: 'Computing health',
};

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
    const [status, setStatus] = useState<AiPreparationStatus | null>(initialStatus);
    const [starting, setStarting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const isRunning = status && !['completed', 'failed'].includes(status.status);

    useEffect(() => {
        if (!isRunning) {
            if (pollRef.current) {
                clearInterval(pollRef.current);
                pollRef.current = null;
            }
            return;
        }

        pollRef.current = setInterval(async () => {
            try {
                const res = await fetch(`/books/${bookId}/ai/prepare/status`, {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) throw new Error();
                const data = await res.json();
                setStatus(data);
            } catch {
                // Silently retry on next interval
            }
        }, 2000);

        return () => {
            if (pollRef.current) clearInterval(pollRef.current);
        };
    }, [bookId, isRunning]);

    const handleStart = useCallback(async () => {
        setStarting(true);
        setError(null);

        try {
            const res = await fetch(`/books/${bookId}/ai/prepare`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
            });

            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw new Error(data.message || 'Failed to start');
            }

            const data = await res.json();
            setStatus(data);
        } catch (e) {
            setError((e as Error).message);
        } finally {
            setStarting(false);
        }
    }, [bookId]);

    if (!licensed) {
        return (
            <button
                type="button"
                disabled
                className="flex cursor-not-allowed items-center gap-2 rounded-md border border-border bg-surface-card px-4 py-2 text-sm text-ink-faint"
                title="Requires Manuscript Pro licence"
            >
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" className="shrink-0">
                    <rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" strokeWidth="1.5" />
                    <path d="M5 7V5a3 3 0 016 0v2" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                </svg>
                Prepare for AI
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
                title="Configure an API key in settings to enable AI features"
            >
                Prepare for AI
            </button>
        );
    }

    if (isRunning) {
        const completedCount = status.completed_phases?.length ?? 0;
        const currentPhase = status.current_phase;
        const phaseLabel = currentPhase ? phaseLabels[currentPhase] : 'Starting...';
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
                            Phase {completedCount + 1}/{TOTAL_PHASES}
                        </span>
                        {status.current_phase_total > 1 && (
                            <span className="text-xs text-ink-faint">
                                &middot; {status.current_phase_progress}/{status.current_phase_total} ({phaseProgress}%)
                            </span>
                        )}
                        <span className="text-xs text-ink-faint">&middot; {overallProgress}% overall</span>
                        {hasErrors && <span className="text-xs text-amber-600">&middot; {status.phase_errors!.length} warning(s)</span>}
                    </div>
                </div>
            </div>
        );
    }

    if (status?.status === 'completed') {
        const hasErrors = status.phase_errors && status.phase_errors.length > 0;

        return (
            <div className="flex items-center gap-2 rounded-md border border-border bg-surface-card px-4 py-2">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" className="text-status-final">
                    <path d="M4 8l3 3 5-6" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
                <span className="text-sm text-ink-muted">AI ready</span>
                {hasErrors && (
                    <span className="text-xs text-amber-600">({status.phase_errors!.length} warning{status.phase_errors!.length !== 1 ? 's' : ''})</span>
                )}
                <button
                    type="button"
                    onClick={handleStart}
                    className="ml-2 text-xs text-ink-faint transition-colors hover:text-ink"
                >
                    Re-run
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
                {starting ? 'Starting...' : 'Prepare for AI'}
            </button>
            {error && <span className="text-xs text-red-600">{error}</span>}
            {status?.status === 'failed' && (
                <span className="text-xs text-red-600">{status.error_message ?? 'Preparation failed'}</span>
            )}
        </div>
    );
}
