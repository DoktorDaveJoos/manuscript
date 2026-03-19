import type { DragEndEvent, DragStartEvent } from '@dnd-kit/core';
import {
    DndContext,
    DragOverlay,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import { useDraggable, useDroppable } from '@dnd-kit/core';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { buildTimelineGrid, cellKey } from '@/lib/plot-utils';
import type { Act, PlotPoint, Storyline } from '@/types/models';
import ChapterActContextMenu from './ChapterActContextMenu';
import PlotPointCard from './PlotPointCard';

type ChapterColumn = {
    id: number;
    title: string;
    reader_order: number;
    act_id: number | null;
    storyline_id: number;
    tension_score: number | null;
    word_count?: number;
};

type Props = {
    acts: (Act & { chapters: ChapterColumn[] })[];
    storylines: Storyline[];
    plotPoints: PlotPoint[];
    chapters?: ChapterColumn[];
    onSelectPlotPoint: (pp: PlotPoint) => void;
    onCreatePlotPoint: (storylineId: number, chapterId: number) => void;
    onAssignChapterAct?: (chapterId: number, actId: number | null) => void;
    onExportChapter?: (chapterId: number) => void;
};

const ACT_COLORS: Record<number, string> = {
    0: 'var(--color-accent)',
    1: 'var(--color-status-revised)',
    2: '#A3C4A0',
};

const POINTER_SENSOR_OPTIONS = { activationConstraint: { distance: 5 } };

function DraggableChapterChip({
    chapter,
    abbrevLabel,
    onContextMenu,
}: {
    chapter: ChapterColumn;
    abbrevLabel: string;
    onContextMenu?: (e: React.MouseEvent) => void;
}) {
    const { attributes, listeners, setNodeRef, transform, isDragging } =
        useDraggable({
            id: `chapter-${chapter.id}`,
            data: { type: 'chapter', chapter },
        });

    const style: React.CSSProperties = transform
        ? {
              transform: `translate(${transform.x}px, ${transform.y}px)`,
              opacity: isDragging ? 0.4 : 1,
          }
        : {};

    return (
        <span
            ref={setNodeRef}
            {...attributes}
            {...listeners}
            onContextMenu={onContextMenu}
            className="cursor-grab rounded bg-neutral-bg px-1.5 py-0.5 text-[11px] text-ink-soft select-none"
            style={style}
        >
            {abbrevLabel} &middot; {chapter.title}
        </span>
    );
}

function DroppableActZone({
    actId,
    width,
    children,
}: {
    actId: number;
    width: number;
    children: React.ReactNode;
}) {
    const { setNodeRef, isOver } = useDroppable({
        id: `act-${actId}`,
        data: { type: 'act-drop-zone' },
    });

    return (
        <div
            ref={setNodeRef}
            className={`flex items-center gap-2 border-b border-border px-3 py-2 transition-colors ${isOver ? 'bg-accent/5' : ''}`}
            style={{ width }}
        >
            {children}
        </div>
    );
}

export default function SwimLaneTimeline({
    acts,
    storylines,
    plotPoints,
    chapters = [],
    onSelectPlotPoint,
    onCreatePlotPoint,
    onAssignChapterAct,
    onExportChapter,
}: Props) {
    const { t } = useTranslation('plot');
    const { grid } = buildTimelineGrid(acts, storylines, plotPoints);
    const ACT_COL_W = 240;
    const LABEL_W = 120;
    const hasActs = acts.length > 0;

    const sensors = useSensors(
        useSensor(PointerSensor, POINTER_SENSOR_OPTIONS),
    );
    const [activeChapter, setActiveChapter] = useState<ChapterColumn | null>(
        null,
    );

    const unassignedChapters = useMemo(
        () =>
            chapters
                .filter((ch) => ch.act_id == null)
                .sort((a, b) => a.reader_order - b.reader_order),
        [chapters],
    );

    const [contextMenu, setContextMenu] = useState<{
        chapterId: number;
        currentActId: number | null;
        position: { x: number; y: number };
    } | null>(null);

    const chapterWordCountMap = useMemo(() => {
        const map = new Map<number, number>();
        for (const ch of chapters) {
            map.set(ch.id, ch.word_count ?? 0);
        }
        return map;
    }, [chapters]);

    const handleDragStart = (event: DragStartEvent) => {
        const ch = event.active.data.current?.chapter as
            | ChapterColumn
            | undefined;
        setActiveChapter(ch ?? null);
    };

    const handleDragEnd = (event: DragEndEvent) => {
        setActiveChapter(null);

        if (!onAssignChapterAct || !event.over) return;

        const chapterData = event.active.data.current?.chapter as
            | ChapterColumn
            | undefined;
        if (!chapterData) return;

        const overId = String(event.over.id);
        if (overId === 'unassigned-tray') {
            onAssignChapterAct(chapterData.id, null);
        } else if (overId.startsWith('act-')) {
            const actId = Number(overId.replace('act-', ''));
            if (!isNaN(actId)) {
                onAssignChapterAct(chapterData.id, actId);
            }
        }
    };

    const handleChapterContextMenu = (
        e: React.MouseEvent,
        chapter: ChapterColumn,
    ) => {
        e.preventDefault();
        if (!onAssignChapterAct || acts.length === 0) return;
        setContextMenu({
            chapterId: chapter.id,
            currentActId: chapter.act_id,
            position: { x: e.clientX, y: e.clientY },
        });
    };

    const showUnassignedTray =
        hasActs &&
        onAssignChapterAct &&
        (unassignedChapters.length > 0 || activeChapter != null);

    const content = (
        <div className="overflow-auto">
            <div
                className="inline-flex flex-col"
                style={{
                    minWidth: LABEL_W + Math.max(1, acts.length) * ACT_COL_W,
                }}
            >
                {/* Act headers */}
                {hasActs && (
                    <div className="flex" style={{ paddingLeft: LABEL_W }}>
                        {acts.map((act, i) => (
                            <DroppableActZone
                                key={act.id}
                                actId={act.id}
                                width={ACT_COL_W}
                            >
                                <div
                                    className="h-3.5 w-1 rounded-sm"
                                    style={{
                                        backgroundColor:
                                            ACT_COLORS[i] ??
                                            'var(--color-accent)',
                                    }}
                                />
                                <span className="text-[11px] font-semibold tracking-wide text-ink-soft uppercase">
                                    {t('actTitle', {
                                        number: act.number,
                                        title: act.title,
                                    })}
                                </span>
                            </DroppableActZone>
                        ))}
                    </div>
                )}

                {/* Storyline rows */}
                {storylines.map((storyline) => (
                    <div
                        key={storyline.id}
                        className="flex border-b border-border-light"
                    >
                        <div
                            className="flex items-start gap-2 px-3 py-3"
                            style={{ width: LABEL_W, flexShrink: 0 }}
                        >
                            <div
                                className="mt-0.5 h-2 w-2 rounded-full"
                                style={{
                                    backgroundColor:
                                        storyline.color ?? '#737373',
                                }}
                            />
                            <span className="text-[11px] font-semibold tracking-wide text-ink-soft uppercase">
                                {storyline.name}
                            </span>
                        </div>
                        {hasActs ? (
                            acts.map((act) => {
                                const cell = grid.get(
                                    cellKey(storyline.id, act.id),
                                );
                                const cellChapters = cell?.chapters ?? [];
                                const firstChapter = cellChapters[0];
                                return (
                                    <div
                                        key={act.id}
                                        className="min-h-[80px] cursor-pointer border-l border-border-light p-1.5 hover:bg-surface"
                                        style={{ width: ACT_COL_W }}
                                        onClick={() => {
                                            if (
                                                !cell?.plotPoints.length &&
                                                firstChapter
                                            ) {
                                                onCreatePlotPoint(
                                                    storyline.id,
                                                    firstChapter.id,
                                                );
                                            }
                                        }}
                                    >
                                        {cellChapters.length > 0 && (
                                            <div className="mb-1.5 flex flex-wrap gap-1">
                                                {cellChapters.map((ch) => (
                                                    <DraggableChapterChip
                                                        key={ch.id}
                                                        chapter={ch}
                                                        abbrevLabel={t(
                                                            'timeline.chapterAbbrev',
                                                            {
                                                                number:
                                                                    ch.reader_order +
                                                                    1,
                                                            },
                                                        )}
                                                        onContextMenu={(e) =>
                                                            handleChapterContextMenu(
                                                                e,
                                                                ch,
                                                            )
                                                        }
                                                    />
                                                ))}
                                            </div>
                                        )}
                                        <div className="flex flex-col gap-1">
                                            {cell?.plotPoints.map((pp) => (
                                                <PlotPointCard
                                                    key={pp.id}
                                                    plotPoint={pp}
                                                    chapterWordCount={
                                                        pp.intended_chapter_id
                                                            ? chapterWordCountMap.get(
                                                                  pp.intended_chapter_id,
                                                              )
                                                            : pp.actual_chapter_id
                                                              ? chapterWordCountMap.get(
                                                                    pp.actual_chapter_id,
                                                                )
                                                              : undefined
                                                    }
                                                    onClick={() =>
                                                        onSelectPlotPoint(pp)
                                                    }
                                                />
                                            ))}
                                        </div>
                                    </div>
                                );
                            })
                        ) : (
                            /* No acts — single column with all chapters for this storyline */
                            <div
                                className="min-h-[80px] flex-1 border-l border-border-light p-1.5"
                                style={{ minWidth: ACT_COL_W }}
                            >
                                {(() => {
                                    const slChapters = chapters.filter(
                                        (ch) =>
                                            ch.storyline_id === storyline.id,
                                    );
                                    return slChapters.length > 0 ? (
                                        <div className="flex flex-wrap gap-1">
                                            {slChapters.map((ch) => (
                                                <span
                                                    key={ch.id}
                                                    className="rounded bg-neutral-bg px-1.5 py-0.5 text-[11px] text-ink-soft"
                                                >
                                                    {t(
                                                        'timeline.chapterAbbrev',
                                                        {
                                                            number:
                                                                ch.reader_order +
                                                                1,
                                                        },
                                                    )}{' '}
                                                    &middot; {ch.title}
                                                </span>
                                            ))}
                                        </div>
                                    ) : null;
                                })()}
                            </div>
                        )}
                    </div>
                ))}

                {/* Unassigned chapters tray */}
                {showUnassignedTray && (
                    <UnassignedTray
                        chapters={unassignedChapters}
                        onContextMenu={handleChapterContextMenu}
                    />
                )}
            </div>

            {contextMenu && (
                <ChapterActContextMenu
                    acts={acts}
                    currentActId={contextMenu.currentActId}
                    chapterId={contextMenu.chapterId}
                    position={contextMenu.position}
                    onAssign={(actId) =>
                        onAssignChapterAct!(contextMenu.chapterId, actId)
                    }
                    onExport={onExportChapter}
                    onClose={() => setContextMenu(null)}
                />
            )}
        </div>
    );

    if (!onAssignChapterAct) return content;

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragStart={handleDragStart}
            onDragEnd={handleDragEnd}
        >
            {content}
            <DragOverlay>
                {activeChapter && (
                    <span className="rounded bg-neutral-bg px-1.5 py-0.5 text-[11px] text-ink-soft shadow-lg">
                        {t('timeline.chapterAbbrev', {
                            number: activeChapter.reader_order + 1,
                        })}{' '}
                        &middot; {activeChapter.title}
                    </span>
                )}
            </DragOverlay>
        </DndContext>
    );
}

function UnassignedTray({
    chapters,
    onContextMenu,
}: {
    chapters: ChapterColumn[];
    onContextMenu: (e: React.MouseEvent, chapter: ChapterColumn) => void;
}) {
    const { t } = useTranslation('plot');
    const { setNodeRef, isOver } = useDroppable({
        id: 'unassigned-tray',
        data: { type: 'unassigned-drop-zone' },
    });

    return (
        <div
            ref={setNodeRef}
            className={`mt-2 flex flex-wrap items-center gap-2 rounded-lg border border-dashed px-4 py-3 transition-colors ${
                isOver ? 'border-accent bg-accent/5' : 'border-border'
            }`}
        >
            <span className="mr-1 text-[11px] font-semibold tracking-wide text-ink-faint uppercase">
                {t('timeline.unassignedChapters')}
            </span>
            {chapters.map((ch) => (
                <DraggableChapterChip
                    key={ch.id}
                    chapter={ch}
                    abbrevLabel={t('timeline.chapterAbbrev', {
                        number: ch.reader_order + 1,
                    })}
                    onContextMenu={(e) => onContextMenu(e, ch)}
                />
            ))}
        </div>
    );
}
