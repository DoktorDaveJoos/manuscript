import {
    DndContext,
    DragOverlay,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors
    
    
} from '@dnd-kit/core';
import type {DragEndEvent, DragStartEvent} from '@dnd-kit/core';
import { SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Download, GripVertical, ListOrdered } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import { downloadExport } from '@/lib/export-download';
import { cn } from '@/lib/utils';
import type { Chapter, Storyline } from '@/types/models';

const POINTER_SENSOR_OPTIONS = { activationConstraint: { distance: 5 } };
const STORAGE_KEY = 'manuscript:reading-order-width';
const MIN_WIDTH = 220;
const MAX_WIDTH = 480;
const DEFAULT_WIDTH = 280;

type ReadingOrderPanelProps = {
    chapters: Chapter[];
    storylines: Storyline[];
    book: { id: number; title: string };
    onReorder: (order: { id: number; storyline_id: number }[]) => void;
    onInterleave: () => void;
};

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

    const color = storyline?.color ?? '#737373';

    return (
        <div
            ref={setNodeRef}
            style={style}
            {...attributes}
            className={cn(
                'flex items-center gap-2 rounded px-2 py-1.5 transition-colors hover:bg-neutral-bg',
                isDragging && 'opacity-50',
            )}
        >
            <span
                {...listeners}
                className="flex shrink-0 cursor-grab items-center text-ink-muted active:cursor-grabbing"
            >
                <GripVertical className="h-3 w-3" />
            </span>

            <span
                className="shrink-0 rounded-sm"
                style={{ width: 3, height: 20, backgroundColor: color }}
            />

            <span className="shrink-0 text-[13px] font-semibold tabular-nums text-ink">
                {position}
            </span>

            <span className="min-w-0 flex-1 truncate text-[13px] text-ink">
                {chapter.title}
            </span>

            <span className="shrink-0 truncate text-[11px] text-ink-muted">
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
    book,
    onReorder,
    onInterleave,
}: ReadingOrderPanelProps) {
    const { t } = useTranslation('plot');
    const sortedFromProps = useMemo(
        () => [...chapters].sort((a, b) => a.reader_order - b.reader_order),
        [chapters],
    );
    const [orderedChapters, setOrderedChapters] = useState(sortedFromProps);
    const [activeChapter, setActiveChapter] = useState<Chapter | null>(null);
    const [prevSorted, setPrevSorted] = useState(sortedFromProps);
    if (prevSorted !== sortedFromProps) {
        setPrevSorted(sortedFromProps);
        setOrderedChapters(sortedFromProps);
    }
    const [showConfirmDialog, setShowConfirmDialog] = useState(false);
    const [exporting, setExporting] = useState(false);

    const [width, setWidth] = useState(() => {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            const parsed = Number(stored);
            if (parsed >= MIN_WIDTH && parsed <= MAX_WIDTH) return parsed;
        }
        return DEFAULT_WIDTH;
    });
    const widthRef = useRef(width);
    useEffect(() => {
        widthRef.current = width;
    }, [width]);
    const asideRef = useRef<HTMLElement>(null);
    const dragCleanupRef = useRef<(() => void) | null>(null);

    useEffect(() => {
        return () => dragCleanupRef.current?.();
    }, []);

    const handleResizeMouseDown = useCallback((e: React.MouseEvent) => {
        e.preventDefault();
        const startX = e.clientX;
        const startWidth = widthRef.current;

        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';

        const cleanup = () => {
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            document.removeEventListener('mousemove', handleMouseMove);
            document.removeEventListener('mouseup', handleMouseUp);
            dragCleanupRef.current = null;
        };

        const handleMouseMove = (ev: MouseEvent) => {
            const delta = startX - ev.clientX;
            const newWidth = Math.min(MAX_WIDTH, Math.max(MIN_WIDTH, startWidth + delta));
            widthRef.current = newWidth;
            if (asideRef.current) asideRef.current.style.width = `${newWidth}px`;
        };

        const handleMouseUp = () => {
            setWidth(widthRef.current);
            localStorage.setItem(STORAGE_KEY, String(widthRef.current));
            cleanup();
        };

        dragCleanupRef.current = cleanup;
        document.addEventListener('mousemove', handleMouseMove);
        document.addEventListener('mouseup', handleMouseUp);
    }, []);

    const sensors = useSensors(useSensor(PointerSensor, POINTER_SENSOR_OPTIONS));


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

    const handleExport = useCallback(() => {
        setExporting(true);
        downloadExport(book, { format: 'docx', scope: 'full', include_chapter_titles: true })
            .catch(() => {})
            .finally(() => setExporting(false));
    }, [book]);

    const activeChapterStoryline = activeChapter ? storylineMap.get(activeChapter.storyline_id) : undefined;

    return (
        <aside ref={asideRef} className="relative flex h-full shrink-0 flex-col border-l border-border-light bg-surface-card" style={{ width }}>
            {/* Resize handle */}
            <div
                onMouseDown={handleResizeMouseDown}
                className="group absolute inset-y-0 -left-1 z-10 w-2 cursor-col-resize"
            >
                <div className="absolute inset-y-0 left-[3px] w-px bg-transparent transition-colors group-hover:bg-ink/20" />
            </div>

            {/* Header */}
            <div className="flex h-12 items-center border-b border-border-light px-4">
                <div className="flex items-center gap-2">
                    <span className="flex items-center text-ink-muted">
                        <ListOrdered className="h-3.5 w-3.5" />
                    </span>
                    <span className="text-xs font-semibold uppercase tracking-[0.08em] text-ink">
                        {t('readingOrder.header')}
                    </span>
                    <span className="rounded-full bg-neutral-bg px-1.5 py-0.5 text-[10px] font-medium tabular-nums text-ink-soft">
                        {t('readingOrder.chapterCount', { count: orderedChapters.length })}
                    </span>
                </div>
            </div>

                    {/* Action bar */}
                    {hasMultipleStorylines && (
                        <div className="flex items-center justify-between border-b border-border-light px-4 py-2">
                            <button
                                type="button"
                                onClick={() => setShowConfirmDialog(true)}
                                className="flex items-center gap-1.5 rounded border border-border-light px-2 py-1 text-[12px] font-medium text-ink-soft transition-colors hover:bg-neutral-bg"
                            >
                                <ShuffleIcon />
                                {t('readingOrder.autoInterleave')}
                            </button>
                            <button
                                type="button"
                                onClick={handleExport}
                                disabled={exporting}
                                className="flex items-center gap-1.5 rounded border border-border-light px-2 py-1 text-[12px] font-medium text-ink-soft transition-colors hover:bg-neutral-bg disabled:opacity-50"
                            >
                                <Download className="h-3 w-3" />
                                {exporting ? t('readingOrder.exporting') : t('readingOrder.export')}
                            </button>
                        </div>
                    )}

                    {/* Chapter list */}
                    <div className="flex-1 overflow-y-auto p-2">
                        {orderedChapters.length === 0 ? (
                            <div className="flex flex-col items-center justify-center gap-2 py-12 text-center">
                                <span className="text-ink-muted">
                                    <ListOrdered className="h-3.5 w-3.5" />
                                </span>
                                <p className="text-[13px] text-ink-muted">
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
                                                    <span className="h-px flex-1 bg-border-light" />
                                                    <span className="text-[10px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                                                        {t('readingOrder.backstoryDivider')}
                                                    </span>
                                                    <span className="h-px flex-1 bg-border-light" />
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
                                        <div className="flex items-center gap-2 rounded bg-surface-card px-2 py-1.5 opacity-95 shadow-[0_4px_16px_#0000001F,0_0_0_1px_#0000000A]">
                                            <span className="flex shrink-0 items-center text-ink-muted">
                                                <GripVertical className="h-3 w-3" />
                                            </span>
                                            <span
                                                className="shrink-0 rounded-sm"
                                                style={{
                                                    width: 3,
                                                    height: 20,
                                                    backgroundColor: activeChapterStoryline?.color ?? '#737373',
                                                }}
                                            />
                                            <span className="min-w-0 flex-1 truncate text-[13px] text-ink">
                                                {activeChapter.title}
                                            </span>
                                        </div>
                                    )}
                                </DragOverlay>
                            </DndContext>
                        )}
                    </div>
            {/* Confirmation dialog */}
            {showConfirmDialog && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30">
                    <div className="mx-4 w-full max-w-sm rounded-lg bg-surface-card p-5 shadow-lg">
                        <h3 className="text-[14px] font-semibold text-ink">{t('readingOrder.confirmTitle')}</h3>
                        <p className="mt-2 text-[13px] leading-relaxed text-ink-soft">
                            {t('readingOrder.confirmMessage', { count: orderedChapters.length })}
                        </p>
                        <div className="mt-4 flex justify-end gap-2">
                            <Button variant="secondary" size="sm" type="button" onClick={() => setShowConfirmDialog(false)}>
                                {t('readingOrder.confirmCancel')}
                            </Button>
                            <Button variant="accent" size="sm" type="button" onClick={handleInterleaveConfirm}>
                                {t('readingOrder.confirmContinue')}
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </aside>
    );
}
