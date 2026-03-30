import { Link, usePage } from '@inertiajs/react';
import { Brain, Crown, RefreshCw, Sparkle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { index as settingsIndex } from '@/actions/App/Http/Controllers/SettingsController';
import { useAiFeatures } from '@/hooks/useAiFeatures';
import { useAiPreparation, TOTAL_PHASES } from '@/hooks/useAiPreparation';
import type { AiPreparationStatus } from '@/types/models';

export default function AiPreparation({
    bookId,
    initialStatus,
}: {
    bookId: number;
    initialStatus: AiPreparationStatus | null;
}) {
    const { t } = useTranslation('ai');
    const pageUrl = usePage().url;
    const { visible, usable, licensed } = useAiFeatures();
    const { status, isRunning, starting, error, handleStart } =
        useAiPreparation(bookId, initialStatus);

    function formatTimeAgo(dateString: string): string {
        const now = new Date();
        const date = new Date(dateString);
        const seconds = Math.floor((now.getTime() - date.getTime()) / 1000);
        if (seconds < 60) return t('preparation.timeAgo.justNow', 'just now');
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60)
            return t('preparation.timeAgo.minutes', {
                count: minutes,
                defaultValue: '{{count}}m ago',
            });
        const hours = Math.floor(minutes / 60);
        if (hours < 24)
            return t('preparation.timeAgo.hours', {
                count: hours,
                defaultValue: '{{count}}h ago',
            });
        const days = Math.floor(hours / 24);
        return t('preparation.timeAgo.days', {
            count: days,
            defaultValue: '{{count}}d ago',
        });
    }

    if (!visible) return null;

    // Not usable — show upgrade/configure banner
    if (!usable) {
        if (!licensed) {
            return (
                <div className="flex items-center gap-4">
                    <div className="flex size-[44px] shrink-0 items-center justify-center rounded-full bg-ink/[0.06]">
                        <Brain size={20} className="text-ink-muted" />
                    </div>
                    <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                        <span className="text-sm font-medium text-ink">
                            {t('preparation.title')}
                        </span>
                        <p className="text-xs text-ink-faint">
                            {t(
                                'preparation.unlockAiDescription',
                                'Unlock AI-powered manuscript analysis',
                            )}
                        </p>
                    </div>
                    <a
                        href="https://getmanuscript.app"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-ink px-4 py-2 text-[13px] font-medium text-surface transition-colors hover:bg-ink/80"
                    >
                        <Crown size={14} />
                        {t('preparation.upgradeToPro', 'Upgrade to PRO')}
                    </a>
                </div>
            );
        }

        // Licensed but not configured
        return (
            <div className="flex items-center gap-4">
                <div className="flex size-[44px] shrink-0 items-center justify-center rounded-full bg-ink/[0.06]">
                    <Brain size={20} className="text-ink-muted" />
                </div>
                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                    <span className="text-sm font-medium text-ink">
                        {t('preparation.setupProvider')}
                    </span>
                    <p className="text-xs text-ink-faint">
                        {t('preparation.addApiKey')}
                    </p>
                </div>
                <Link
                    href={settingsIndex.url({ query: { from: pageUrl } })}
                    className="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-ink px-4 py-2 text-[13px] font-medium text-surface transition-colors hover:bg-ink/80"
                >
                    {t('preparation.goToSettings')}
                </Link>
            </div>
        );
    }

    // Running state
    if (isRunning && status) {
        const completedCount = status.completed_phases?.length ?? 0;
        const currentPhase = status.current_phase;
        const phaseLabel = currentPhase
            ? t(`phase.${currentPhase}`)
            : t('preparation.starting');
        const overallProgress = Math.round(
            (completedCount / TOTAL_PHASES) * 100,
        );

        return (
            <div className="flex items-center gap-4">
                <div className="flex size-[44px] shrink-0 items-center justify-center rounded-full bg-ink/[0.06]">
                    <span className="inline-block size-5 animate-spin rounded-full border-2 border-ink-faint border-t-ink" />
                </div>
                <div className="flex min-w-0 flex-1 flex-col gap-2">
                    <div className="flex flex-col gap-0.5">
                        <span className="text-sm font-medium text-ink">
                            {phaseLabel}...
                        </span>
                        <span className="text-xs text-ink-faint">
                            {t('preparation.phaseProgress', {
                                current: completedCount + 1,
                                total: TOTAL_PHASES,
                                percent: overallProgress,
                            })}
                        </span>
                    </div>
                    <div className="h-1.5 overflow-hidden rounded bg-neutral-bg">
                        <div
                            className="h-full rounded bg-accent transition-all duration-500"
                            style={{
                                width: `${overallProgress}%`,
                            }}
                        />
                    </div>
                </div>
            </div>
        );
    }

    // Completed state
    if (status?.status === 'completed') {
        const chaptersAnalyzed = status.total_chapters;
        const findingsCount = status.phase_errors?.length ?? 0;

        return (
            <div className="flex items-center gap-4">
                <div className="flex size-[44px] shrink-0 items-center justify-center rounded-full bg-ink/[0.06]">
                    <Brain size={20} className="text-ink-muted" />
                </div>
                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                    <span className="text-sm font-medium text-ink">
                        {t('preparation.title')}
                    </span>
                    <span className="text-xs text-ink-faint">
                        {chaptersAnalyzed}{' '}
                        {t('preparation.chaptersAnalyzed', 'chapters analyzed')}
                        {' · '}
                        {findingsCount} {t('preparation.findings', 'findings')}
                    </span>
                    <span className="text-[11px] text-ink-faint italic">
                        {t('preparation.lastAnalyzed', 'Last analyzed')}{' '}
                        {formatTimeAgo(status.updated_at)}
                    </span>
                </div>
                <button
                    type="button"
                    onClick={handleStart}
                    className="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-ink px-4 py-2 text-[13px] font-medium text-surface transition-colors hover:bg-ink/80"
                >
                    <RefreshCw size={14} />
                    {t('preparation.reRunAnalysis', 'Re-run Analysis')}
                </button>
            </div>
        );
    }

    // Not prepared / failed state
    return (
        <div className="flex items-center gap-4">
            <div className="flex size-[44px] shrink-0 items-center justify-center rounded-full bg-ink/[0.06]">
                <Sparkle size={20} className="text-ink-muted" />
            </div>
            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                <span className="text-sm font-medium text-ink">
                    {t('preparation.unlockInsights')}
                </span>
                <p className="text-xs text-ink-faint">
                    {t('preparation.unlockDescription')}
                </p>
                {error && (
                    <span className="text-[12px] text-red-600">{error}</span>
                )}
                {status?.status === 'failed' && (
                    <span className="text-[12px] text-red-600">
                        {status.error_message ?? t('preparation.failed')}
                    </span>
                )}
            </div>
            <button
                type="button"
                onClick={handleStart}
                disabled={starting}
                className="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-ink px-4 py-2 text-[13px] font-medium text-surface transition-colors hover:bg-ink/80 disabled:opacity-50"
            >
                <Sparkle size={14} />
                {starting
                    ? t('preparation.starting')
                    : t('preparation.prepareManuscript')}
            </button>
        </div>
    );
}
