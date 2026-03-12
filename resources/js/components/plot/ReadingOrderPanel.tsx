import { cn } from '@/lib/utils';
import type { Chapter, Storyline } from '@/types/models';
import {
    DndContext,
    DragOverlay,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors,
    type DragEndEvent,
    type DragStartEvent,
} from '@dnd-kit/core';
import { SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

const POINTER_SENSOR_OPTIONS = { activationConstraint: { distance: 5 } };

type ReadingOrderPanelProps = {
    chapters: Chapter[];
    storylines: Storyline[];
    bookId: number;
    isOpen: boolean;
    onToggle: () => void;
    onReorder: (order: { id: number; storyline_id: number }[]) => void;
    onInterleave: () => void;
};

function GripIcon() {
    return (
        <svg width="6" height="10" viewBox="0 0 6 10" fill="currentColor">
            <circle cx="1" cy="1" r="1" />
            <circle cx="5" cy="1" r="1" />
            <circle cx="1" cy="5" r="1" />
            <circle cx="5" cy="5" r="1" />
            <circle cx="1" cy="9" r="1" />
            <circle cx="5" cy="9" r="1" />
        </svg>
    );
}

function ListOrderedIcon() {
    return (
        <svg
            width="14"
            height="14"
            viewBox="0 0 14 14"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.2"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path d="M6 3h6" />
            <path d="M6 7h6" />
            <path d="M6 11h6" />
            <text x="1" y="4" fontSize="4" fontWeight="600" fill="currentColor" stroke="none" fontFamily="system-ui">1</text>
            <text x="1" y="8" fontSize="4" fontWeight="600" fill="currentColor" stroke="none" fontFamily="system-ui">2</text>
            <text x="1" y="12" fontSize="4" fontWeight="600" fill="currentColor" stroke="none" fontFamily="system-ui">3</text>
        </svg>
    );
}

function ShuffleIcon() {
    return (
        <svg
            width="14"
            height="14"
            viewBox="0 0 14 14"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.2"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path d="M10 2l2 2-2 2" />
            <path d="M10 8l2 2-2 2" />
            <path d="M2 4h3l2 3 2 3h3" />
            <path d="M2 10h3l1.5-2.25" />
            <path d="M9 4h3" />
        </svg>
    );
}

function SortableChapterRow({
    chapter,
    position,
    storyline,
    storylineChapterIndex,
    t,
}: {
    chapter: Chapter;
    position: number;
    storyline: Storyline | undefined;
    storylineChapterIndex: number;
    t: (key: string, opts?: Record<string, unknown>) => string;
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: `reading-${chapter.id}`,
        data: { type: 'reading-order-chapter', chapter },
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    const color = storyline?.color ?? '#8A857D';

    return (
        <div
            ref={setNodeRef}
            style={style}
            {...attributes}
            className={cn(
                'flex items-center gap-2 rounded px-2 py-1.5 transition-colors hover:bg-[#F0EEEA]',
                isDragging && 'opacity-50',
            )}
        >
            <span
                {...listeners}
                className="flex shrink-0 cursor-grab items-center text-[#8A857D] active:cursor-grabbing"
            >
                <GripIcon />
            </span>

            <span
                className="shrink-0 rounded-sm"
                style={{ width: 3, height: 20, backgroundColor: color }}
            />

            <span className="shrink-0 text-[13px] font-semibold tabular-nums text-[#2D2A26]">
                {position}
            </span>

            <span className="min-w-0 flex-1 truncate text-[13px] text-[#2D2A26]">
                {chapter.title}
            </span>

            <span className="shrink-0 truncate text-[11px] text-[#8A857D]">
                {t('readingOrder.storylineChapter', {
                    storyline: storyline?.name ?? t('readingOrder.unknownStoryline'),
                    index: storylineChapterIndex,
                })}
            </span>
        </div>
    );
}

export default function ReadingOrderPanel({
    chapters,
    storylines,
    bookId: _bookId,
    isOpen,
    onToggle,
    onReorder,
    onInterleave,
}: ReadingOrderPanelProps) {
    const { t } = useTranslation('plot');
    const [orderedChapters, setOrderedChapters] = useState<Chapter[]>([]);
    const [activeChapter, setActiveChapter] = useState<Chapter | null>(null);
    const [showConfirmDialog, setShowConfirmDialog] = useState(false);

    const sensors = useSensors(useSensor(PointerSensor, POINTER_SENSOR_OPTIONS));

    useEffect(() => {
        const sorted = [...chapters].sort((a, b) => a.reader_order - b.reader_order);
        setOrderedChapters(sorted);
    }, [chapters]);

    const storylineMap = useMemo(() => {
        const map = new Map<number, Storyline>();
        for (const s of storylines) {
            map.set(s.id, s);
        }
        return map;
    }, [storylines]);

    const storylineChapterCounters = useMemo(() => {
        const counters = new Map<number, Map<number, number>>();
        for (const ch of orderedChapters) {
            if (!counters.has(ch.storyline_id)) {
                counters.set(ch.storyline_id, new Map());
            }
            const slMap = counters.get(ch.storyline_id)!;
            slMap.set(ch.id, slMap.size + 1);
        }
        return counters;
    }, [orderedChapters]);

    const positionMap = useMemo(() => {
        const map = new Map<number, number>();
        orderedChapters.forEach((ch, i) => map.set(ch.id, i + 1));
        return map;
    }, [orderedChapters]);

    const hasMultipleStorylines = storylines.length > 1;

    const mainChapters = useMemo(
        () =>
            orderedChapters.filter((ch) => {
                const sl = storylineMap.get(ch.storyline_id);
                return sl?.type !== 'backstory';
            }),
        [orderedChapters, storylineMap],
    );

    const backstoryChapters = useMemo(
        () =>
            orderedChapters.filter((ch) => {
                const sl = storylineMap.get(ch.storyline_id);
                return sl?.type === 'backstory';
            }),
        [orderedChapters, storylineMap],
    );

    const getStorylineChapterIndex = useCallback(
        (chapter: Chapter) => {
            return storylineChapterCounters.get(chapter.storyline_id)?.get(chapter.id) ?? 0;
        },
        [storylineChapterCounters],
    );

    const handleDragStart = useCallback((event: DragStartEvent) => {
        const data = event.active.data.current;
        if (data?.type === 'reading-order-chapter') {
            setActiveChapter(data.chapter);
        }
    }, []);

    const handleDragEnd = useCallback(
        (event: DragEndEvent) => {
            setActiveChapter(null);

            const { active, over } = event;
            if (!over || active.id === over.id) return;

            const oldIndex = orderedChapters.findIndex((ch) => `reading-${ch.id}` === active.id);
            const newIndex = orderedChapters.findIndex((ch) => `reading-${ch.id}` === over.id);
            if (oldIndex === -1 || newIndex === -1) return;

            const reordered = [...orderedChapters];
            const [moved] = reordered.splice(oldIndex, 1);
            reordered.splice(newIndex, 0, moved);
            setOrderedChapters(reordered);

            onReorder(
                reordered.map((ch) => ({
                    id: ch.id,
                    storyline_id: ch.storyline_id,
                })),
            );
        },
        [orderedChapters, onReorder],
    );

    const handleInterleaveConfirm = useCallback(() => {
        setShowConfirmDialog(false);
        onInterleave();
    }, [onInterleave]);

    const activeChapterStoryline = activeChapter ? storylineMap.get(activeChapter.storyline_id) : undefined;

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
                        <div className="flex items-center gap-2">
                            <span className="flex items-center text-[#8A857D]">
                                <ListOrderedIcon />
                            </span>
                            <span className="text-xs font-semibold uppercase tracking-[0.06em] text-[#2D2A26]">
                                {t('readingOrder.header')}
                            </span>
                            <span className="rounded-full bg-[#F0EEEA] px-1.5 py-0.5 text-[10px] font-medium tabular-nums text-[#5A574F]">
                                {t('readingOrder.chapterCount', { count: orderedChapters.length })}
                            </span>
                        </div>
                        <button
                            type="button"
                            onClick={onToggle}
                            className="flex size-6 items-center justify-center rounded text-[#8A857D] transition-colors hover:text-[#2D2A26]"
                        >
                            <svg
                                width="14"
                                height="14"
                                viewBox="0 0 14 14"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            >
                                <path d="M6 4l3 3-3 3" />
                            </svg>
                        </button>
                    </div>

                    {/* Action bar */}
                    {hasMultipleStorylines && (
                        <div className="flex items-center justify-between border-b border-[#ECEAE4] px-4 py-2">
                            <button
                                type="button"
                                onClick={() => setShowConfirmDialog(true)}
                                className="flex items-center gap-1.5 rounded border border-[#ECEAE4] px-2 py-1 text-[12px] font-medium text-[#5A574F] transition-colors hover:bg-[#F0EEEA]"
                            >
                                <ShuffleIcon />
                                {t('readingOrder.autoInterleave')}
                            </button>
                            <span className="text-[11px] text-[#8A857D]">{t('readingOrder.exportOrder')}</span>
                        </div>
                    )}

                    {/* Chapter list */}
                    <div className="flex-1 overflow-y-auto p-2">
                        {orderedChapters.length === 0 ? (
                            <div className="flex flex-col items-center justify-center gap-2 py-12 text-center">
                                <span className="text-[#8A857D]">
                                    <ListOrderedIcon />
                                </span>
                                <p className="text-[13px] text-[#8A857D]">
                                    {t('readingOrder.emptyState')}
                                </p>
                            </div>
                        ) : (
                            <DndContext
                                sensors={sensors}
                                collisionDetection={closestCenter}
                                onDragStart={handleDragStart}
                                onDragEnd={handleDragEnd}
                            >
                                <SortableContext
                                    items={orderedChapters.map((ch) => `reading-${ch.id}`)}
                                    strategy={verticalListSortingStrategy}
                                >
                                    <div className="flex flex-col gap-px">
                                        {mainChapters.map((chapter) => (
                                            <SortableChapterRow
                                                key={chapter.id}
                                                chapter={chapter}
                                                position={positionMap.get(chapter.id) ?? 0}
                                                storyline={storylineMap.get(chapter.storyline_id)}
                                                storylineChapterIndex={getStorylineChapterIndex(chapter)}
                                                t={t}
                                            />
                                        ))}

                                        {backstoryChapters.length > 0 && (
                                            <>
                                                <div className="flex items-center gap-2 px-2 pb-1 pt-3">
                                                    <span className="h-px flex-1 bg-[#ECEAE4]" />
                                                    <span className="text-[10px] font-semibold uppercase tracking-[0.08em] text-[#8A857D]">
                                                        {t('readingOrder.backstoryDivider')}
                                                    </span>
                                                    <span className="h-px flex-1 bg-[#ECEAE4]" />
                                                </div>
                                                {backstoryChapters.map((chapter) => (
                                                    <SortableChapterRow
                                                        key={chapter.id}
                                                        chapter={chapter}
                                                        position={positionMap.get(chapter.id) ?? 0}
                                                        storyline={storylineMap.get(chapter.storyline_id)}
                                                        storylineChapterIndex={getStorylineChapterIndex(chapter)}
                                                        t={t}
                                                    />
                                                ))}
                                            </>
                                        )}
                                    </div>
                                </SortableContext>

                                <DragOverlay>
                                    {activeChapter && (
                                        <div className="flex items-center gap-2 rounded bg-white px-2 py-1.5 opacity-95 shadow-[0_4px_16px_#0000001F,0_0_0_1px_#0000000A]">
                                            <span className="flex shrink-0 items-center text-[#8A857D]">
                                                <GripIcon />
                                            </span>
                                            <span
                                                className="shrink-0 rounded-sm"
                                                style={{
                                                    width: 3,
                                                    height: 20,
                                                    backgroundColor: activeChapterStoryline?.color ?? '#8A857D',
                                                }}
                                            />
                                            <span className="min-w-0 flex-1 truncate text-[13px] text-[#2D2A26]">
                                                {activeChapter.title}
                                            </span>
                                        </div>
                                    )}
                                </DragOverlay>
                            </DndContext>
                        )}
                    </div>
                </>
            ) : (
                /* Collapsed state */
                <button
                    type="button"
                    onClick={onToggle}
                    className="flex h-full w-full flex-col items-center gap-3 pt-3 transition-colors hover:bg-[#FAFAF7]"
                >
                    <span className="flex size-6 items-center justify-center text-[#8A857D]">
                        <svg
                            width="14"
                            height="14"
                            viewBox="0 0 14 14"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        >
                            <path d="M8 4l-3 3 3 3" />
                        </svg>
                    </span>
                    <span className="flex size-5 items-center justify-center text-[#8A857D]">
                        <ListOrderedIcon />
                    </span>
                </button>
            )}

            {/* Confirmation dialog */}
            {showConfirmDialog && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30">
                    <div className="mx-4 w-full max-w-sm rounded-lg bg-white p-5 shadow-lg">
                        <h3 className="text-[14px] font-semibold text-[#2D2A26]">{t('readingOrder.confirmTitle')}</h3>
                        <p className="mt-2 text-[13px] leading-relaxed text-[#5A574F]">
                            {t('readingOrder.confirmMessage', { count: orderedChapters.length })}
                        </p>
                        <div className="mt-4 flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => setShowConfirmDialog(false)}
                                className="rounded border border-[#ECEAE4] px-3 py-1.5 text-[13px] font-medium text-[#5A574F] transition-colors hover:bg-[#F0EEEA]"
                            >
                                {t('readingOrder.confirmCancel')}
                            </button>
                            <button
                                type="button"
                                onClick={handleInterleaveConfirm}
                                className="rounded bg-[#C8B88A] px-3 py-1.5 text-[13px] font-medium text-white transition-colors hover:bg-[#b8a87a]"
                            >
                                {t('readingOrder.confirmContinue')}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </aside>
    );
}
