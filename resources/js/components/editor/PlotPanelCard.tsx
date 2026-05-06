import {
    ChevronDown,
    ChevronUp,
    Ellipsis,
    ExternalLink,
    Link2Off,
    Plus,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/DropdownMenu';
import DescriptionBlock from '@/components/wiki/DescriptionBlock';
import { cn } from '@/lib/utils';
import type { BeatStatus } from '@/types/models';

const STATUS_TONE: Record<BeatStatus, string> = {
    planned: 'bg-neutral-bg text-ink-muted',
    fulfilled: 'bg-plot-resolution-bg text-plot-resolution-text',
    abandoned: 'bg-neutral-bg text-ink-faint',
};

export type PlotPanelBeat = {
    id: number;
    title: string;
    description: string | null;
    status: BeatStatus;
    sort_order: number;
    plot_point_id: number;
};

type Props = {
    beat: PlotPanelBeat;
    plotPointTitle: string;
    isConnected: boolean;
    isExpanded: boolean;
    onToggleExpand: () => void;
    onConnect?: () => void;
    onDisconnect?: () => void;
    plotBoardUrl: string;
};

export default function PlotPanelCard({
    beat,
    plotPointTitle,
    isConnected,
    isExpanded,
    onToggleExpand,
    onConnect,
    onDisconnect,
    plotBoardUrl,
}: Props) {
    const { t } = useTranslation('plot-panel');

    return (
        <div className="flex flex-col rounded-lg bg-surface-card ring-1 ring-border-light">
            <button
                type="button"
                onClick={onToggleExpand}
                className="flex items-start gap-2 p-3 text-left"
            >
                <span className="mt-0.5 shrink-0 text-ink-faint">
                    {isExpanded ? (
                        <ChevronUp className="size-3.5" />
                    ) : (
                        <ChevronDown className="size-3.5" />
                    )}
                </span>
                <div className="min-w-0 flex-1">
                    <p className="truncate text-[13px] font-medium text-ink">
                        {beat.title}
                    </p>
                    <p className="truncate text-[11px] text-ink-faint">
                        {plotPointTitle}
                    </p>
                </div>
                <span
                    className={cn(
                        'shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium tracking-wide uppercase',
                        STATUS_TONE[beat.status],
                    )}
                >
                    {t(`status.${beat.status}`)}
                </span>
                {isConnected && (
                    <CardMenu
                        plotBoardUrl={plotBoardUrl}
                        onDisconnect={onDisconnect}
                    />
                )}
            </button>

            {isExpanded && (
                <div className="flex flex-col gap-2.5 border-t border-border-light px-3 pt-2.5 pb-3">
                    {beat.description?.trim() ? (
                        <DescriptionBlock
                            text={beat.description}
                            className="text-[12px] leading-relaxed text-ink-muted"
                        />
                    ) : (
                        <p className="text-[12px] text-ink-faint italic">
                            {t('noDescription')}
                        </p>
                    )}

                    {!isConnected && onConnect && (
                        <button
                            type="button"
                            onClick={onConnect}
                            className="flex items-center justify-center gap-1.5 self-start rounded-md bg-ink px-2.5 py-1 text-[12px] font-medium text-surface transition-colors hover:bg-ink-muted"
                        >
                            <Plus className="size-3" />
                            {t('connectToChapter')}
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}

function CardMenu({
    plotBoardUrl,
    onDisconnect,
}: {
    plotBoardUrl: string;
    onDisconnect?: () => void;
}) {
    const { t } = useTranslation('plot-panel');

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <span
                    role="button"
                    tabIndex={0}
                    aria-label="More actions"
                    onClick={(e) => e.stopPropagation()}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.stopPropagation();
                        }
                    }}
                    className="shrink-0 rounded-md p-1 text-ink-faint transition-colors hover:bg-neutral-bg hover:text-ink"
                >
                    <Ellipsis className="size-3.5" />
                </span>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" sideOffset={4}>
                <DropdownMenuItem
                    onClick={() => window.open(plotBoardUrl, '_blank')}
                >
                    <ExternalLink className="size-3.5" />
                    {t('viewOnPlotBoard')}
                </DropdownMenuItem>
                {onDisconnect && (
                    <DropdownMenuItem
                        onClick={onDisconnect}
                        className="text-delete focus:text-delete"
                    >
                        <Link2Off className="size-3.5" />
                        {t('disconnectFromChapter')}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
