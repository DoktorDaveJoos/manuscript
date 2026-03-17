import {
    ChevronLeft,
    ChevronRight,
    HeartPulse,
    Search,
    Sparkle,
    Zap,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useCallback, useState } from 'react';
import { Trans, useTranslation } from 'react-i18next';
import ProFeatureLock from '@/components/ui/ProFeatureLock';
import { useAiFeatures } from '@/hooks/useAiFeatures';
import { getXsrfToken } from '@/lib/csrf';
import { cn } from '@/lib/utils';
import type { Book } from '@/types/models';

type TensionPoint = {
    chapter_id: number;
    title: string;
    reader_order: number;
    tension_score: number;
};

type AnalysisResult = {
    score?: number;
    findings?: string[];
    recommendations?: string[];
    suggestion?: string;
};

type ActionResult = {
    message?: string;
    tension_arc?: TensionPoint[];
    generated_at?: string;
    analysis?: AnalysisResult;
};

type ActionKey = 'tension' | 'health' | 'holes' | 'beats';

const actionDefs: { key: ActionKey; icon: LucideIcon; route: string }[] = [
    { key: 'tension', icon: Zap, route: 'plot/ai/tension' },
    { key: 'health', icon: HeartPulse, route: 'plot/ai/health' },
    { key: 'holes', icon: Search, route: 'plot/ai/holes' },
    { key: 'beats', icon: Sparkle, route: 'plot/ai/beats' },
];

type TensionData = {
    chapter_id: number;
    reader_order: number;
    tension_score: number;
    title?: string;
};

