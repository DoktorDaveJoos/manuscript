import ProFeatureLock from '@/components/ui/ProFeatureLock';
import { useAiFeatures } from '@/hooks/useAiFeatures';
import { getXsrfToken } from '@/lib/csrf';
import { cn } from '@/lib/utils';
import type { Book } from '@/types/models';
import { CaretLeft, CaretRight, Heartbeat, Lightning, MagnifyingGlass, Sparkle } from '@phosphor-icons/react';
import { useCallback, useState } from 'react';

type TensionPoint = {
    chapter_id: number;
    title: string;
    reader_order: number;
    tension_score: number;
};

type ActionResult = {
    message?: string;
    tension_arc?: TensionPoint[];
    generated_at?: string;
};

type ActionKey = 'tension' | 'health' | 'holes' | 'beats';

const actions: { key: ActionKey; label: string; description: string; icon: typeof Lightning; route: string }[] = [
    {
        key: 'tension',
        label: 'Generate Tension Arc',
        description: 'Visualize tension across chapters',
        icon: Lightning,
        route: 'plot/ai/tension',
    },
    {
        key: 'health',
        label: 'Run Plot Health',
        description: 'Evaluate overall structure',
        icon: Heartbeat,
        route: 'plot/ai/health',
    },
    {
        key: 'holes',
        label: 'Detect Plot Holes',
        description: 'Find gaps and contradictions',
        icon: MagnifyingGlass,
        route: 'plot/ai/holes',
    },
    {
        key: 'beats',
        label: 'Suggest Next Beats',
        description: 'AI-recommended plot points',
        icon: Sparkle,
        route: 'plot/ai/beats',
    },
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
    const { visible, usable, licensed } = useAiFeatures();

    const [runningAction, setRunningAction] = useState<ActionKey | null>(null);
    const [results, setResults] = useState<Partial<Record<ActionKey, ActionResult>>>({});
    const [errors, setErrors] = useState<Partial<Record<ActionKey, string>>>({});

    const handleAction = useCallback(
        async (action: (typeof actions)[number]) => {
            setRunningAction(action.key);
            setErrors((prev) => ({ ...prev, [action.key]: undefined }));

            try {
                const response = await fetch(`/books/${book.id}/${action.route}`, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                    },
                });

                const data = await response.json();

                if (!response.ok) {
                    setErrors((prev) => ({ ...prev, [action.key]: data.message ?? 'Action failed' }));
                } else {
                    setResults((prev) => ({ ...prev, [action.key]: data }));

                    if (action.key === 'tension' && data.tension_arc) {
                        onTensionArcGenerated?.(data.tension_arc);
                    }
                }
            } catch {
                setErrors((prev) => ({ ...prev, [action.key]: 'Network error. Please try again.' }));
            } finally {
                setRunningAction(null);
            }
        },
        [book.id, onTensionArcGenerated],
    );

    if (!visible) return null;

    return (
        <aside
            className={cn(
                'flex h-full shrink-0 flex-col border-l border-[#ECEAE4] bg-white transition-[width] duration-200 ease-in-out',
                isOpen ? 'w-[280px]' : 'w-10',
            )}
        >
            {isOpen ? (
                <>
                    {/* Header */}
                    <div className="flex h-12 items-center justify-between border-b border-[#ECEAE4] px-4">
                        <span className="text-xs font-semibold uppercase tracking-[0.06em] text-[#2D2A26]">
                            AI Actions
                        </span>
                        <button
                            type="button"
                            onClick={onToggle}
                            className="flex size-6 items-center justify-center rounded text-[#8A857D] transition-colors hover:text-[#2D2A26]"
                        >
                            <CaretRight size={14} weight="bold" />
                        </button>
                    </div>

                    {licensed ? (
                        <div className="flex flex-1 flex-col gap-3 overflow-y-auto p-4">
                            {usable ? (
                                actions.map((action) => {
                                    const Icon = action.icon;
                                    const isRunning = runningAction === action.key;
                                    const result = results[action.key];
                                    const error = errors[action.key];

                                    return (
                                        <div key={action.key} className="flex flex-col gap-2">
                                            <button
                                                type="button"
                                                onClick={() => handleAction(action)}
                                                disabled={isRunning || runningAction !== null}
                                                className="flex items-center gap-3 rounded-lg border border-[#ECEAE4] px-3 py-2.5 text-left transition-colors hover:bg-[#FAFAF7] disabled:opacity-50"
                                            >
                                                <Icon size={18} weight="regular" className="shrink-0 text-[#8A857D]" />
                                                <div className="flex flex-col">
                                                    <span className="text-[13px] font-medium text-[#2D2A26]">
                                                        {isRunning ? 'Running...' : action.label}
                                                    </span>
                                                    <span className="text-[11px] text-[#8A857D]">
                                                        {action.description}
                                                    </span>
                                                </div>
                                            </button>

                                            {error && (
                                                <div className="rounded bg-red-50 px-3 py-2 text-xs text-red-600">
                                                    {error}
                                                </div>
                                            )}

                                            {result && !error && (
                                                <div className="rounded bg-[#FAFAF7] px-3 py-2 text-xs text-[#5A574F]">
                                                    {result.message && <p>{result.message}</p>}
                                                    {result.tension_arc && (
                                                        <div className="flex flex-col gap-1">
                                                            <p className="font-medium">Tension Arc</p>
                                                            {result.tension_arc.map((point) => (
                                                                <div
                                                                    key={point.chapter_id}
                                                                    className="flex items-center justify-between"
                                                                >
                                                                    <span className="truncate">
                                                                        {point.reader_order + 1}. {point.title}
                                                                    </span>
                                                                    <span className="ml-2 shrink-0 font-medium">
                                                                        {point.tension_score}/10
                                                                    </span>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })
                            ) : (
                                <p className="text-xs leading-relaxed text-[#8A857D]">
                                    AI is not configured. Go to{' '}
                                    <a
                                        href="/settings/ai"
                                        className="font-medium text-[#5A574F] underline decoration-[#5A574F]/30 hover:decoration-[#5A574F]"
                                    >
                                        AI Settings
                                    </a>{' '}
                                    to set up a provider.
                                </p>
                            )}
                        </div>
                    ) : (
                        <ProFeatureLock>
                            <div className="flex flex-1 flex-col gap-3 p-4 opacity-40">
                                {actions.map((action) => {
                                    const Icon = action.icon;
                                    return (
                                        <div
                                            key={action.key}
                                            className="flex items-center gap-3 rounded-lg border border-[#ECEAE4] px-3 py-2.5"
                                        >
                                            <Icon size={18} className="shrink-0 text-[#8A857D]" />
                                            <div className="flex flex-col">
                                                <span className="text-[13px] font-medium text-[#2D2A26]">
                                                    {action.label}
                                                </span>
                                                <span className="text-[11px] text-[#8A857D]">
                                                    {action.description}
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
                    className="flex h-full w-full flex-col items-center gap-3 pt-3 transition-colors hover:bg-[#FAFAF7]"
                >
                    <span className="flex size-6 items-center justify-center text-[#8A857D]">
                        <CaretLeft size={14} weight="bold" />
                    </span>
                    <span className="flex size-5 items-center justify-center text-[#8A857D]">
                        <Lightning size={16} weight="fill" />
                    </span>
                </button>
            )}
        </aside>
    );
}
