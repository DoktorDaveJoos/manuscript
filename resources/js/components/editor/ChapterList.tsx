import { reorder as reorderChapters, updateTitle } from '@/actions/App/Http/Controllers/ChapterController';
import { reorder as reorderStorylines, update as updateStoryline } from '@/actions/App/Http/Controllers/StorylineController';
import { formatCompactCount, jsonFetchHeaders } from '@/lib/utils';
import type { Chapter, Storyline } from '@/types/models';
import { router } from '@inertiajs/react';
import {
    DndContext,
    DragOverlay,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors,
    type DragEndEvent,
    type DragOverEvent,
    type DragStartEvent,
} from '@dnd-kit/core';
import { SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import ChapterContextMenu from './ChapterContextMenu';
import ChapterListItem, { statusDot } from './ChapterListItem';
import DeleteChapterDialog from './DeleteChapterDialog';
import DeleteStorylineDialog from './DeleteStorylineDialog';
import SceneListItem from './SceneListItem';
import StorylineContextMenu from './StorylineContextMenu';

const POINTER_SENSOR_OPTIONS = { activationConstraint: { distance: 5 } };

type ContextMenuState =
    | { type: 'chapter'; chapter: Chapter; position: { x: number; y: number } }
    | { type: 'storyline'; storyline: Storyline; position: { x: number; y: number } }
    | null;

type DialogState =
    | { type: 'deleteChapter'; chapter: Chapter }
    | { type: 'deleteStoryline'; storyline: Storyline }
    | null;

function SortableChapterItem({
    chapter,
    bookId,
    index,
    isActive,
    onBeforeNavigate,
    onContextMenu,
    hasMultipleScenes,
    isExpanded,
    onToggleExpand,
}: {
    chapter: Chapter;
    bookId: number;
    index: number;
    isActive: boolean;
    onBeforeNavigate?: () => Promise<void>;
    onContextMenu: (e: React.MouseEvent) => void;
    hasMultipleScenes?: boolean;
    isExpanded?: boolean;
    onToggleExpand?: () => void;
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging, isOver } = useSortable({
        id: `chapter-${chapter.id}`,
        data: { type: 'chapter', chapter },
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    return (
        <div ref={setNodeRef} style={style} {...attributes} className="relative">
            {isOver && <div className="absolute -top-px left-2.5 right-2.5 h-0.5 rounded-sm bg-drop" />}
            <ChapterListItem
                chapter={chapter}
                bookId={bookId}
                index={index}
                isActive={isActive}
                onBeforeNavigate={onBeforeNavigate}
                onContextMenu={onContextMenu}
                dragListeners={listeners}
                isDragging={isDragging}
                hasMultipleScenes={hasMultipleScenes}
                isExpanded={isExpanded}
                onToggleExpand={onToggleExpand}
            />
        </div>
    );
}

function SortableStorylineGroup({
    storyline,
    showHeader,
    children,
    onContextMenu,
}: {
    storyline: Storyline;
    showHeader: boolean;
    children: React.ReactNode;
    onContextMenu: (e: React.MouseEvent) => void;
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: `storyline-${storyline.id}`,
        data: { type: 'storyline', storyline },
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    return (
        <div ref={setNodeRef} style={style} {...attributes} className={`flex flex-col gap-0.5 ${isDragging ? 'opacity-50' : ''}`}>
            {showHeader && (
                <span
                    {...listeners}
                    onContextMenu={onContextMenu}
                    className="flex cursor-grab items-center gap-1.5 px-2.5 pb-2 pt-1 text-[11px] font-medium uppercase tracking-[0.08em] text-ink-faint active:cursor-grabbing"
                >
                    {storyline.color && <span className="inline-block size-[6px] rounded-full" style={{ backgroundColor: storyline.color }} />}
                    {storyline.name}
                </span>
            )}
            {children}
        </div>
    );
}

export default function ChapterList({
    storylines: initialStorylines,
    bookId,
    activeChapterId,
    onBeforeNavigate,
    onAddChapter,
}: {
    storylines: Storyline[];
    bookId: number;
    activeChapterId?: number;
    onBeforeNavigate?: () => Promise<void>;
    onAddChapter?: (storylineId: number) => void;
}) {
    const [storylines, setStorylines] = useState(initialStorylines);
    const [activeItem, setActiveItem] = useState<{ type: 'chapter'; chapter: Chapter } | { type: 'storyline'; storyline: Storyline } | null>(null);
    const [contextMenu, setContextMenu] = useState<ContextMenuState>(null);
    const [dialog, setDialog] = useState<DialogState>(null);
    const [expandedChapterIds, setExpandedChapterIds] = useState<Set<number>>(() => {
        return activeChapterId ? new Set([activeChapterId]) : new Set();
    });
    const [renamingChapterId, setRenamingChapterId] = useState<number | null>(null);
    const [renamingStorylineId, setRenamingStorylineId] = useState<number | null>(null);
    const chapterRenameRef = useRef<HTMLInputElement>(null);
    const storylineRenameRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        setStorylines(initialStorylines);
    }, [initialStorylines]);

    useEffect(() => {
        if (activeChapterId) {
            setExpandedChapterIds((prev) => {
                if (prev.has(activeChapterId)) return prev;
                const next = new Set(prev);
                next.add(activeChapterId);
                return next;
            });
        }
    }, [activeChapterId]);

    const toggleChapterExpand = useCallback((chapterId: number) => {
        setExpandedChapterIds((prev) => {
            const next = new Set(prev);
            if (next.has(chapterId)) next.delete(chapterId);
            else next.add(chapterId);
            return next;
        });
    }, []);

    const sensors = useSensors(useSensor(PointerSensor, POINTER_SENSOR_OPTIONS));

    let chapterIndex = 1;

    const storylineIds = useMemo(() => storylines.map((s) => `storyline-${s.id}`), [storylines]);

    const handleDragStart = useCallback((event: DragStartEvent) => {
        const data = event.active.data.current;
        if (data?.type === 'chapter') {
            setActiveItem({ type: 'chapter', chapter: data.chapter });
        } else if (data?.type === 'storyline') {
            setActiveItem({ type: 'storyline', storyline: data.storyline });
        }
    }, []);

    const handleDragOver = useCallback((event: DragOverEvent) => {
        const { active, over } = event;
        if (!over || active.id === over.id) return;

        const activeData = active.data.current;
        const overData = over.data.current;

        if (activeData?.type === 'chapter' && overData?.type === 'chapter') {
            setStorylines((prev) => {
                const newStorylines = prev.map((s) => ({ ...s, chapters: [...(s.chapters ?? [])] }));

                let sourceIdx = -1;
                let sourceStorylineIdx = -1;
                let destIdx = -1;
                let destStorylineIdx = -1;

                for (let si = 0; si < newStorylines.length; si++) {
                    const chapters = newStorylines[si].chapters ?? [];
                    for (let ci = 0; ci < chapters.length; ci++) {
                        if (chapters[ci].id === activeData.chapter.id) {
                            sourceIdx = ci;
                            sourceStorylineIdx = si;
                        }
                        if (chapters[ci].id === overData.chapter.id) {
                            destIdx = ci;
                            destStorylineIdx = si;
                        }
                    }
                }

                if (sourceStorylineIdx === -1 || destStorylineIdx === -1) return prev;

                const [moved] = newStorylines[sourceStorylineIdx].chapters!.splice(sourceIdx, 1);
                newStorylines[destStorylineIdx].chapters!.splice(destIdx, 0, moved);

                return newStorylines;
            });
        }
    }, []);

    const handleDragEnd = useCallback(
        async (event: DragEndEvent) => {
            setActiveItem(null);

            const { active, over } = event;
            if (!over || active.id === over.id) return;

            const activeData = active.data.current;

            if (activeData?.type === 'storyline') {
                const oldIndex = storylines.findIndex((s) => `storyline-${s.id}` === active.id);
                const newIndex = storylines.findIndex((s) => `storyline-${s.id}` === over.id);

                if (oldIndex === -1 || newIndex === -1 || oldIndex === newIndex) return;

                const reordered = [...storylines];
                const [moved] = reordered.splice(oldIndex, 1);
                reordered.splice(newIndex, 0, moved);
                setStorylines(reordered);

                await fetch(reorderStorylines.url(bookId), {
                    method: 'POST',
                    headers: jsonFetchHeaders(),
                    body: JSON.stringify({ order: reordered.map((s) => s.id) }),
                });
                router.reload({ only: ['book'] });
                return;
            }

            if (activeData?.type === 'chapter') {
                const order = storylines.flatMap((s) =>
                    (s.chapters ?? []).map((ch) => ({
                        id: ch.id,
                        storyline_id: s.id,
                    })),
                );

                await fetch(reorderChapters.url(bookId), {
                    method: 'POST',
                    headers: jsonFetchHeaders(),
                    body: JSON.stringify({ order }),
                });
                router.reload({ only: ['book'] });
            }
        },
        [storylines, bookId],
    );

    const handleChapterContextMenu = useCallback((e: React.MouseEvent, chapter: Chapter) => {
        e.preventDefault();
        setContextMenu({ type: 'chapter', chapter, position: { x: e.clientX, y: e.clientY } });
    }, []);

    const handleStorylineContextMenu = useCallback((e: React.MouseEvent, storyline: Storyline) => {
        e.preventDefault();
        setContextMenu({ type: 'storyline', storyline, position: { x: e.clientX, y: e.clientY } });
    }, []);

    const handleRenameChapter = useCallback(
        (chapterId: number) => {
            setRenamingChapterId(chapterId);
            setTimeout(() => chapterRenameRef.current?.focus(), 0);
        },
        [],
    );

    const handleRenameChapterSubmit = useCallback(
        async (chapter: Chapter, newTitle: string) => {
            setRenamingChapterId(null);
            if (!newTitle.trim() || newTitle.trim() === chapter.title) return;

            await fetch(updateTitle.url({ book: bookId, chapter: chapter.id }), {
                method: 'PATCH',
                headers: jsonFetchHeaders(),
                body: JSON.stringify({ title: newTitle.trim() }),
            });
            router.reload({ only: ['book'] });
        },
        [bookId],
    );

    const handleRenameStoryline = useCallback(
        (storylineId: number) => {
            setRenamingStorylineId(storylineId);
            setTimeout(() => storylineRenameRef.current?.focus(), 0);
        },
        [],
    );

    const handleRenameStorylineSubmit = useCallback(
        async (storyline: Storyline, newName: string) => {
            setRenamingStorylineId(null);
            if (!newName.trim() || newName.trim() === storyline.name) return;

            await fetch(updateStoryline.url({ book: bookId, storyline: storyline.id }), {
                method: 'PATCH',
                headers: jsonFetchHeaders(),
                body: JSON.stringify({ name: newName.trim(), color: storyline.color }),
            });
            router.reload({ only: ['book'] });
        },
        [bookId],
    );

    const showHeaders = storylines.length > 1;

    return (
        <>
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragStart={handleDragStart} onDragOver={handleDragOver} onDragEnd={handleDragEnd}>
                <div className="flex flex-col gap-4">
                    <SortableContext items={storylineIds} strategy={verticalListSortingStrategy}>
                        {storylines.map((storyline) => (
                            <SortableStorylineGroup
                                key={storyline.id}
                                storyline={storyline}
                                showHeader={showHeaders}
                                onContextMenu={(e) => handleStorylineContextMenu(e, storyline)}
                            >
                                <SortableContext items={(storyline.chapters ?? []).map((ch) => `chapter-${ch.id}`)} strategy={verticalListSortingStrategy}>
                                    {storyline.chapters?.map((chapter) => {
                                        const index = chapterIndex++;

                                        if (renamingChapterId === chapter.id) {
                                            return (
                                                <input
                                                    key={chapter.id}
                                                    ref={chapterRenameRef}
                                                    type="text"
                                                    defaultValue={chapter.title}
                                                    className="mx-1 rounded-[5px] border border-border bg-surface px-2 py-1.5 text-[13px] leading-4 text-ink outline-none"
                                                    onBlur={(e) => handleRenameChapterSubmit(chapter, e.target.value)}
                                                    onKeyDown={(e) => {
                                                        if (e.key === 'Enter') handleRenameChapterSubmit(chapter, e.currentTarget.value);
                                                        if (e.key === 'Escape') setRenamingChapterId(null);
                                                    }}
                                                />
                                            );
                                        }

                                        const hasMultipleScenes = (chapter.scenes?.length ?? 0) > 1;
                                        const isExpanded = expandedChapterIds.has(chapter.id);

                                        return (
                                            <div key={chapter.id}>
                                                <SortableChapterItem
                                                    chapter={chapter}
                                                    bookId={bookId}
                                                    index={index}
                                                    isActive={chapter.id === activeChapterId}
                                                    onBeforeNavigate={onBeforeNavigate}
                                                    onContextMenu={(e) => handleChapterContextMenu(e, chapter)}
                                                    hasMultipleScenes={hasMultipleScenes}
                                                    isExpanded={isExpanded}
                                                    onToggleExpand={() => toggleChapterExpand(chapter.id)}
                                                />
                                                {hasMultipleScenes && isExpanded && (
                                                    <div className="flex flex-col">
                                                        {chapter.scenes!.map((scene) => (
                                                            <SceneListItem
                                                                key={scene.id}
                                                                scene={scene}
                                                                onClick={() => {
                                                                    const el = document.getElementById(`scene-${scene.id}`);
                                                                    el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                                                }}
                                                            />
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </SortableContext>

                                {showHeaders && renamingStorylineId === storyline.id && (
                                    <input
                                        ref={storylineRenameRef}
                                        type="text"
                                        defaultValue={storyline.name}
                                        className="mx-1 -mt-1 mb-1 rounded-[5px] border border-border bg-surface px-2 py-1 text-[11px] font-medium uppercase tracking-[0.08em] text-ink outline-none"
                                        onBlur={(e) => handleRenameStorylineSubmit(storyline, e.target.value)}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') handleRenameStorylineSubmit(storyline, e.currentTarget.value);
                                            if (e.key === 'Escape') setRenamingStorylineId(null);
                                        }}
                                    />
                                )}

                                {onAddChapter && (
                                    <button
                                        type="button"
                                        onClick={() => onAddChapter(storyline.id)}
                                        className="w-full rounded-[5px] px-2.5 py-1.5 text-left text-[13px] text-ink-faint hover:bg-ink/5"
                                    >
                                        + Add chapter
                                    </button>
                                )}
                            </SortableStorylineGroup>
                        ))}
                    </SortableContext>
                </div>

                <DragOverlay>
                    {activeItem?.type === 'chapter' && (
                        <div className="flex items-center gap-2 rounded-[5px] bg-surface-card px-2.5 py-1.5 text-[13px] leading-4 text-ink opacity-95 shadow-[0_4px_16px_#0000001F,0_0_0_1px_#0000000A]">
                            <span className="flex shrink-0 items-center text-ink-faint">
                                <svg width="6" height="10" viewBox="0 0 6 10" fill="currentColor">
                                    <circle cx="1" cy="1" r="1" />
                                    <circle cx="5" cy="1" r="1" />
                                    <circle cx="1" cy="5" r="1" />
                                    <circle cx="5" cy="5" r="1" />
                                    <circle cx="1" cy="9" r="1" />
                                    <circle cx="5" cy="9" r="1" />
                                </svg>
                            </span>
                            <span className={`inline-block size-[7px] shrink-0 rounded-full ${statusDot[activeItem.chapter.status]}`} />
                            <span className="min-w-0 flex-1 truncate">{activeItem.chapter.title}</span>
                            <span className="shrink-0 text-[11px] text-ink-faint">
                                {formatCompactCount(activeItem.chapter.word_count)}
                            </span>
                        </div>
                    )}
                    {activeItem?.type === 'storyline' && (
                        <div className="rounded-[5px] bg-surface-card px-2.5 py-1.5 text-[11px] font-medium uppercase tracking-[0.08em] text-ink-faint opacity-95 shadow-[0_4px_16px_#0000001F,0_0_0_1px_#0000000A]">
                            {activeItem.storyline.name}
                        </div>
                    )}
                </DragOverlay>
            </DndContext>

            {contextMenu?.type === 'chapter' && (
                <ChapterContextMenu
                    bookId={bookId}
                    chapter={contextMenu.chapter}
                    storylines={storylines}
                    position={contextMenu.position}
                    onClose={() => setContextMenu(null)}
                    onRename={() => handleRenameChapter(contextMenu.chapter.id)}
                    onDelete={() => setDialog({ type: 'deleteChapter', chapter: contextMenu.chapter })}
                />
            )}

            {contextMenu?.type === 'storyline' && (
                <StorylineContextMenu
                    bookId={bookId}
                    storyline={contextMenu.storyline}
                    isLastStoryline={storylines.length <= 1}
                    position={contextMenu.position}
                    onClose={() => setContextMenu(null)}
                    onRename={() => handleRenameStoryline(contextMenu.storyline.id)}
                    onDelete={() => setDialog({ type: 'deleteStoryline', storyline: contextMenu.storyline })}
                />
            )}

            {dialog?.type === 'deleteChapter' && (
                <DeleteChapterDialog bookId={bookId} chapter={dialog.chapter} onClose={() => setDialog(null)} />
            )}

            {dialog?.type === 'deleteStoryline' && (
                <DeleteStorylineDialog bookId={bookId} storyline={dialog.storyline} onClose={() => setDialog(null)} />
            )}
        </>
    );
}
