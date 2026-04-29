import {
    DndContext,
    DragOverlay,
    PointerSensor,
    closestCorners,
    pointerWithin,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type {
    CollisionDetection,
    DragEndEvent,
    DragStartEvent,
} from '@dnd-kit/core';
import { arrayMove } from '@dnd-kit/sortable';
import { Head, router } from '@inertiajs/react';
import {
    GripVertical,
    LayoutGrid,
    MessageSquare,
    Plus,
    Undo2,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
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
import CoachPanel from '@/components/plot/CoachPanel';
import type { CoachPanelHandle } from '@/components/plot/CoachPanel';
import DeleteActDialog from '@/components/plot/DeleteActDialog';
import PlotEmptyState from '@/components/plot/PlotEmptyState';
import PlotPointContextMenu from '@/components/plot/PlotPointContextMenu';
import PlotPointDetailPanel from '@/components/plot/PlotPointDetailPanel';
import PlotWizardModal from '@/components/plot/PlotWizardModal';
import Button from '@/components/ui/Button';
import { useAiFeatures } from '@/hooks/useAiFeatures';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import type { PlotTemplate } from '@/lib/plot-templates';
import { cn } from '@/lib/utils';
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
    active_coach_session: number | null;
};

type PlotMode = 'coach' | 'board';

const PLOT_MODE_STORAGE_KEY = (bookId: number) => `plot-coach-mode-${bookId}`;

const POINTER_SENSOR_OPTIONS = { activationConstraint: { distance: 5 } };

type DragItem =
    | { type: 'beat'; beat: Beat }
    | { type: 'plotpoint'; plotPoint: PlotPoint };

// Filters droppable containers by what's compatible with the active item's
// type. PlotPointSection registers two droppables on the same DOM node — a
// `plotpoint` sortable and a `plotpoint-drop` zone for beats — so without
// filtering, collision detection randomly picks one and drops silently fail.
// Uses pointerWithin first so drops in container empty space (e.g. below the
// last card) reliably hit `act-drop` / `plotpoint-drop`; falls back to
// closestCorners when the pointer is outside any droppable. When the pointer
// is over both an item and its container, the item wins.
const collisionDetectionStrategy: CollisionDetection = (args) => {
    const activeType = args.active?.data.current?.type as string | undefined;
    if (!activeType) return closestCorners(args);

    const droppableContainers = args.droppableContainers.filter((container) => {
        const type = container.data.current?.type as string | undefined;
        if (activeType === 'plotpoint') {
            return type === 'plotpoint' || type === 'act-drop';
        }
        if (activeType === 'beat') {
            return type === 'beat' || type === 'plotpoint-drop';
        }
        return true;
    });

    const pointerCollisions = pointerWithin({ ...args, droppableContainers });
    if (pointerCollisions.length > 0) {
        const containerById = new Map(
            droppableContainers.map((c) => [c.id, c] as const),
        );
        const itemMatches = pointerCollisions.filter((collision) => {
            const type = containerById.get(collision.id)?.data.current?.type as
                | string
                | undefined;
            return type === 'plotpoint' || type === 'beat';
        });
        return itemMatches.length > 0 ? itemMatches : pointerCollisions;
    }

    return closestCorners({ ...args, droppableContainers });
};

type PlotPointWithBeats = PlotPageProps['plotPoints'][number];

function sortPlotPoints(items: PlotPointWithBeats[]): PlotPointWithBeats[] {
    return [...items].sort((a, b) => {
        const aAct = a.act_id ?? Number.MAX_SAFE_INTEGER;
        const bAct = b.act_id ?? Number.MAX_SAFE_INTEGER;
        if (aAct !== bAct) return aAct - bAct;
        return (a.sort_order ?? 0) - (b.sort_order ?? 0);
    });
}

function applyPlotPointReorder(
    plotPoints: PlotPointWithBeats[],
    items: { id: number; sort_order: number; act_id: number | null }[],
): PlotPointWithBeats[] {
    const updates = new Map(items.map((it) => [it.id, it]));
    return sortPlotPoints(
        plotPoints.map((pp) => {
            const update = updates.get(pp.id);
            if (!update) return pp;
            return {
                ...pp,
                sort_order: update.sort_order,
                act_id: update.act_id,
            };
        }),
    );
}

function applyBeatReorder(
    plotPoints: PlotPointWithBeats[],
    plotPointId: number,
    beats: NonNullable<PlotPointWithBeats['beats']>,
): PlotPointWithBeats[] {
    return plotPoints.map((pp) =>
        pp.id === plotPointId ? { ...pp, beats } : pp,
    );
}

function applyBeatMove(
    plotPoints: PlotPointWithBeats[],
    beatId: number,
    sourcePlotPointId: number,
    targetPlotPointId: number,
    targetIndex: number,
): PlotPointWithBeats[] {
    const sourcePP = plotPoints.find((pp) => pp.id === sourcePlotPointId);
    const beat = sourcePP?.beats?.find((b) => b.id === beatId);
    if (!beat) return plotPoints;

    return plotPoints.map((pp) => {
        if (pp.id === sourcePlotPointId) {
            return {
                ...pp,
                beats: (pp.beats ?? []).filter((b) => b.id !== beatId),
            };
        }
        if (pp.id === targetPlotPointId) {
            const beats = [...(pp.beats ?? [])];
            beats.splice(targetIndex, 0, {
                ...beat,
                plot_point_id: targetPlotPointId,
            });
            return { ...pp, beats };
        }
        return pp;
    });
}

export default function Plot({
    book,
    storylines,
    acts,
    plotPoints: serverPlotPoints,
    chapters,
    characters,
    active_coach_session: activeCoachSessionId,
}: PlotPageProps) {
    const [plotPoints, setPlotPoints] = useState(serverPlotPoints);

    useEffect(() => {
        setPlotPoints(serverPlotPoints);
    }, [serverPlotPoints]);

    const { t } = useTranslation('plot');
    const { t: tCoach } = useTranslation('plot-coach');
    const sidebarStorylines = useSidebarStorylines();
    const { configured: aiConfigured } = useAiFeatures();

    // Ref for dispatching conversational signals into the coach chat
    // ("UNDO:last" from the top-bar Undo button).
    const coachPanelRef = useRef<CoachPanelHandle>(null);

    // Tracks the active coach session id. Starts from the page-prop and is
    // updated locally when the chat surface creates a new session so the
    // hydrate/stream wiring stays correct without a page reload.
    const [coachSessionId, setCoachSessionId] = useState<number | null>(
        activeCoachSessionId ?? null,
    );

    useEffect(() => {
        setCoachSessionId(activeCoachSessionId ?? null);
    }, [activeCoachSessionId]);

    // Plot mode — Coach chat or Board view. Persisted per-book in localStorage.
    // Default: Coach if an active unfinished session exists, Board otherwise.
    const [mode, setMode] = useState<PlotMode>(() => {
        const hasSession = activeCoachSessionId !== null;
        if (typeof window === 'undefined') {
            return hasSession ? 'coach' : 'board';
        }
        const stored = window.localStorage.getItem(
            PLOT_MODE_STORAGE_KEY(book.id),
        );
        if (stored === 'coach' || stored === 'board') {
            return stored;
        }
        return hasSession ? 'coach' : 'board';
    });

    useEffect(() => {
        if (typeof window === 'undefined') return;
        window.localStorage.setItem(PLOT_MODE_STORAGE_KEY(book.id), mode);
    }, [book.id, mode]);

    // Cmd+\ / Ctrl+\ toggles between Coach and Board.
    useEffect(() => {
        const handler = (event: KeyboardEvent) => {
            if (event.key !== '\\') return;
            if (!(event.metaKey || event.ctrlKey)) return;
            // Ignore when focus is in an editable surface (contenteditable,
            // inputs, textareas) so the shortcut never swallows user typing.
            const target = event.target as HTMLElement | null;
            const tag = target?.tagName;
            if (
                target?.isContentEditable ||
                tag === 'INPUT' ||
                tag === 'TEXTAREA'
            ) {
                return;
            }
            event.preventDefault();
            setMode((prev) => (prev === 'coach' ? 'board' : 'coach'));
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, []);

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
        setSelection(null);
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
        [book.id, t, plotPoints],
    );

    const handleCreatePlotPoint = useCallback(
        (actId: number) => {
            const previousIds = new Set(plotPoints.map((pp) => pp.id));
            router.post(
                `/books/${book.id}/plot-points`,
                {
                    title: t('page.newPlotPointTitle', 'New plot point'),
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
        [book.id, t, plotPoints],
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
        [acts, selectedActId, clearSelection],
    );

    const handleDeletePlotPoint = useCallback(
        (plotPointId: number) => {
            if (selectedPlotPointId === plotPointId) clearSelection();
            router.delete(`/books/${book.id}/plot-points/${plotPointId}`, {
                preserveScroll: true,
            });
            setPlotPointContextMenu(null);
        },
        [book.id, selectedPlotPointId, clearSelection],
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
        [book.id, selectedBeatId, clearSelection],
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

    const handleDragEnd = useCallback(
        (event: DragEndEvent) => {
            setActiveItem(null);

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
                    const pp = plotPointMap.get(sourcePlotPointId);
                    const beats = pp?.beats ?? [];
                    const oldIndex = beats.findIndex(
                        (b) => b.id === draggedBeat.id,
                    );
                    if (oldIndex === -1) return;

                    const overIndex = beats.findIndex(
                        (b) => `beat-${b.id}` === (over.id as string),
                    );
                    const reordered =
                        overIndex === -1
                            ? [
                                  ...beats.filter(
                                      (b) => b.id !== draggedBeat.id,
                                  ),
                                  draggedBeat,
                              ]
                            : arrayMove(beats, oldIndex, overIndex);

                    const items = reordered.map((b, i) => ({
                        id: b.id,
                        sort_order: i,
                    }));

                    setPlotPoints((prev) =>
                        applyBeatReorder(prev, sourcePlotPointId, reordered),
                    );
                    router.post(
                        beatReorder.url({
                            book: book.id,
                            plotPoint: sourcePlotPointId,
                        }),
                        { items },
                        { preserveScroll: true },
                    );
                } else {
                    setPlotPoints((prev) =>
                        applyBeatMove(
                            prev,
                            draggedBeat.id,
                            sourcePlotPointId,
                            targetPlotPointId,
                            targetIndex,
                        ),
                    );
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
                    const actPPs = plotPointsByAct.get(sourceActId ?? -1) ?? [];
                    const oldIdx = actPPs.findIndex(
                        (pp) => pp.id === draggedPP.id,
                    );
                    if (oldIdx === -1) return;

                    const overIdx = actPPs.findIndex(
                        (pp) => `plotpoint-${pp.id}` === (over.id as string),
                    );
                    const reordered =
                        overIdx === -1
                            ? [
                                  ...actPPs.filter(
                                      (pp) => pp.id !== draggedPP.id,
                                  ),
                                  draggedPP,
                              ]
                            : arrayMove(actPPs, oldIdx, overIdx);

                    reordered.forEach((pp, i) => {
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

                setPlotPoints((prev) => applyPlotPointReorder(prev, allItems));
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
                    {/* Header bar */}
                    <div className="flex h-12 items-center justify-between border-b border-border px-6">
                        <div className="flex items-center gap-4">
                            <h1 className="text-sm font-medium text-ink">
                                {t('page.tabs.timeline', 'Plot')}
                            </h1>
                            <div className="flex items-center gap-2">
                                {mode === 'coach' &&
                                    coachSessionId !== null && (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                coachPanelRef.current?.sendSystemSignal(
                                                    'UNDO:last',
                                                );
                                            }}
                                            className="inline-flex items-center gap-1.5 rounded-md border border-border-light px-2.5 py-1 text-[12px] font-medium text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink"
                                        >
                                            <Undo2 className="h-3.5 w-3.5" />
                                            {tCoach('undo.button')}
                                        </button>
                                    )}
                                <ModeToggle
                                    mode={mode}
                                    onChange={setMode}
                                    coachLabel={tCoach('mode.coach')}
                                    boardLabel={tCoach('mode.board')}
                                />
                            </div>
                        </div>
                        {mode === 'board' && hasActs && (
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={handleAddAct}
                            >
                                <Plus size={14} />
                                {t('act.addAct')}
                            </Button>
                        )}
                    </div>

                    {mode === 'coach' ? (
                        <CoachPanel
                            ref={coachPanelRef}
                            aiConfigured={aiConfigured}
                            bookId={book.id}
                            activeSessionId={coachSessionId}
                            onSessionCreated={setCoachSessionId}
                            onSessionEnded={() => setCoachSessionId(null)}
                        />
                    ) : hasActs ? (
                        <>
                            <div className="flex min-h-0 flex-1 overflow-hidden">
                                <DndContext
                                    sensors={sensors}
                                    collisionDetection={
                                        collisionDetectionStrategy
                                    }
                                    onDragStart={handleDragStart}
                                    onDragEnd={handleDragEnd}
                                >
                                    <div className="grid h-full min-w-0 flex-1 auto-cols-[minmax(320px,1fr)] grid-flow-col overflow-auto">
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
                                            <div className="flex items-center gap-2 rounded bg-surface-card px-2 py-1 opacity-95 shadow-[0_4px_16px_#0000001F,0_0_0_1px_#0000000A]">
                                                <span className="flex shrink-0 items-center text-ink-faint">
                                                    <GripVertical className="h-3 w-3" />
                                                </span>
                                                <span className="min-w-0 flex-1 truncate text-[12px] text-ink-soft">
                                                    {activeItem.beat.title}
                                                </span>
                                            </div>
                                        )}
                                        {activeItem?.type === 'plotpoint' && (
                                            <div className="flex items-center gap-2 rounded-lg border border-border bg-surface px-3 py-2 opacity-95 shadow-[0_4px_16px_#0000001F,0_0_0_1px_#0000000A]">
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

type ModeToggleProps = {
    mode: PlotMode;
    onChange: (mode: PlotMode) => void;
    coachLabel: string;
    boardLabel: string;
};

function ModeToggle({
    mode,
    onChange,
    coachLabel,
    boardLabel,
}: ModeToggleProps) {
    return (
        <div className="inline-flex items-center gap-0.5 rounded-md border border-border-light bg-surface-card p-0.5">
            <ModeToggleButton
                active={mode === 'coach'}
                onClick={() => onChange('coach')}
                label={coachLabel}
                icon={<MessageSquare className="h-3.5 w-3.5" />}
            />
            <ModeToggleButton
                active={mode === 'board'}
                onClick={() => onChange('board')}
                label={boardLabel}
                icon={<LayoutGrid className="h-3.5 w-3.5" />}
            />
        </div>
    );
}

function ModeToggleButton({
    active,
    onClick,
    label,
    icon,
}: {
    active: boolean;
    onClick: () => void;
    label: string;
    icon: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={cn(
                'inline-flex items-center gap-1.5 rounded-[5px] px-2.5 py-1 text-[12px] font-medium transition-colors',
                active
                    ? 'border border-border-light bg-surface text-ink shadow-[0_1px_2px_rgba(0,0,0,0.04)]'
                    : 'border border-transparent text-ink-muted hover:text-ink',
            )}
        >
            {icon}
            {label}
        </button>
    );
}