export default function AiActionSidebar({
    book,
    isOpen,
    onToggle,
    onTensionArcGenerated,
}: {
    book: Book;
    isOpen: boolean;
    onToggle: () => void;
    onTensionArcGenerated?: (data: TensionData[]) => void;
}) {
    const { t } = useTranslation('plot');
    const { visible, usable, licensed } = useAiFeatures();

    const [runningAction, setRunningAction] = useState<ActionKey | null>(null);
    const [results, setResults] = useState<
        Partial<Record<ActionKey, ActionResult>>
    >({});
    const [errors, setErrors] = useState<Partial<Record<ActionKey, string>>>(
        {},
    );

    const handleAction = useCallback(
        async (action: (typeof actionDefs)[number]) => {
            setRunningAction(action.key);
            setErrors((prev) => ({ ...prev, [action.key]: undefined }));

            try {
                const response = await fetch(
                    `/books/${book.id}/${action.route}`,
                    {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-XSRF-TOKEN': getXsrfToken(),
                        },
                    },
                );

                const data = await response.json();

                if (!response.ok) {
                    setErrors((prev) => ({
                        ...prev,
                        [action.key]:
                            data.message ?? t('aiActions.actionFailed'),
                    }));
                } else {
                    setResults((prev) => ({ ...prev, [action.key]: data }));

                    if (action.key === 'tension' && data.tension_arc) {
                        onTensionArcGenerated?.(data.tension_arc);
                    }
                }
            } catch {
                setErrors((prev) => ({
                    ...prev,
                    [action.key]: t('aiActions.networkError'),
                }));
            } finally {
                setRunningAction(null);
            }
        },
        [book.id, onTensionArcGenerated, t],
    );

    if (!visible) return null;

    return (
        <aside
            className={cn(
                'flex h-full shrink-0 flex-col border-l border-border bg-surface-card transition-[width] duration-200 ease-in-out',
                isOpen ? 'w-[280px]' : 'w-10',
            )}
        >
            {isOpen ? (
                <>
                    {/* Header */}
                    <div className="flex h-12 items-center justify-between border-b border-border px-4">
                        <span className="text-xs font-semibold tracking-[0.08em] text-ink uppercase">
                            {t('aiActions.header')}
                        </span>
                        <button
                            type="button"
                            onClick={onToggle}
                            className="flex size-6 items-center justify-center rounded text-ink-muted transition-colors hover:text-ink"
                        >
                            <ChevronRight size={14} strokeWidth={2.5} />
                        </button>
                    </div>

                    {licensed ? (
                        <div className="flex flex-1 flex-col gap-3 overflow-y-auto p-4">
                            {usable ? (
                                actionDefs.map((action) => {
                                    const Icon = action.icon;
                                    const isRunning =
                                        runningAction === action.key;
                                    const result = results[action.key];
                                    const error = errors[action.key];

                                    return (
                                        <div
                                            key={action.key}
                                            className="flex flex-col gap-2"
                                        >
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    handleAction(action)
                                                }
                                                disabled={
                                                    isRunning ||
                                                    runningAction !== null
                                                }
                                                className="flex items-center gap-3 rounded-lg border border-border px-3 py-2.5 text-left transition-colors hover:bg-surface disabled:opacity-50"
                                            >
                                                <Icon
                                                    size={18}
                                                    className="shrink-0 text-ink-muted"
                                                />
                                                <div className="flex flex-col">
                                                    <span className="text-[13px] font-medium text-ink">
                                                        {isRunning
                                                            ? t(
                                                                  'aiActions.running',
                                                              )
                                                            : t(
                                                                  `aiActions.${action.key}.label`,
                                                              )}
                                                    </span>
                                                    <span className="text-[11px] text-ink-muted">
                                                        {t(
                                                            `aiActions.${action.key}.description`,
                                                        )}
                                                    </span>
                                                </div>
                                            </button>

                                            {error && (
                                                <div className="rounded bg-red-50 px-3 py-2 text-xs text-red-600">
                                                    {error}
                                                </div>
                                            )}

                                            {result && !error && (
                                                <div className="flex flex-col gap-2 rounded bg-surface px-3 py-2 text-xs text-ink-soft">
                                                    {result.message && (
                                                        <p>{result.message}</p>
                                                    )}
                                                    {result.tension_arc && (
                                                        <div className="flex flex-col gap-1">
                                                            <p className="font-medium">
                                                                {t(
                                                                    'aiActions.tensionArc',
                                                                )}
                                                            </p>
                                                            {result.tension_arc.map(
                                                                (point) => (
                                                                    <div
                                                                        key={
                                                                            point.chapter_id
                                                                        }
                                                                        className="flex items-center justify-between"
                                                                    >
                                                                        <span className="truncate">
                                                                            {point.reader_order +
                                                                                1}
                                                                            .{' '}
                                                                            {
                                                                                point.title
                                                                            }
                                                                        </span>
                                                                        <span className="ml-2 shrink-0 font-medium">
                                                                            {
                                                                                point.tension_score
                                                                            }
                                                                            /10
                                                                        </span>
                                                                    </div>
                                                                ),
                                                            )}
                                                        </div>
                                                    )}
                                                    {result.analysis && (
                                                        <div className="flex flex-col gap-2">
                                                            {result.analysis
                                                                .score !=
                                                                null && (
                                                                <div className="flex items-center justify-between">
                                                                    <span className="font-medium text-ink">
                                                                        {t(
                                                                            'aiActions.score',
                                                                        )}
                                                                    </span>
                                                                    <span
                                                                        className={cn(
                                                                            'font-semibold',
                                                                            result
                                                                                .analysis
                                                                                .score >=
                                                                                7
                                                                                ? 'text-ai-green'
                                                                                : result
                                                                                        .analysis
                                                                                        .score >=
                                                                                    4
                                                                                  ? 'text-status-revised'
                                                                                  : 'text-red-600',
                                                                        )}
                                                                    >
                                                                        {
                                                                            result
                                                                                .analysis
                                                                                .score
                                                                        }
                                                                        /10
                                                                    </span>
                                                                </div>
                                                            )}
                                                            {result.analysis
                                                                .findings &&
                                                                result.analysis
                                                                    .findings
                                                                    .length >
                                                                    0 && (
                                                                    <div className="flex flex-col gap-1.5">
                                                                        <span className="text-[10px] font-semibold tracking-[0.08em] text-ink-muted uppercase">
                                                                            {t(
                                                                                'aiActions.findings',
                                                                            )}
                                                                        </span>
                                                                        {result.analysis.findings.map(
                                                                            (
                                                                                f,
                                                                                i,
                                                                            ) => (
                                                                                <div
                                                                                    key={
                                                                                        i
                                                                                    }
                                                                                    className="flex gap-2"
                                                                                >
                                                                                    <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-accent" />
                                                                                    <span className="text-[11px] leading-[1.4] text-ink-soft">
                                                                                        {
                                                                                            f
                                                                                        }
                                                                                    </span>
                                                                                </div>
                                                                            ),
                                                                        )}
                                                                    </div>
                                                                )}
                                                            {result.analysis
                                                                .recommendations &&
                                                                result.analysis
                                                                    .recommendations
                                                                    .length >
                                                                    0 && (
                                                                    <div className="flex flex-col gap-1.5">
                                                                        <span className="text-[10px] font-semibold tracking-[0.08em] text-ink-muted uppercase">
                                                                            {t(
                                                                                'aiActions.recommendations',
                                                                            )}
                                                                        </span>
                                                                        {result.analysis.recommendations.map(
                                                                            (
                                                                                r,
                                                                                i,
                                                                            ) => (
                                                                                <div
                                                                                    key={
                                                                                        i
                                                                                    }
                                                                                    className="flex gap-2"
                                                                                >
                                                                                    <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-ink-muted" />
                                                                                    <span className="text-[11px] leading-[1.4] text-ink-soft">
                                                                                        {
                                                                                            r
                                                                                        }
                                                                                    </span>
                                                                                </div>
                                                                            ),
                                                                        )}
                                                                    </div>
                                                                )}
                                                            {result.analysis
                                                                .suggestion && (
                                                                <p className="text-[11px] leading-[1.4] text-ink-soft">
                                                                    {
                                                                        result
                                                                            .analysis
                                                                            .suggestion
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })
                            ) : (
                                <p className="text-xs leading-relaxed text-ink-muted">
                                    <Trans
                                        i18nKey="aiActions.notConfigured"
                                        ns="plot"
                                        components={{
                                            1: (
                                                <a
                                                    href="/settings"
                                                    className="font-medium text-ink-soft underline decoration-ink-soft/30 hover:decoration-ink-soft"
                                                />
                                            ),
                                        }}
                                    />
                                </p>
                            )}
                        </div>
                    ) : (
                        <ProFeatureLock>
                            <div className="flex flex-1 flex-col gap-3 p-4 opacity-40">
                                {actionDefs.map((action) => {
                                    const Icon = action.icon;
                                    return (
                                        <div
                                            key={action.key}
                                            className="flex items-center gap-3 rounded-lg border border-border px-3 py-2.5"
                                        >
                                            <Icon
                                                size={18}
                                                className="shrink-0 text-ink-muted"
                                            />
                                            <div className="flex flex-col">
                                                <span className="text-[13px] font-medium text-ink">
                                                    {t(
                                                        `aiActions.${action.key}.label`,
                                                    )}
                                                </span>
                                                <span className="text-[11px] text-ink-muted">
                                                    {t(
                                                        `aiActions.${action.key}.description`,
                                                    )}
                                                </span>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </ProFeatureLock>
                    )}
                </>
            ) : (
                /* Collapsed state */
                <button
                    type="button"
                    onClick={onToggle}
                    className="flex h-full w-full flex-col items-center gap-3 pt-3 transition-colors hover:bg-surface"
                >
                    <span className="flex size-6 items-center justify-center text-ink-muted">
                        <ChevronLeft size={14} strokeWidth={2.5} />
                    </span>
                    <span className="flex size-5 items-center justify-center text-ink-muted">
                        <Zap size={16} fill="currentColor" />
                    </span>
                </button>
            )}
        </aside>
    );
}
