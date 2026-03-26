import { BookOpen, Loader2, RefreshCw, Sparkles, Wand2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import {
    beautifyAll,
    bulkRevisionStatus,
    reviseAll,
} from '@/actions/App/Http/Controllers/AiController';
import Button from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import Dialog from '@/components/ui/Dialog';
import SectionLabel from '@/components/ui/SectionLabel';
import { TOTAL_PHASES, useAiPreparation } from '@/hooks/useAiPreparation';
import { cn, formatTimeAgo, jsonFetchHeaders } from '@/lib/utils';
import type { AiPreparationStatus } from '@/types/models';

function CommandCard({
    icon,
    title,
    description,
    footer,
    disabled = false,
    disabledMessage,
}: {
    icon: ReactNode;
    title: string;
    description: ReactNode;
    footer: ReactNode;
    disabled?: boolean;
    disabledMessage?: string;
}) {
    return (
        <Card
            className={cn(
                'flex flex-1 flex-col justify-between p-5',
                disabled && 'opacity-50',
            )}
        >
            <div className="flex flex-col gap-3">
                <div className="flex size-10 items-center justify-center rounded-xl bg-neutral-bg">
                    {icon}
                </div>
                <div className="flex flex-col gap-1">
                    <span className="text-[14px] font-semibold text-ink">
                        {title}
                    </span>
                    <span className="text-[12px] leading-[1.5] text-ink-muted">
                        {description}
                    </span>
                </div>
            </div>
            <div className="mt-4 border-t border-border-subtle pt-3">
                {disabled && disabledMessage ? (
                    <span className="text-[12px] text-ink-faint">
                        {disabledMessage}
                    </span>
                ) : (
                    footer
                )}
            </div>
        </Card>
    );
}

function ProgressBar({
    label,
    rightLabel,
    percent,
}: {
    label: string;
    rightLabel: string;
    percent: number;
}) {
    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-center justify-between">
                <span className="text-[12px] font-medium text-ink">
                    {label}
                </span>
                <span className="text-[11px] text-ink-faint">{rightLabel}</span>
            </div>
            <div className="h-1.5 overflow-hidden rounded-full bg-neutral-bg">
                <div
                    className="h-full rounded-full bg-accent transition-all duration-300"
                    style={{ width: `${percent}%` }}
                />
            </div>
        </div>
    );
}

type BulkStatus = {
    type: 'beautify' | 'revise';
    status: 'running' | 'completed' | 'failed' | 'idle';
    total: number;
    processed: number;
    error: string | null;
};

function BulkActionDialog({
    open,
    onClose,
    onConfirm,
    title,
    body,
    hint,
    confirmLabel,
    cancelLabel,
}: {
    open: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title: string;
    body: string;
    hint: string;
    confirmLabel: string;
    cancelLabel: string;
}) {
    if (!open) return null;

    return (
        <Dialog onClose={onClose} width={440}>
            <div className="flex flex-col gap-4">
                <h2 className="font-serif text-[20px] font-semibold text-ink">
                    {title}
                </h2>
                <p className="text-[13px] leading-[1.6] text-ink-muted">
                    {body}
                </p>
                <p className="rounded-lg bg-neutral-bg px-3 py-2 text-[12px] leading-[1.5] text-ink-muted">
                    {hint}
                </p>
                <div className="flex justify-end gap-2 pt-2">
                    <Button variant="ghost" onClick={onClose}>
                        {cancelLabel}
                    </Button>
                    <Button variant="primary" onClick={onConfirm}>
                        {confirmLabel}
                    </Button>
                </div>
            </div>
        </Dialog>
    );
}

