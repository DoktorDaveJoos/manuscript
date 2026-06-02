import { ChevronDown, ChevronUp, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import DescriptionBlock from '@/components/wiki/DescriptionBlock';
import { cn } from '@/lib/utils';
import type { BeatStatus } from '@/types/models';
import PanelCardMenu from './PanelCardMenu';

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
        <div className="flex flex-col rounded-lg bg-neutral-bg/50">
            <button
                type="button"
                onClick={onToggleExpand}
                className="flex w-full items-center gap-2.5 p-2.5 text-left"
            >
                <div className="flex min-w-0 flex-1 flex-col gap-1">
                    <p className="truncate text-[13px] font-medium text-ink">
                        {beat.title}
                    </p>
                    <p className="truncate text-[11px] text-ink-faint">
                        {plotPointTitle}
                    </p>
                    <span
                        className={cn(
                            'mt-0.5 self-start rounded-full px-2 py-0.5 text-[11px] font-medium tracking-wide uppercase',
                            STATUS_TONE[beat.status],
                        )}
                    >
                        {t(`status.${beat.status}`)}
                    </span>
                </div>
                {isConnected && (
                    <PanelCardMenu
                        openUrl={plotBoardUrl}
                        openLabel={t('viewOnPlotBoard')}
                        disconnectLabel={t('disconnectFromChapter')}
                        onDisconnect={onDisconnect}
                    />
                )}
                {isExpanded ? (
                    <ChevronUp size={14} className="shrink-0 text-ink-faint" />
                ) : (
                    <ChevronDown
                        size={14}
                        className="shrink-0 text-ink-faint"
                    />
                )}
            </button>

            {isExpanded && (
                <div className="flex flex-col gap-2.5 px-3 pb-3">
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
