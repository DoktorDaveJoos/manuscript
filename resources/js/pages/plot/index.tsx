import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useCallback, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Sidebar from '@/components/editor/Sidebar';
import ActColumn from '@/components/plot/ActColumn';
import ActDetailPanel from '@/components/plot/ActDetailPanel';
import BeatContextMenu from '@/components/plot/BeatContextMenu';
import BeatDetailPanel from '@/components/plot/BeatDetailPanel';
import PlotEmptyState from '@/components/plot/PlotEmptyState';
import PlotPointDetailPanel from '@/components/plot/PlotPointDetailPanel';
import PlotWizardModal from '@/components/plot/PlotWizardModal';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import type { PlotTemplate } from '@/lib/plot-templates';
import type {
    Act,
    Beat,
    BeatStatus,
    Book,
    PlotPoint,
    Storyline,
} from '@/types/models';

type PlotPageProps = {
    book: Book;
    storylines: Storyline[];
    acts: Act[];
    plotPoints: (PlotPoint & {
        beats?: (Beat & {
            chapters?: { id: number; title: string; reader_order: number }[];
        })[];
    })[];
};

export default function Plot({
    book,
    storylines,
    acts,
    plotPoints,
}: PlotPageProps) {
    const { t } = useTranslation('plot');
    const sidebarStorylines = useSidebarStorylines();
    const [selection, setSelection] = useState<{
        type: 'beat' | 'act' | 'plotPoint';
        id: number;
    } | null>(null);
    const [selectedTemplate, setSelectedTemplate] =
        useState<PlotTemplate | null>(null);
    const [contextMenu, setContextMenu] = useState<{
        beatId: number;
        position: { x: number; y: number };
    } | null>(null);
    const hasActs = acts.length > 0;
    const [titleOverrides, setTitleOverrides] = useState<
        Record<string, string>
    >({});

    const selectedBeatId = selection?.type === 'beat' ? selection.id : null;
    const selectedActId = selection?.type === 'act' ? selection.id : null;
    const selectedPlotPointId =
        selection?.type === 'plotPoint' ? selection.id : null;

    const handleTitleOverride = useCallback((key: string, title: string) => {
        setTitleOverrides((prev) => ({ ...prev, [key]: title }));
    }, []);

    // Track existing IDs so onSuccess can find the newly created item
    const beatIdsRef = useRef<Set<number>>(new Set());
    const plotPointIdsRef = useRef<Set<number>>(new Set());
    const actIdsRef = useRef<Set<number>>(new Set());

    // Keep refs in sync with props
    beatIdsRef.current = new Set(
        plotPoints.flatMap((pp) => (pp.beats ?? []).map((b) => b.id)),
    );
    plotPointIdsRef.current = new Set(plotPoints.map((pp) => pp.id));
    actIdsRef.current = new Set(acts.map((a) => a.id));

    // Build a beat lookup map once for O(1) access
    const beatMap = useMemo(() => {
        const map = new Map<number, Beat & { plot_point?: PlotPoint }>();
        for (const pp of plotPoints) {
            for (const beat of pp.beats ?? []) {
                map.set(beat.id, { ...beat, plot_point: pp });
            }
        }
        return map;
    }, [plotPoints]);

    const selectedBeat = selectedBeatId
        ? (beatMap.get(selectedBeatId) ?? null)
        : null;

    const selectedAct = selectedActId
        ? (acts.find((a) => a.id === selectedActId) ?? null)
        : null;

    const selectedPlotPoint = selectedPlotPointId
        ? (plotPoints.find((pp) => pp.id === selectedPlotPointId) ?? null)
        : null;

    const plotPointsByAct = useMemo(() => {
        const map = new Map<number, typeof plotPoints>();
        for (const pp of plotPoints) {
            if (pp.act_id !== null && pp.act_id !== undefined) {
                const existing = map.get(pp.act_id) ?? [];
                existing.push(pp);
                map.set(pp.act_id, existing);
            }
        }
        return map;
    }, [plotPoints]);

    const handleCreateBeat = useCallback(
        (plotPointId: number) => {
            const previousIds = new Set(beatIdsRef.current);
            router.post(
                `/books/${book.id}/plot-points/${plotPointId}/beats`,
                { title: t('beat.addBeat') },
                {
                    preserveScroll: true,
                    onSuccess: (page) => {
                        const props = page.props as unknown as PlotPageProps;
                        const allBeats = props.plotPoints.flatMap(
                            (pp) => pp.beats ?? [],
                        );
                        const newBeat = allBeats.find(
                            (b) => !previousIds.has(b.id),
                        );
                        if (newBeat) {
                            setSelection({ type: 'beat', id: newBeat.id });
                        }
                    },
                },
            );
        },
        [book.id, t],
    );

    const handleCreatePlotPoint = useCallback(
        (actId: number) => {
            const previousIds = new Set(plotPointIdsRef.current);
            router.post(
                `/books/${book.id}/plot-points`,
                {
                    title: t('page.newPlotPointTitle', 'New plot point'),
                    type: 'setup',
                    act_id: actId,
                },
                {
                    preserveScroll: true,
                    onSuccess: (page) => {
                        const props = page.props as unknown as PlotPageProps;
                        const newPlotPoint = props.plotPoints.find(
                            (pp) => !previousIds.has(pp.id),
                        );
                        if (newPlotPoint) {
                            setSelection({
                                type: 'plotPoint',
                                id: newPlotPoint.id,
                            });
                        }
                    },
                },
            );
        },
        [book.id, t],
    );

    const handleDeleteAct = useCallback((_actId: number) => {
        // TODO: implement act deletion
    }, []);

    const handleDeletePlotPoint = useCallback(
        (plotPointId: number) => {
            if (selectedPlotPointId === plotPointId) setSelection(null);
            router.delete(`/books/${book.id}/plot-points/${plotPointId}`, {
                preserveScroll: true,
            });
        },
        [book.id, selectedPlotPointId],
    );

    const handleBeatStatusChange = useCallback(
        (beatId: number, status: BeatStatus) => {
            router.patch(
                `/books/${book.id}/beats/${beatId}/status`,
                { status },
                { preserveScroll: true },
            );
        },
        [book.id],
    );

    const handleDeleteBeat = useCallback(
        (beatId: number) => {
            if (selectedBeatId === beatId) setSelection(null);
            router.delete(`/books/${book.id}/beats/${beatId}`, {
                preserveScroll: true,
            });
            setContextMenu(null);
        },
        [book.id, selectedBeatId],
    );

    const handleBeatContextMenu = useCallback(
        (beat: Beat, position: { x: number; y: number }) => {
            setContextMenu({ beatId: beat.id, position });
        },
        [],
    );

    const handleAddAct = useCallback(() => {
        const nextNumber =
            acts.length > 0 ? Math.max(...acts.map((a) => a.number)) + 1 : 1;
        const previousIds = new Set(actIdsRef.current);
        router.post(
            `/books/${book.id}/acts`,
            { number: nextNumber, title: `Act ${nextNumber}` },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const props = page.props as unknown as PlotPageProps;
                    const newAct = props.acts.find(
                        (a) => !previousIds.has(a.id),
                    );
                    if (newAct) {
                        setSelection({ type: 'act', id: newAct.id });
                    }
                },
            },
        );
    }, [acts, book.id]);

    const contextMenuBeat = contextMenu
        ? (beatMap.get(contextMenu.beatId) ?? null)
        : null;

    return (
        <>
            <Head title={`Plot — ${book.title}`} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar
                    book={book}
                    storylines={sidebarStorylines}
                    scenesVisible={false}
                    onScenesVisibleChange={() => {}}
                />

                <main className="flex min-w-0 flex-1 flex-col overflow-hidden">
                    {hasActs ? (
                        <>
                            {/* Header bar */}
                            <div className="flex h-12 items-center justify-between border-b border-border px-6">
                                <h1 className="text-[15px] font-semibold text-ink">
                                    {t('page.tabs.timeline', 'Plot')}
                                </h1>
                                <button
                                    type="button"
                                    onClick={handleAddAct}
                                    className="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-[12px] font-medium transition-opacity hover:opacity-80"
                                    style={{
                                        backgroundColor: '#F1EEEA',
                                        color: '#737373',
                                    }}
                                >
                                    <Plus size={14} />
                                    {t('act.addAct')}
                                </button>
                            </div>

                            {/* Act columns + detail panel */}
                            <div className="min-h-0 flex-1">
                                <div className="flex h-full overflow-x-auto">
                                    {acts.map((act, index) => (
                                        <ActColumn
                                            key={act.id}
                                            act={act}
                                            colorIndex={index}
                                            plotPoints={
                                                plotPointsByAct.get(act.id) ??
                                                []
                                            }
                                            selectedBeatId={selectedBeatId}
                                            selectedPlotPointId={
                                                selectedPlotPointId
                                            }
                                            titleOverrides={titleOverrides}
                                            isLast={index === acts.length - 1}
                                            onSelectBeat={(beat) =>
                                                setSelection({
                                                    type: 'beat',
                                                    id: beat.id,
                                                })
                                            }
                                            onSelectAct={(actId) =>
                                                setSelection({
                                                    type: 'act',
                                                    id: actId,
                                                })
                                            }
                                            onSelectPlotPoint={(pp) =>
                                                setSelection({
                                                    type: 'plotPoint',
                                                    id: pp.id,
                                                })
                                            }
                                            onCreateBeat={handleCreateBeat}
                                            onDeleteAct={handleDeleteAct}
                                            onCreatePlotPoint={
                                                handleCreatePlotPoint
                                            }
                                            onDeletePlotPoint={
                                                handleDeletePlotPoint
                                            }
                                            onBeatContextMenu={
                                                handleBeatContextMenu
                                            }
                                        />
                                    ))}
                                </div>

                                {selectedBeat && (
                                    <BeatDetailPanel
                                        key={selectedBeat.id}
                                        beat={selectedBeat}
                                        bookId={book.id}
                                        onClose={() => setSelection(null)}
                                        onDelete={handleDeleteBeat}
                                        onTitleChange={(title) =>
                                            handleTitleOverride(
                                                `beat-${selectedBeat.id}`,
                                                title,
                                            )
                                        }
                                    />
                                )}

                                {selectedAct && (
                                    <ActDetailPanel
                                        key={selectedAct.id}
                                        act={selectedAct}
                                        bookId={book.id}
                                        plotPoints={
                                            plotPointsByAct.get(
                                                selectedAct.id,
                                            ) ?? []
                                        }
                                        onClose={() => setSelection(null)}
                                        onDelete={handleDeleteAct}
                                        onTitleChange={(title) =>
                                            handleTitleOverride(
                                                `act-${selectedAct.id}`,
                                                title,
                                            )
                                        }
                                    />
                                )}

                                {selectedPlotPoint && (
                                    <PlotPointDetailPanel
                                        key={selectedPlotPoint.id}
                                        plotPoint={selectedPlotPoint}
                                        bookId={book.id}
                                        onClose={() => setSelection(null)}
                                        onDelete={handleDeletePlotPoint}
                                        onTitleChange={(title) =>
                                            handleTitleOverride(
                                                `plotpoint-${selectedPlotPoint.id}`,
                                                title,
                                            )
                                        }
                                    />
                                )}
                            </div>
                        </>
                    ) : (
                        <PlotEmptyState
                            onSelectTemplate={setSelectedTemplate}
                        />
                    )}
                </main>

                {contextMenu && contextMenuBeat && (
                    <BeatContextMenu
                        beat={contextMenuBeat}
                        bookId={book.id}
                        storylines={storylines}
                        position={contextMenu.position}
                        onClose={() => setContextMenu(null)}
                        onStatusChange={handleBeatStatusChange}
                        onDelete={handleDeleteBeat}
                    />
                )}

                {selectedTemplate && (
                    <PlotWizardModal
                        book={book}
                        template={selectedTemplate}
                        onClose={() => setSelectedTemplate(null)}
                    />
                )}
            </div>
        </>
    );
}
