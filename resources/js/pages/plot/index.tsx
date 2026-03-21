import {
    DndContext,
    DragOverlay,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type {
    DragEndEvent,
    DragOverEvent,
    DragStartEvent,
} from '@dnd-kit/core';
import { Head, router } from '@inertiajs/react';
import { GripVertical, Plus } from 'lucide-react';
import { useCallback, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    move as beatMove,
    reorder as beatReorder,
} from '@/actions/App/Http/Controllers/BeatController';
import { reorder as plotPointReorder } from '@/actions/App/Http/Controllers/PlotPointController';
import Sidebar from '@/components/editor/Sidebar';
import ActColumn from '@/components/plot/ActColumn';
import ActContextMenu from '@/components/plot/ActContextMenu';
import ActDetailPanel from '@/components/plot/ActDetailPanel';
import BeatContextMenu from '@/components/plot/BeatContextMenu';
import BeatDetailPanel from '@/components/plot/BeatDetailPanel';
import DeleteActDialog from '@/components/plot/DeleteActDialog';
import PlotEmptyState from '@/components/plot/PlotEmptyState';
import PlotPointContextMenu from '@/components/plot/PlotPointContextMenu';
import PlotPointDetailPanel from '@/components/plot/PlotPointDetailPanel';
import PlotWizardModal from '@/components/plot/PlotWizardModal';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import type { PlotTemplate } from '@/lib/plot-templates';
import type {
    Act,
    Beat,
    BeatStatus,
    Book,
    Character,
    ChapterSummary,
    PlotPoint,
    Storyline,
} from '@/types/models';

type PlotPageProps = {
    book: Book;
    storylines: Storyline[];
    acts: Act[];
    plotPoints: (PlotPoint & {
        beats?: (Beat & {
            chapters?: {
                id: number;
                title: string;
                storyline_id: number;
                reader_order: number;
            }[];
        })[];
    })[];
    chapters: ChapterSummary[];
    characters: Character[];
};

const POINTER_SENSOR_OPTIONS = { activationConstraint: { distance: 5 } };

type DragItem =
    | { type: 'beat'; beat: Beat }
    | { type: 'plotpoint'; plotPoint: PlotPoint };

export default function Plot({
    book,
    storylines,
    acts,
    plotPoints,
    chapters,
    characters,
}: PlotPageProps) {
    const { t } = useTranslation('plot');
    const sidebarStorylines = useSidebarStorylines();

    // Unified selection state (from #20)
    const [selection, setSelection] = useState<{
        type: 'beat' | 'act' | 'plotPoint';
        id: number;
    } | null>(null);

    const [selectedTemplate, setSelectedTemplate] =
        useState<PlotTemplate | null>(null);
    const [actToDelete, setActToDelete] = useState<Act | null>(null);
    const [contextMenu, setContextMenu] = useState<{
        beatId: number;
        position: { x: number; y: number };
    } | null>(null);
    const [actContextMenu, setActContextMenu] = useState<{
        act: Act;
        position: { x: number; y: number };
    } | null>(null);
    const [plotPointContextMenu, setPlotPointContextMenu] = useState<{
        plotPoint: PlotPoint;
        position: { x: number; y: number };
    } | null>(null);
    const [activeItem, setActiveItem] = useState<DragItem | null>(null);
    const hasActs = acts.length > 0;
    const [titleOverrides, setTitleOverrides] = useState<
        Record<string, string>
    >({});

    // Derived selection IDs
    const selectedBeatId = selection?.type === 'beat' ? selection.id : null;
    const selectedActId = selection?.type === 'act' ? selection.id : null;
    const selectedPlotPointId =
        selection?.type === 'plotPoint' ? selection.id : null;

    const clearSelection = useCallback(() => {
        clearSelection();
        setTitleOverrides({});
    }, []);

    const handleTitleOverride = useCallback((key: string, title: string) => {
        setTitleOverrides((prev) => ({ ...prev, [key]: title }));
    }, []);

    const sensors = useSensors(
        useSensor(PointerSensor, POINTER_SENSOR_OPTIONS),
    );

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

    const plotPointMap = useMemo(() => {
        const map = new Map<number, (typeof plotPoints)[number]>();
        for (const pp of plotPoints) {
            map.set(pp.id, pp);
        }
        return map;
    }, [plotPoints]);

    const handleCreateBeat = useCallback(
        (plotPointId: number) => {
            const previousIds = new Set(
                plotPoints.flatMap((pp) => (pp.beats ?? []).map((b) => b.id)),
            );
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
            const previousIds = new Set(plotPoints.map((pp) => pp.id));
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

    const handleDeleteAct = useCallback(
        (actId: number) => {
            setActContextMenu(null);
            if (selectedActId === actId) clearSelection();
            const act = acts.find((a) => a.id === actId);
            if (act) {
                setActToDelete(act);
            }
        },
        [acts, selectedActId],
    );

    const handleDeletePlotPoint = useCallback(
        (plotPointId: number) => {
            if (selectedPlotPointId === plotPointId) clearSelection();
            router.delete(`/books/${book.id}/plot-points/${plotPointId}`, {
                preserveScroll: true,
            });
            setPlotPointContextMenu(null);
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
            if (selectedBeatId === beatId) clearSelection();
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

    const handleActContextMenu = useCallback(
        (act: Act, position: { x: number; y: number }) => {
            setActContextMenu({ act, position });
        },
        [],
    );

    const handlePlotPointContextMenu = useCallback(
        (plotPoint: PlotPoint, position: { x: number; y: number }) => {
            setPlotPointContextMenu({ plotPoint, position });
        },
        [],
    );

    const handleAddAct = useCallback(() => {
        const nextNumber =
            acts.length > 0 ? Math.max(...acts.map((a) => a.number)) + 1 : 1;
        const previousIds = new Set(acts.map((a) => a.id));
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

    // --- Drag-and-drop handlers ---

    const handleDragStart = useCallback((event: DragStartEvent) => {
        const data = event.active.data.current;
        if (data?.type === 'beat') {
            setActiveItem({ type: 'beat', beat: data.beat });
        } else if (data?.type === 'plotpoint') {
            setActiveItem({ type: 'plotpoint', plotPoint: data.plotPoint });
        }
    }, []);

    /** Track which container a dragged item is currently over */
    const overContainerRef = useRef<{
        plotPointId?: number;
        actId?: number;
    }>({});

    const handleDragOver = useCallback((event: DragOverEvent) => {
        const overData = event.over?.data.current;
        if (!overData) {
            overContainerRef.current = {};
            return;
        }

        if (overData.type === 'beat') {
            overContainerRef.current = {
                plotPointId: overData.beat.plot_point_id,
            };
        } else if (overData.type === 'plotpoint-drop') {
            overContainerRef.current = {
                plotPointId: overData.plotPointId,
            };
        } else if (overData.type === 'plotpoint') {
            overContainerRef.current = {
                actId: overData.plotPoint.act_id ?? undefined,
            };
        } else if (overData.type === 'act-drop') {
            overContainerRef.current = {
                actId: overData.actId,
            };
        }
    }, []);

    const handleDragEnd = useCallback(
        (event: DragEndEvent) => {
            setActiveItem(null);
            overContainerRef.current = {};

            const { active, over } = event;
            if (!over) return;

            const activeData = active.data.current;
            const overData = over.data.current;

            if (!activeData || !overData) return;

            // --- Beat drag ---
            if (activeData.type === 'beat') {
                const draggedBeat: Beat = activeData.beat;
                const sourcePlotPointId = draggedBeat.plot_point_id;

                let targetPlotPointId: number;
                let targetIndex: number;

                if (overData.type === 'beat') {
                    const overBeat: Beat = overData.beat;
                    targetPlotPointId = overBeat.plot_point_id;

                    const targetPP = plotPointMap.get(targetPlotPointId);
                    const targetBeats = targetPP?.beats ?? [];
                    targetIndex = targetBeats.findIndex(
                        (b) => b.id === overBeat.id,
                    );
                    if (targetIndex === -1) targetIndex = targetBeats.length;
                } else if (overData.type === 'plotpoint-drop') {
                    targetPlotPointId = overData.plotPointId;
                    const targetPP = plotPointMap.get(targetPlotPointId);
                    targetIndex = (targetPP?.beats ?? []).length;
                } else {
                    return;
                }

                if (
                    sourcePlotPointId === targetPlotPointId &&
                    active.id === over.id
                ) {
                    return;
                }

                if (sourcePlotPointId === targetPlotPointId) {
                    // Reorder within same plot point
                    const pp = plotPointMap.get(sourcePlotPointId);
                    const beats = [...(pp?.beats ?? [])];
                    const oldIndex = beats.findIndex(
                        (b) => b.id === draggedBeat.id,
                    );
                    if (oldIndex === -1) return;

                    beats.splice(oldIndex, 1);
                    const newIndex = beats.findIndex(
                        (b) => `beat-${b.id}` === (over.id as string),
                    );
                    const insertAt = newIndex === -1 ? beats.length : newIndex;
                    beats.splice(insertAt, 0, draggedBeat);

                    const items = beats.map((b, i) => ({
                        id: b.id,
                        sort_order: i,
                    }));

                    router.post(
                        beatReorder.url({
                            book: book.id,
                            plotPoint: sourcePlotPointId,
                        }),
                        { items },
                        { preserveScroll: true },
                    );
                } else {
                    // Move to different plot point
                    router.patch(
                        beatMove.url({
                            book: book.id,
                            beat: draggedBeat.id,
                        }),
                        {
                            plot_point_id: targetPlotPointId,
                            sort_order: targetIndex,
                        },
                        { preserveScroll: true },
                    );
                }
            }

            // --- Plot point drag ---
            if (activeData.type === 'plotpoint') {
                const draggedPP: PlotPoint = activeData.plotPoint;
                const sourceActId = draggedPP.act_id;

                let targetActId: number | null;
                let targetIndex: number;

                if (overData.type === 'plotpoint') {
                    const overPP: PlotPoint = overData.plotPoint;
                    targetActId = overPP.act_id;
                    const actPPs = plotPointsByAct.get(targetActId ?? -1) ?? [];
                    targetIndex = actPPs.findIndex((pp) => pp.id === overPP.id);
                    if (targetIndex === -1) targetIndex = actPPs.length;
                } else if (overData.type === 'act-drop') {
                    targetActId = overData.actId;
                    const actPPs = plotPointsByAct.get(targetActId ?? -1) ?? [];
                    targetIndex = actPPs.length;
                } else {
                    return;
                }

                if (sourceActId === targetActId && active.id === over.id) {
                    return;
                }

                // Build reorder items for all affected plot points
                const allItems: {
                    id: number;
                    sort_order: number;
                    act_id: number | null;
                }[] = [];

                if (sourceActId === targetActId) {
                    // Reorder within same act
                    const actPPs = [
                        ...(plotPointsByAct.get(sourceActId ?? -1) ?? []),
                    ];
                    const oldIdx = actPPs.findIndex(
                        (pp) => pp.id === draggedPP.id,
                    );
                    if (oldIdx === -1) return;

                    actPPs.splice(oldIdx, 1);
                    const newIdx = actPPs.findIndex(
                        (pp) => `plotpoint-${pp.id}` === (over.id as string),
                    );
                    const insertAt = newIdx === -1 ? actPPs.length : newIdx;
                    actPPs.splice(insertAt, 0, draggedPP);

                    actPPs.forEach((pp, i) => {
                        allItems.push({
                            id: pp.id,
                            sort_order: i,
                            act_id: sourceActId,
                        });
                    });
                } else {
                    // Move to different act
                    // Remove from source
                    const sourcePPs = [
                        ...(plotPointsByAct.get(sourceActId ?? -1) ?? []),
                    ].filter((pp) => pp.id !== draggedPP.id);
                    sourcePPs.forEach((pp, i) => {
                        allItems.push({
                            id: pp.id,
                            sort_order: i,
                            act_id: sourceActId,
                        });
                    });

                    // Add to target
                    const targetPPs = [
                        ...(plotPointsByAct.get(targetActId ?? -1) ?? []),
                    ];
                    const overIdx = targetPPs.findIndex(
                        (pp) => `plotpoint-${pp.id}` === (over.id as string),
                    );
                    const insertAt =
                        overIdx === -1 ? targetPPs.length : overIdx;
                    targetPPs.splice(insertAt, 0, draggedPP);

                    targetPPs.forEach((pp, i) => {
                        allItems.push({
                            id: pp.id,
                            sort_order: i,
                            act_id: targetActId,
                        });
                    });
                }

                router.post(
                    plotPointReorder.url({ book: book.id }),
                    { items: allItems },
                    { preserveScroll: true },
                );
            }
        },
        [book.id, plotPointMap, plotPointsByAct],
    );

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
                            <div className="flex min-h-0 flex-1">
                                <DndContext
                                    sensors={sensors}
                                    collisionDetection={closestCenter}
                                    onDragStart={handleDragStart}
                                    onDragOver={handleDragOver}
                                    onDragEnd={handleDragEnd}
                                >
                                    <div className="flex flex-1 overflow-x-auto">
                                        {acts.map((act, index) => (
                                            <ActColumn
                                                key={act.id}
                                                act={act}
                                                colorIndex={index}
                                                plotPoints={
                                                    plotPointsByAct.get(
                                                        act.id,
                                                    ) ?? []
                                                }
                                                selectedBeatId={selectedBeatId}
                                                selectedPlotPointId={
                                                    selectedPlotPointId
                                                }
                                                titleOverrides={titleOverrides}
                                                isLast={
                                                    index === acts.length - 1
                                                }
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
                                                onCreatePlotPoint={
                                                    handleCreatePlotPoint
                                                }
                                                onBeatContextMenu={
                                                    handleBeatContextMenu
                                                }
                                                onActContextMenu={
                                                    handleActContextMenu
                                                }
                                                onPlotPointContextMenu={
                                                    handlePlotPointContextMenu
                                                }
                                            />
                                        ))}
                                    </div>

                                    <DragOverlay>
                                        {activeItem?.type === 'beat' && (
                                            <div className="flex items-center gap-2 rounded bg-white px-2 py-1 opacity-95 shadow-[0_4px_16px_#0000001F,0_0_0_1px_#0000000A] dark:bg-surface-card">
                                                <span className="flex shrink-0 items-center text-ink-faint">
                                                    <GripVertical className="h-3 w-3" />
                                                </span>
                                                <span className="min-w-0 flex-1 truncate text-[12px] text-ink-soft">
                                                    {activeItem.beat.title}
                                                </span>
                                            </div>
                                        )}
                                        {activeItem?.type === 'plotpoint' && (
                                            <div
                                                className="flex items-center gap-2 rounded-lg border px-3 py-2 opacity-95 shadow-[0_4px_16px_#0000001F,0_0_0_1px_#0000000A]"
                                                style={{
                                                    backgroundColor: '#FCFAF7',
                                                    borderColor: '#E4E2DD',
                                                }}
                                            >
                                                <span className="flex shrink-0 items-center text-ink-faint">
                                                    <GripVertical className="h-3.5 w-3.5" />
                                                </span>
                                                <span className="min-w-0 flex-1 truncate text-[13px] font-semibold text-ink">
                                                    {activeItem.plotPoint.title}
                                                </span>
                                            </div>
                                        )}
                                    </DragOverlay>
                                </DndContext>

                                {selectedBeat && (
                                    <BeatDetailPanel
                                        key={selectedBeat.id}
                                        beat={selectedBeat}
                                        bookId={book.id}
                                        chapters={chapters}
                                        storylines={storylines}
                                        onClose={() => clearSelection()}
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
                                        onClose={() => clearSelection()}
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
                                        characters={characters}
                                        onClose={() => clearSelection()}
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

                {actContextMenu && (
                    <ActContextMenu
                        act={actContextMenu.act}
                        position={actContextMenu.position}
                        onClose={() => setActContextMenu(null)}
                        onDelete={handleDeleteAct}
                    />
                )}

                {plotPointContextMenu && (
                    <PlotPointContextMenu
                        plotPoint={plotPointContextMenu.plotPoint}
                        position={plotPointContextMenu.position}
                        onClose={() => setPlotPointContextMenu(null)}
                        onDelete={handleDeletePlotPoint}
                    />
                )}

                {selectedTemplate && (
                    <PlotWizardModal
                        book={book}
                        template={selectedTemplate}
                        onClose={() => setSelectedTemplate(null)}
                    />
                )}

                {actToDelete && (
                    <DeleteActDialog
                        bookId={book.id}
                        act={actToDelete}
                        onClose={() => {
                            const deletedActId = actToDelete.id;
                            const beatsInAct = (
                                plotPointsByAct.get(deletedActId) ?? []
                            ).flatMap((pp) => pp.beats ?? []);
                            if (
                                selectedBeatId &&
                                beatsInAct.some((b) => b.id === selectedBeatId)
                            ) {
                                clearSelection();
                            }
                            setActToDelete(null);
                        }}
                    />
                )}
            </div>
        </>
    );
}