function useBulkRevisionStatus(bookId: number) {
    const [bulkStatus, setBulkStatus] = useState<BulkStatus | null>(null);
    const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const isRunning = bulkStatus?.status === 'running';

    useEffect(() => {
        fetch(bulkRevisionStatus.url(bookId), {
            headers: { Accept: 'application/json' },
        })
            .then((r) => r.json())
            .then((data) => {
                if (data.status !== 'idle') setBulkStatus(data);
            })
            .catch(() => {});
    }, [bookId]);

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
                const res = await fetch(bulkRevisionStatus.url(bookId), {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) return;
                const data = await res.json();
                setBulkStatus((prev) => {
                    if (
                        prev?.status === data.status &&
                        prev?.processed === data.processed
                    ) {
                        return prev;
                    }
                    return data;
                });
            } catch {
                // Silently retry
            }
        }, 3000);

        return () => {
            if (pollRef.current) clearInterval(pollRef.current);
        };
    }, [bookId, isRunning]);

    const startBulk = useCallback(
        async (type: 'beautify' | 'revise') => {
            const url =
                type === 'beautify'
                    ? beautifyAll.url(bookId)
                    : reviseAll.url(bookId);

            const res = await fetch(url, {
                method: 'POST',
                headers: jsonFetchHeaders(),
            });

            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw new Error(data.message || 'Failed to start');
            }

            setBulkStatus({
                type,
                status: 'running',
                total: 0,
                processed: 0,
                error: null,
            });
        },
        [bookId],
    );

    return { bulkStatus, isRunning, startBulk };
}

