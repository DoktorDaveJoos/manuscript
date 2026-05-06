import {
    ChevronDown,
    ChevronUp,
    Ellipsis,
    ExternalLink,
    Link2Off,
    Plus,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/DropdownMenu';
import Textarea from '@/components/ui/Textarea';
import { cn } from '@/lib/utils';
import type { BeatStatus } from '@/types/models';

const STATUS_OPTIONS: BeatStatus[] = ['planned', 'fulfilled', 'abandoned'];

const STATUS_TONE: Record<BeatStatus, string> = {
    planned: 'bg-neutral-bg text-ink-muted',
    fulfilled: 'bg-emerald-100 text-emerald-700',
    abandoned: 'bg-zinc-100 text-zinc-500',
};

export type PlotPanelBeat = {
    id: number;
    title: string;
    description: string | null;
    status: BeatStatus;
    sort_order: number;
    plot_point_id: number;
    chapters: { id: number; title: string }[];
};

type Props = {
    beat: PlotPanelBeat;
    isConnected: boolean;
    isExpanded: boolean;
    onToggleExpand: () => void;
    onConnect?: () => void;
    onDisconnect?: () => void;
    onDismiss?: () => void;
    onUpdate?: (data: {
        title?: string;
        description?: string | null;
        status?: BeatStatus;
    }) => void;
    plotBoardUrl: string;
};

export default function PlotPanelCard({
    beat,
    isConnected,
    isExpanded,
    onToggleExpand,
    onConnect,
    onDisconnect,
    onDismiss,
    onUpdate,
    plotBoardUrl,
}: Props) {
    const { t } = useTranslation('plot-panel');
    const [title, setTitle] = useState(beat.title);
    const [description, setDescription] = useState(beat.description ?? '');
    const [status, setStatus] = useState<BeatStatus>(beat.status);
    const [syncedBeatId, setSyncedBeatId] = useState(beat.id);

    if (beat.id !== syncedBeatId) {
        setSyncedBeatId(beat.id);
        setTitle(beat.title);
        setDescription(beat.description ?? '');
        setStatus(beat.status);
    }

    const commitTitle = () => {
        if (!onUpdate) return;
        const next = title.trim();
        if (next && next !== beat.title) onUpdate({ title: next });
    };

    const commitDescription = () => {
        if (!onUpdate) return;
        const next = description;
        if (next !== (beat.description ?? '')) {
            onUpdate({ description: next === '' ? null : next });
        }
    };

    const commitStatus = (next: BeatStatus) => {
        setStatus(next);
        onUpdate?.({ status: next });
    };

    return (
        <div className="rounded-lg bg-neutral-bg/50">
            <button
                type="button"
                onClick={onToggleExpand}
                className="flex w-full items-center gap-2.5 p-2.5 text-left"
            >
                <div className="min-w-0 flex-1">
                    <p className="truncate text-[13px] font-medium text-ink">
                        {beat.title}
                    </p>
                </div>
                <span
                    className={cn(
                        'shrink-0 rounded px-1.5 py-0.5 text-[10px] font-medium tracking-wide uppercase',
                        STATUS_TONE[beat.status],
                    )}
                >
                    {t(`status.${beat.status}`)}
                </span>
                {!isConnected && onDismiss ? (
                    <span
                        role="button"
                        tabIndex={0}
                        onClick={(e) => {
                            e.stopPropagation();
                            onDismiss();
                        }}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter' || e.key === ' ') {
                                e.stopPropagation();
                                onDismiss();
                            }
                        }}
                        className="shrink-0 rounded p-0.5 text-ink-faint hover:text-ink"
                    >
                        <X size={12} />
                    </span>
                ) : (
                    <span className="shrink-0 text-ink-faint">
                        {isExpanded ? (
                            <ChevronUp size={14} />
                        ) : (
                            <ChevronDown size={14} />
                        )}
                    </span>
                )}
            </button>

            {isExpanded && (
                <div className="space-y-2.5 border-t border-border-light px-2.5 pt-2.5 pb-2.5">
                    <input
                        value={title}
                        onChange={(e) => setTitle(e.target.value)}
                        onBlur={commitTitle}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                (e.target as HTMLInputElement).blur();
                            }
                        }}
                        className="w-full rounded border border-border-light bg-surface px-2 py-1 text-[13px] font-medium text-ink focus:border-ink-muted focus:outline-none"
                        readOnly={!onUpdate}
                    />

                    <Textarea
                        value={description}
                        onChange={(e) => setDescription(e.target.value)}
                        onBlur={commitDescription}
                        rows={3}
                        readOnly={!onUpdate}
                        placeholder={t('description')}
                        className="text-[13px]"
                    />

                    <div className="flex items-center justify-between gap-2">
                        <div className="flex items-center gap-1">
                            {STATUS_OPTIONS.map((opt) => (
                                <button
                                    key={opt}
                                    type="button"
                                    onClick={() => commitStatus(opt)}
                                    disabled={!onUpdate}
                                    className={cn(
                                        'rounded px-1.5 py-0.5 text-[11px] transition-colors disabled:cursor-not-allowed disabled:opacity-60',
                                        status === opt
                                            ? STATUS_TONE[opt]
                                            : 'text-ink-faint hover:text-ink',
                                    )}
                                >
                                    {t(`status.${opt}`)}
                                </button>
                            ))}
                        </div>

                        {isConnected && onDisconnect ? (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <button
                                        type="button"
                                        className="rounded p-1 text-ink-faint hover:bg-neutral-bg hover:text-ink"
                                    >
                                        <Ellipsis size={14} />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        onClick={() =>
                                            window.open(plotBoardUrl, '_blank')
                                        }
                                    >
                                        <ExternalLink size={14} />
                                        {t('viewOnPlotBoard')}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onClick={onDisconnect}
                                        className="text-red-600 focus:text-red-700"
                                    >
                                        <Link2Off size={14} />
                                        {t('disconnectFromChapter')}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        ) : onConnect ? (
                            <button
                                type="button"
                                onClick={onConnect}
                                className="flex items-center gap-1 rounded bg-ink px-2 py-1 text-[11px] text-surface hover:bg-ink-muted"
                            >
                                <Plus size={12} />
                                {t('connectToChapter')}
                            </button>
                        ) : null}
                    </div>
                </div>
            )}
        </div>
    );
}
