import { ChevronDown, ChevronRight, Workflow } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { index as plotIndex } from '@/actions/App/Http/Controllers/PlotController';
import {
    connect as panelConnect,
    disconnect as panelDisconnect,
    index as panelIndex,
    updateBeat as panelUpdateBeat,
} from '@/actions/App/Http/Controllers/PlotPanelController';
import PanelHeader from '@/components/ui/PanelHeader';
import SectionLabel from '@/components/ui/SectionLabel';
import { cn, jsonFetchHeaders } from '@/lib/utils';
import type { BeatStatus, Book, Chapter } from '@/types/models';
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

const EMPTY: PanelData = { connected: [], session: [] };

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
    const [collapsedGroupIds, setCollapsedGroupIds] = useState<Set<number>>(
        () => loadSet(`manuscript:plot-collapsed-groups:${chapter.id}`),
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
            `manuscript:plot-collapsed-groups:${chapter.id}`,
            collapsedGroupIds,
        );
    }, [collapsedGroupIds, chapter.id]);

    const toggleGroup = useCallback((id: number) => {
        setCollapsedGroupIds((prev) => {
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

    const handleUpdateBeat = useCallback(
        async (
            beatId: number,
            patch: {
                title?: string;
                description?: string | null;
                status?: BeatStatus;
            },
        ) => {
            await fetch(panelUpdateBeat.url({ book: book.id, beat: beatId }), {
                method: 'PATCH',
                headers: jsonFetchHeaders(),
                body: JSON.stringify(patch),
            });
            refresh();
        },
        [book.id, refresh],
    );

    const isEmpty = data.connected.length === 0 && data.session.length === 0;

    return (
        <aside className="flex h-full shrink-0 flex-col border-l border-border-light bg-surface-sidebar">
            <PanelHeader
                title={t('headerTitle')}
                icon={<Workflow size={14} className="text-ink-muted" />}
                onClose={onClose}
            />

            <PlotPanelSearch onChange={setQuery} />

            <div className="flex flex-1 flex-col gap-3 overflow-y-auto p-4">
                {data.connected.length > 0 && (
                    <>
                        <SectionLabel variant="section">
                            {t('connectedToChapter')}
                        </SectionLabel>
                        {data.connected.map((group) => (
                            <PlotGroup
                                key={`c-${group.plot_point.id}`}
                                group={group}
                                isCollapsed={collapsedGroupIds.has(
                                    group.plot_point.id,
                                )}
                                onToggleGroup={() =>
                                    toggleGroup(group.plot_point.id)
                                }
                                renderBeat={(beat) => (
                                    <PlotPanelCard
                                        key={beat.id}
                                        beat={beat}
                                        isConnected
                                        onDisconnect={() =>
                                            handleDisconnect(beat.id)
                                        }
                                        onUpdate={(patch) =>
                                            handleUpdateBeat(beat.id, patch)
                                        }
                                        plotBoardUrl={plotBoardUrl}
                                    />
                                )}
                            />
                        ))}
                    </>
                )}

                {data.session.length > 0 && (
                    <>
                        {data.connected.length > 0 && (
                            <div className="flex items-center gap-2 py-1">
                                <div className="h-px flex-1 bg-border-light" />
                                <SectionLabel variant="section">
                                    {t('recentlyViewed')}
                                </SectionLabel>
                                <div className="h-px flex-1 bg-border-light" />
                            </div>
                        )}
                        {data.session.map((group) => (
                            <PlotGroup
                                key={`s-${group.plot_point.id}`}
                                group={group}
                                isCollapsed={collapsedGroupIds.has(
                                    group.plot_point.id,
                                )}
                                onToggleGroup={() =>
                                    toggleGroup(group.plot_point.id)
                                }
                                renderBeat={(beat) => (
                                    <PlotPanelCard
                                        key={beat.id}
                                        beat={beat}
                                        isConnected={false}
                                        onConnect={() => handleConnect(beat.id)}
                                        plotBoardUrl={plotBoardUrl}
                                    />
                                )}
                            />
                        ))}
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

function PlotGroup({
    group,
    isCollapsed,
    onToggleGroup,
    renderBeat,
}: {
    group: PlotPointGroup;
    isCollapsed: boolean;
    onToggleGroup: () => void;
    renderBeat: (beat: PlotPanelBeat) => React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-2">
            <button
                type="button"
                onClick={onToggleGroup}
                className="flex items-center gap-1.5 text-left"
            >
                <span className="text-ink-faint">
                    {isCollapsed ? (
                        <ChevronRight size={12} />
                    ) : (
                        <ChevronDown size={12} />
                    )}
                </span>
                <span
                    className={cn(
                        'truncate text-[12px] font-medium text-ink',
                        isCollapsed && 'text-ink-muted',
                    )}
                >
                    {group.plot_point.title}
                </span>
            </button>
            {!isCollapsed && (
                <div className="flex flex-col gap-1.5 pl-3">
                    {group.beats.map((beat) => renderBeat(beat))}
                </div>
            )}
        </div>
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