export default function CommandCenter({
    bookId,
    initialStatus,
    chapterCount,
}: {
    bookId: number;
    initialStatus: AiPreparationStatus | null;
    chapterCount: number;
}) {
    const { t } = useTranslation('ai-dashboard');
    const {
        status,
        isRunning: prepRunning,
        starting,
        handleStart,
    } = useAiPreparation(bookId, initialStatus);
    const {
        bulkStatus,
        isRunning: bulkRunning,
        startBulk,
    } = useBulkRevisionStatus(bookId);

    const [dialogOpen, setDialogOpen] = useState<'beautify' | 'revise' | null>(
        null,
    );

    const lastPrepTime = status?.updated_at
        ? formatTimeAgo(status.updated_at, t, 'commandCenter.timeAgo')
        : null;

    const handleBulkAction = useCallback(
        async (type: 'beautify' | 'revise') => {
            setDialogOpen(null);
            try {
                await startBulk(type);
            } catch {
                // TODO: show error toast
            }
        },
        [startBulk],
    );

    const actionsDisabled = prepRunning || bulkRunning;

    const prepProgress =
        prepRunning && status
            ? (() => {
                  const completedCount = status.completed_phases?.length ?? 0;
                  const percent =
                      status.current_phase_total > 0
                          ? (status.current_phase_progress /
                                status.current_phase_total) *
                            100
                          : 0;
                  const phaseKey = status.current_phase
                      ? `commandCenter.preparation.phase.${status.current_phase}`
                      : 'commandCenter.preparation.phase.default';
                  return (
                      <ProgressBar
                          label={t(phaseKey)}
                          rightLabel={t('commandCenter.preparation.step', {
                              current: completedCount + 1,
                              total: TOTAL_PHASES,
                          })}
                          percent={percent}
                      />
                  );
              })()
            : null;

    const bulkProgress = (type: 'beautify' | 'revise') => {
        if (!bulkRunning || bulkStatus?.type !== type) return null;
        const percent =
            bulkStatus.total > 0
                ? (bulkStatus.processed / bulkStatus.total) * 100
                : 0;
        const prefix =
            type === 'beautify'
                ? 'commandCenter.beautify'
                : 'commandCenter.prosePass';
        return (
            <ProgressBar
                label={`${t(`${prefix}.title`)}…`}
                rightLabel={`${bulkStatus.processed} / ${bulkStatus.total}`}
                percent={percent}
            />
        );
    };

    return (
        <div className="flex flex-col gap-3">
            <SectionLabel>{t('commandCenter.label')}</SectionLabel>
            <div className="flex gap-4">
                <CommandCard
                    icon={
                        prepRunning ? (
                            <Loader2
                                size={20}
                                strokeWidth={1.5}
                                className="animate-spin text-accent"
                            />
                        ) : (
                            <Sparkles
                                size={20}
                                strokeWidth={1.5}
                                className="text-ink-muted"
                            />
                        )
                    }
                    title={t('commandCenter.preparation.title')}
                    description={
                        prepProgress ??
                        t('commandCenter.preparation.description')
                    }
                    footer={
                        <div className="flex items-center justify-between">
                            {lastPrepTime && !prepRunning && (
                                <span className="text-[11px] text-ink-faint">
                                    {t('commandCenter.preparation.lastRun', {
                                        time: lastPrepTime,
                                    })}
                                </span>
                            )}
                            <button
                                type="button"
                                onClick={handleStart}
                                disabled={starting || prepRunning}
                                className="ml-auto inline-flex items-center gap-1.5 text-[12px] font-medium text-accent transition-colors hover:text-accent/80 disabled:opacity-50"
                            >
                                <RefreshCw
                                    size={12}
                                    className={cn(
                                        prepRunning && 'animate-spin',
                                    )}
                                />
                                {t('commandCenter.preparation.reanalyze')}
                            </button>
                        </div>
                    }
                />

                <CommandCard
                    icon={
                        bulkRunning && bulkStatus?.type === 'beautify' ? (
                            <Loader2
                                size={20}
                                strokeWidth={1.5}
                                className="animate-spin text-accent"
                            />
                        ) : (
                            <Wand2
                                size={20}
                                strokeWidth={1.5}
                                className="text-ink-muted"
                            />
                        )
                    }
                    title={t('commandCenter.beautify.title')}
                    description={
                        bulkProgress('beautify') ??
                        t('commandCenter.beautify.description')
                    }
                    disabled={prepRunning}
                    disabledMessage={t(
                        'commandCenter.beautify.requiresPreparation',
                    )}
                    footer={
                        <button
                            type="button"
                            onClick={() => setDialogOpen('beautify')}
                            disabled={actionsDisabled}
                            className="inline-flex items-center text-[12px] font-medium text-accent transition-colors hover:text-accent/80 disabled:opacity-50"
                        >
                            {t('commandCenter.beautify.runAll')}
                        </button>
                    }
                />

                <CommandCard
                    icon={
                        bulkRunning && bulkStatus?.type === 'revise' ? (
                            <Loader2
                                size={20}
                                strokeWidth={1.5}
                                className="animate-spin text-accent"
                            />
                        ) : (
                            <BookOpen
                                size={20}
                                strokeWidth={1.5}
                                className="text-ink-muted"
                            />
                        )
                    }
                    title={t('commandCenter.prosePass.title')}
                    description={
                        bulkProgress('revise') ??
                        t('commandCenter.prosePass.description')
                    }
                    disabled={prepRunning}
                    disabledMessage={t(
                        'commandCenter.prosePass.requiresPreparation',
                    )}
                    footer={
                        <button
                            type="button"
                            onClick={() => setDialogOpen('revise')}
                            disabled={actionsDisabled}
                            className="inline-flex items-center text-[12px] font-medium text-accent transition-colors hover:text-accent/80 disabled:opacity-50"
                        >
                            {t('commandCenter.prosePass.runAll')}
                        </button>
                    }
                />
            </div>

            <BulkActionDialog
                open={dialogOpen === 'beautify'}
                onClose={() => setDialogOpen(null)}
                onConfirm={() => handleBulkAction('beautify')}
                title={t('commandCenter.beautify.confirmTitle')}
                body={t('commandCenter.beautify.confirmBody', {
                    count: chapterCount,
                })}
                hint={t('commandCenter.beautify.perChapterHint')}
                confirmLabel={t('commandCenter.beautify.confirm')}
                cancelLabel={t('commandCenter.beautify.cancel')}
            />

            <BulkActionDialog
                open={dialogOpen === 'revise'}
                onClose={() => setDialogOpen(null)}
                onConfirm={() => handleBulkAction('revise')}
                title={t('commandCenter.prosePass.confirmTitle')}
                body={t('commandCenter.prosePass.confirmBody', {
                    count: chapterCount,
                })}
                hint={t('commandCenter.prosePass.perChapterHint')}
                confirmLabel={t('commandCenter.prosePass.confirm')}
                cancelLabel={t('commandCenter.prosePass.cancel')}
            />
        </div>
    );
}
