import { Workflow } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { index as plotIndex } from '@/actions/App/Http/Controllers/PlotController';
import {
    connect as panelConnect,
    disconnect as panelDisconnect,
    index as panelIndex,
} from '@/actions/App/Http/Controllers/PlotPanelController';
import PanelHeader from '@/components/ui/PanelHeader';
import SectionLabel from '@/components/ui/SectionLabel';
import { jsonFetchHeaders } from '@/lib/utils';
import type { Book, Chapter } from '@/types/models';
import PlotPanelCard from './PlotPanelCard';
import type { PlotPanelBeat } from './PlotPanelCard';
import PlotPanelSearch from './PlotPanelSearch';

type PlotPointGroup = {
    plot_point: { id: number; title: string; sort_order: number };
    beats: PlotPanelBeat[];
};

type PanelData = {
    connected: PlotPointGroup[];
    session: PlotPointGroup[];
};

type FlatBeat = {
    beat: PlotPanelBeat;
    plotPointTitle: string;
};

const EMPTY: PanelData = { connected: [], session: [] };

function flatten(groups: PlotPointGroup[]): FlatBeat[] {
    return groups.flatMap((group) =>
        group.beats.map((beat) => ({
            beat,
            plotPointTitle: group.plot_point.title,
        })),
    );
}

export default function PlotPanel({
    book,
    chapter,
    onClose,
}: {
    book: Book;
    chapter: Chapter;
    onClose: () => void;
}) {
    const { t } = useTranslation('plot-panel');
    const [data, setData] = useState<PanelData>(EMPTY);
    const [query, setQuery] = useState('');
    const [expandedBeatIds, setExpandedBeatIds] = useState<Set<number>>(() =>
        loadSet(`manuscript:plot-expanded-beats:${chapter.id}`),
    );
    const [refreshTick, setRefreshTick] = useState(0);

    const plotBoardUrl = useMemo(() => plotIndex.url(book.id), [book.id]);

    useEffect(() => {
        const controller = new AbortController();
        const params: { chapter_id: number; q?: string } = {
            chapter_id: chapter.id,
        };
        if (query.length >= 2) params.q = query;

        fetch(panelIndex.url(book.id, { query: params }), {
            headers: jsonFetchHeaders(),
            signal: controller.signal,
        })
            .then((res) => (res.ok ? (res.json() as Promise<PanelData>) : null))
            .then((json) => {
                if (!json) return;
                setData({
                    connected: json.connected ?? [],
                    session: json.session ?? [],
                });
            })
            .catch(() => {
                // aborted or network error
            });

        return () => controller.abort();
    }, [book.id, chapter.id, query, refreshTick]);

    const refresh = useCallback(() => setRefreshTick((n) => n + 1), []);

    useEffect(() => {
        persistSet(
            `manuscript:plot-expanded-beats:${chapter.id}`,
            expandedBeatIds,
        );
    }, [expandedBeatIds, chapter.id]);

    const toggleBeat = useCallback((id: number) => {
        setExpandedBeatIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    }, []);

    const handleConnect = useCallback(
        async (beatId: number) => {
            await fetch(panelConnect.url(book.id), {
                method: 'POST',
                headers: jsonFetchHeaders(),
                body: JSON.stringify({
                    chapter_id: chapter.id,
                    beat_id: beatId,
                }),
            });
            refresh();
        },
        [book.id, chapter.id, refresh],
    );

    const handleDisconnect = useCallback(
        async (beatId: number) => {
            await fetch(panelDisconnect.url(book.id), {
                method: 'POST',
                headers: jsonFetchHeaders(),
                body: JSON.stringify({
                    chapter_id: chapter.id,
                    beat_id: beatId,
                }),
            });
            refresh();
        },
        [book.id, chapter.id, refresh],
    );

    const connectedBeats = useMemo(
        () => flatten(data.connected),
        [data.connected],
    );
    const sessionBeats = useMemo(() => flatten(data.session), [data.session]);
    const isEmpty = connectedBeats.length === 0 && sessionBeats.length === 0;

    const renderBeat = (
        { beat, plotPointTitle }: FlatBeat,
        isConnected: boolean,
    ) => (
        <PlotPanelCard
            key={`${isConnected ? 'c' : 's'}-${beat.id}`}
            beat={beat}
            plotPointTitle={plotPointTitle}
            isConnected={isConnected}
            isExpanded={expandedBeatIds.has(beat.id)}
            onToggleExpand={() => toggleBeat(beat.id)}
            onConnect={isConnected ? undefined : () => handleConnect(beat.id)}
            onDisconnect={
                isConnected ? () => handleDisconnect(beat.id) : undefined
            }
            plotBoardUrl={plotBoardUrl}
        />
    );

    return (
        <aside className="flex h-full shrink-0 flex-col border-l border-border-light bg-surface-sidebar">
            <PanelHeader
                title={t('headerTitle')}
                icon={<Workflow className="size-3.5 text-ink-muted" />}
                onClose={onClose}
            />

            <PlotPanelSearch onChange={setQuery} />

            <div className="flex flex-1 flex-col gap-3 overflow-y-auto p-4">
                {connectedBeats.length > 0 && (
                    <>
                        <SectionLabel variant="section">
                            {t('connectedToChapter')}
                        </SectionLabel>
                        {connectedBeats.map((b) => renderBeat(b, true))}
                    </>
                )}

                {sessionBeats.length > 0 && (
                    <>
                        {connectedBeats.length > 0 && (
                            <div className="flex items-center gap-2 py-1">
                                <div className="h-px flex-1 bg-border-light" />
                                <SectionLabel variant="section">
                                    {t('recentlyViewed')}
                                </SectionLabel>
                                <div className="h-px flex-1 bg-border-light" />
                            </div>
                        )}
                        {sessionBeats.map((b) => renderBeat(b, false))}
                    </>
                )}

                {isEmpty && (
                    <p className="py-8 text-center text-[13px] text-ink-muted">
                        {t('emptyState')}
                    </p>
                )}
            </div>
        </aside>
    );
}

function loadSet(key: string): Set<number> {
    try {
        const stored = localStorage.getItem(key);
        return stored ? new Set(JSON.parse(stored) as number[]) : new Set();
    } catch {
        return new Set();
    }
}

function persistSet(key: string, set: Set<number>) {
    try {
        if (set.size > 0) {
            localStorage.setItem(key, JSON.stringify([...set]));
        } else {
            localStorage.removeItem(key);
        }
    } catch {
        /* no-op */
    }
}
