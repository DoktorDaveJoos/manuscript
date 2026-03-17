import {
    DndContext,
    DragOverlay,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors
    
    
    
} from '@dnd-kit/core';
import type {DragEndEvent, DragOverEvent, DragStartEvent} from '@dnd-kit/core';
import { SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { router } from '@inertiajs/react';
import { Book, ChevronDown, GripVertical, Plus } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { reorder as reorderChapters, updateTitle } from '@/actions/App/Http/Controllers/ChapterController';
import {
    destroy as destroyScene,
    reorder as reorderScenes,
    store as storeScene,
    updateTitle as updateSceneTitle,
} from '@/actions/App/Http/Controllers/SceneController';
import { reorder as reorderStorylines, update as updateStoryline } from '@/actions/App/Http/Controllers/StorylineController';
import { formatCompactCount, jsonFetchHeaders } from '@/lib/utils';
import type { Chapter, Scene, Storyline } from '@/types/models';
import ChapterContextMenu from './ChapterContextMenu';
import ChapterListItem from './ChapterListItem';
import DeleteChapterDialog from './DeleteChapterDialog';
import DeleteStorylineDialog from './DeleteStorylineDialog';
import SceneContextMenu from './SceneContextMenu';
import SceneListItem from './SceneListItem';
import StorylineContextMenu from './StorylineContextMenu';

const POINTER_SENSOR_OPTIONS = { activationConstraint: { distance: 5 } };

let savedCollapsedStorylineIds = new Set<number>();

type ContextMenuState =
    | { type: 'chapter'; chapter: Chapter; position: { x: number; y: number } }
    | { type: 'storyline'; storyline: Storyline; position: { x: number; y: number } }
    | { type: 'scene'; scene: Scene; chapterId: number; sceneCount: number; position: { x: number; y: number } }
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
    displayTitle,
    wordCount,
    onBeforeNavigate,
    onContextMenu,
}: {
    chapter: Chapter;
    bookId: number;
    index: number;
    isActive: boolean;
    displayTitle?: string;
    wordCount?: number;
    onBeforeNavigate?: () => Promise<void>;
    onContextMenu: (e: React.MouseEvent) => void;
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
                displayTitle={displayTitle}
                wordCount={wordCount}
                onBeforeNavigate={onBeforeNavigate}
                onContextMenu={onContextMenu}
                dragListeners={listeners}
                isDragging={isDragging}
            />
        </div>
    );
}

function SortableStorylineGroup({
    storyline,
    showHeader,
    isFirst,
    children,
    onContextMenu,
    isCollapsed,
    onToggleCollapse,
}: {
    storyline: Storyline;
    showHeader: boolean;
    isFirst: boolean;
    children: React.ReactNode;
    onContextMenu: (e: React.MouseEvent) => void;
    isCollapsed?: boolean;
    onToggleCollapse?: () => void;
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
        <div ref={setNodeRef} style={style} {...attributes} className={`flex flex-col gap-px ${isDragging ? 'opacity-50' : ''}`}>
            {showHeader && (
                <span
                    onContextMenu={onContextMenu}
                    className={`flex items-center justify-between px-2.5 pb-1 text-[10px] font-medium uppercase tracking-[0.08em] text-[#B5B5B5] ${isFirst ? 'pt-2.5' : 'pt-3.5'}`}
                >
                    <span {...listeners} className="flex cursor-grab items-center gap-1.5 active:cursor-grabbing">
                        {storyline.color && <span className="inline-block size-[6px] rounded-full" style={{ backgroundColor: storyline.color }} />}
                        {storyline.name}
                    </span>
                    <span
                        role="button"
                        tabIndex={-1}
                        onClick={(e) => {
                            e.stopPropagation();
                            onToggleCollapse?.();
                        }}
                        className={`flex items-center text-[#C5C5C5] transition-transform duration-150 ${isCollapsed ? '-rotate-90' : ''}`}
                    >
                        <ChevronDown size={10} />
                    </span>
                </span>
            )}
            <div
                className="grid transition-[grid-template-rows] duration-200 ease-out"
                style={{ gridTemplateRows: isCollapsed ? '0fr' : '1fr' }}
            >
                <div className="overflow-hidden">
                    {children}
                </div>
            </div>
        </div>
    );
}

function SortableSceneItem({
    scene,
    onClick,
    onContextMenu,
}: {
    scene: Scene;
    onClick: () => void;
    onContextMenu?: (e: React.MouseEvent) => void;
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: `scene-${scene.id}`,
        data: { type: 'scene', scene },
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    return (
        <div ref={setNodeRef} style={style} {...attributes} className={isDragging ? 'opacity-50' : ''}>
            <SceneListItem scene={scene} onClick={onClick} dragListeners={listeners} onContextMenu={onContextMenu} />
        </div>
    );
}

function SceneList({
    scenes: initialScenes,
    bookId,
    chapterId,
    onSceneContextMenu,
    renamingSceneId,
    sceneRenameRef,
    onRenameSceneSubmit,
    onCancelRename,
    onReorder,
}: {
    scenes: Scene[];
    bookId: number;
    chapterId: number;
    onSceneContextMenu?: (e: React.MouseEvent, scene: Scene) => void;
    renamingSceneId?: number | null;
    sceneRenameRef?: React.RefObject<HTMLInputElement | null>;
    onRenameSceneSubmit?: (scene: Scene, newTitle: string) => void;
    onCancelRename?: () => void;
    onReorder?: (orderedIds: number[]) => void;
}) {
    const [scenes, setScenes] = useState(initialScenes);
    const [activeScene, setActiveScene] = useState<Scene | null>(null);
    const sensors = useSensors(useSensor(PointerSensor, POINTER_SENSOR_OPTIONS));

    useEffect(() => {
        setScenes(initialScenes);
    }, [initialScenes]);

    const handleSceneDragStart = useCallback((event: DragStartEvent) => {
        const data = event.active.data.current;
        if (data?.type === 'scene') {
            setActiveScene(data.scene);
        }
    }, []);

    const handleSceneDragEnd = useCallback(
        async (event: DragEndEvent) => {
            setActiveScene(null);

            const { active, over } = event;
            if (!over || active.id === over.id) return;

            const oldIndex = scenes.findIndex((s) => `scene-${s.id}` === active.id);
            const newIndex = scenes.findIndex((s) => `scene-${s.id}` === over.id);
            if (oldIndex === -1 || newIndex === -1) return;

            const reordered = [...scenes];
            const [moved] = reordered.splice(oldIndex, 1);
            reordered.splice(newIndex, 0, moved);
            setScenes(reordered);

            await fetch(reorderScenes.url({ book: bookId, chapter: chapterId }), {
                method: 'POST',
                headers: jsonFetchHeaders(),
                body: JSON.stringify({ order: reordered.map((s) => s.id) }),
            });

            if (onReorder) {
                onReorder(reordered.map((s) => s.id));
            } else {
                router.reload({ only: ['book'] });
            }
        },
        [scenes, bookId, chapterId, onReorder],
    );

    return (
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragStart={handleSceneDragStart} onDragEnd={handleSceneDragEnd}>
            <SortableContext items={scenes.map((s) => `scene-${s.id}`)} strategy={verticalListSortingStrategy}>
                <div className="flex flex-col gap-px">
                    {scenes.map((scene) =>
                        renamingSceneId === scene.id ? (
                            <input
                                key={scene.id}
                                ref={sceneRenameRef}
                                type="text"
                                defaultValue={scene.title}
                                className="mx-1 rounded-[5px] border border-border bg-surface px-2 py-1 text-[12px] text-ink outline-none"
                                onBlur={(e) => onRenameSceneSubmit?.(scene, e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') onRenameSceneSubmit?.(scene, e.currentTarget.value);
                                    if (e.key === 'Escape') onCancelRename?.();
                                }}
                            />
                        ) : (
                            <SortableSceneItem
                                key={scene.id}
                                scene={scene}
                                onClick={() => {
                                    const el = document.getElementById(`scene-${scene.id}`);
                                    el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                }}
                                onContextMenu={onSceneContextMenu ? (e) => onSceneContextMenu(e, scene) : undefined}
                            />
                        ),
                    )}
                </div>
            </SortableContext>
            <DragOverlay>
                {activeScene && (
                    <div className="flex items-center gap-1.5 rounded-[5px] bg-surface-card px-2.5 py-1 text-[12px] opacity-95 shadow-[0_4px_16px_#0000001F,0_0_0_1px_#0000000A]">
                        <span className="flex shrink-0 items-center text-ink-faint">
                            <svg width="8" height="12" viewBox="0 0 8 12" fill="currentColor">
                                <circle cx="2" cy="2" r="1" />
                                <circle cx="6" cy="2" r="1" />
                                <circle cx="2" cy="6" r="1" />
                                <circle cx="6" cy="6" r="1" />
                                <circle cx="2" cy="10" r="1" />
                                <circle cx="6" cy="10" r="1" />
                            </svg>
                        </span>
                        <span className="min-w-0 flex-1 truncate text-ink">{activeScene.title}</span>
                        <span className="shrink-0 text-[11px] text-ink-faint">
                            {formatCompactCount(activeScene.word_count)}
                        </span>
                    </div>
                )}
            </DragOverlay>
        </DndContext>
    );
}

export default function ChapterList({
    bookTitle,
    storylines: initialStorylines,
    bookId,
    activeChapterId,
    activeChapterTitle,
    activeChapterWordCount,
    onBeforeNavigate,
    onAddChapter,
    onAddStoryline,
    activeScenes,
    onSceneRename,
    onSceneDelete,
    onSceneReorder,
    onSceneAdd,
    scenesVisible,
    onScenesVisibleChange,
}: {
    bookTitle: string;
    storylines: Storyline[];
    bookId: number;
    activeChapterId?: number;
    activeChapterTitle?: string;
    activeChapterWordCount?: number;
    onBeforeNavigate?: () => Promise<void>;
    onAddChapter?: (storylineId: number) => void;
    onAddStoryline?: () => void;
    activeScenes?: Scene[];
    onSceneRename?: (sceneId: number, newTitle: string) => void;
    onSceneDelete?: (sceneId: number) => void;
    onSceneReorder?: (orderedIds: number[]) => void;
    onSceneAdd?: (afterPosition: number) => Promise<void>;
    scenesVisible: boolean;
    onScenesVisibleChange: (v: boolean) => void;
}) {
    const { t } = useTranslation('editor');
    const [storylines, setStorylines] = useState(initialStorylines);
    const [activeItem, setActiveItem] = useState<{ type: 'chapter'; chapter: Chapter } | { type: 'storyline'; storyline: Storyline } | null>(null);
    const [contextMenu, setContextMenu] = useState<ContextMenuState>(null);
    const [dialog, setDialog] = useState<DialogState>(null);
    const [expandedChapterIds, setExpandedChapterIds] = useState<Set<number>>(new Set());
    const [collapsedStorylineIds, setCollapsedStorylineIds] = useState<Set<number>>(() => new Set(savedCollapsedStorylineIds));
    const [renamingChapterId, setRenamingChapterId] = useState<number | null>(null);
    const [renamingStorylineId, setRenamingStorylineId] = useState<number | null>(null);
    const [renamingSceneId, setRenamingSceneId] = useState<number | null>(null);
    const chapterRenameRef = useRef<HTMLInputElement>(null);
    const storylineRenameRef = useRef<HTMLInputElement>(null);
    const sceneRenameRef = useRef<HTMLInputElement>(null);
    const storylinesRef = useRef(initialStorylines);
    storylinesRef.current = initialStorylines;

    useEffect(() => {
        setStorylines(initialStorylines);
    }, [initialStorylines]);

    useEffect(() => {
        savedCollapsedStorylineIds = new Set(collapsedStorylineIds);
    }, [collapsedStorylineIds]);

    useEffect(() => {
        setExpandedChapterIds(activeChapterId ? new Set([activeChapterId]) : new Set());

        if (activeChapterId) {
            const parentStoryline = storylinesRef.current.find((s) =>
                s.chapters?.some((ch) => ch.id === activeChapterId),
            );
            if (parentStoryline) {
                setCollapsedStorylineIds((prev) => {
                    if (!prev.has(parentStoryline.id)) return prev;
                    const next = new Set(prev);
                    next.delete(parentStoryline.id);
                    return next;
                });
            }
        }
    }, [activeChapterId]);

    const toggleStorylineCollapse = useCallback((storylineId: number) => {
        setCollapsedStorylineIds((prev) => {
            const next = new Set(prev);
            if (next.has(storylineId)) next.delete(storylineId);
            else next.add(storylineId);
            return next;
        });
    }, []);

    const isAllCollapsed = useMemo(() => {
        const allStorylinesClosed = storylines.length > 1 && storylines.every((s) => collapsedStorylineIds.has(s.id));
        const noChaptersExpanded = expandedChapterIds.size === 0;
        return allStorylinesClosed && noChaptersExpanded;
    }, [storylines, collapsedStorylineIds, expandedChapterIds]);

    const handleToggleCollapseAll = useCallback(() => {
        if (isAllCollapsed) {
            setCollapsedStorylineIds(new Set());
            setExpandedChapterIds(activeChapterId ? new Set([activeChapterId]) : new Set());
        } else {
            setCollapsedStorylineIds(new Set(storylines.map((s) => s.id)));
            setExpandedChapterIds(new Set());
        }
    }, [isAllCollapsed, storylines, activeChapterId]);

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

    const handleSceneContextMenu = useCallback((e: React.MouseEvent, scene: Scene, chapterId: number, sceneCount: number) => {
        e.preventDefault();
        setContextMenu({ type: 'scene', scene, chapterId, sceneCount, position: { x: e.clientX, y: e.clientY } });
    }, []);

    const handleRenameScene = useCallback((sceneId: number) => {
        setRenamingSceneId(sceneId);
        setTimeout(() => sceneRenameRef.current?.focus(), 0);
    }, []);

    const handleRenameSceneSubmit = useCallback(
        async (scene: Scene, chapterId: number, newTitle: string) => {
            setRenamingSceneId(null);
            if (!newTitle.trim() || newTitle.trim() === scene.title) return;

            await fetch(updateSceneTitle.url({ book: bookId, chapter: chapterId, scene: scene.id }), {
                method: 'PATCH',
                headers: jsonFetchHeaders(),
                body: JSON.stringify({ title: newTitle.trim() }),
            });

            if (onSceneRename) {
                onSceneRename(scene.id, newTitle.trim());
            } else {
                router.reload({ only: ['book'] });
            }
        },
        [bookId, onSceneRename],
    );

    const handleDeleteScene = useCallback(
        async (chapterId: number, sceneId: number) => {
            await fetch(destroyScene.url({ book: bookId, chapter: chapterId, scene: sceneId }), {
                method: 'DELETE',
                headers: jsonFetchHeaders(),
            });

            if (onSceneDelete) {
                onSceneDelete(sceneId);
            } else {
                router.reload({ only: ['book'] });
            }
        },
        [bookId, onSceneDelete],
    );

    const handleAddScene = useCallback(
        async (chapterId: number, sceneCount: number) => {
            await fetch(storeScene.url({ book: bookId, chapter: chapterId }), {
                method: 'POST',
                headers: jsonFetchHeaders(),
                body: JSON.stringify({
                    title: t('chapterList.sceneDefault', { number: sceneCount + 1 }),
                    position: sceneCount,
                }),
            });
            router.reload({ only: ['book'] });
        },
        [bookId, t],
    );

    const showHeaders = storylines.length > 1;

    return (
        <>
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragStart={handleDragStart} onDragOver={handleDragOver} onDragEnd={handleDragEnd}>
                <div className="flex flex-col">
                    <div className="flex items-center justify-between px-2.5 py-2">
                        <div className="flex items-center gap-2">
                            <Book size={13} className="shrink-0 text-[#B0B0B0]" />
                            <span className="text-[10px] font-semibold uppercase tracking-[0.06em] text-[#8A8A8A]">
                                {bookTitle}
                            </span>
                        </div>
                        <div className="flex items-center gap-1">
                            <button
                                type="button"
                                onClick={handleToggleCollapseAll}
                                className="text-[#B5B5B5] transition-colors hover:text-ink"
                            >
                                <svg
                                    width="12"
                                    height="12"
                                    viewBox="0 0 14 14"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="1.2"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    className={`transition-transform duration-200 ${isAllCollapsed ? 'rotate-180' : ''}`}
                                >
                                    <path d="M3.5 6L7 3L10.5 6" />
                                    <path d="M3.5 10L7 7L10.5 10" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                onClick={() => onScenesVisibleChange(!scenesVisible)}
                                className="text-[#B5B5B5] transition-colors hover:text-ink"
                            >
                                {scenesVisible ? (
                                    <svg
                                        width="12"
                                        height="12"
                                        viewBox="0 0 14 14"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="1.2"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    >
                                        <path d="M1.5 7C1.5 7 3.5 3.5 7 3.5C10.5 3.5 12.5 7 12.5 7C12.5 7 10.5 10.5 7 10.5C3.5 10.5 1.5 7 1.5 7Z" />
                                        <circle cx="7" cy="7" r="1.5" />
                                    </svg>
                                ) : (
                                    <svg
                                        width="12"
                                        height="12"
                                        viewBox="0 0 14 14"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="1.2"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    >
                                        <path d="M1.5 7C1.5 7 3.5 3.5 7 3.5C10.5 3.5 12.5 7 12.5 7C12.5 7 10.5 10.5 7 10.5C3.5 10.5 1.5 7 1.5 7Z" />
                                        <circle cx="7" cy="7" r="1.5" />
                                        <path d="M3 11L11 3" />
                                    </svg>
                                )}
                            </button>
                        </div>
                    </div>
                    <SortableContext items={storylineIds} strategy={verticalListSortingStrategy}>
                        {storylines.map((storyline, i) => (
                            <SortableStorylineGroup
                                key={storyline.id}
                                storyline={storyline}
                                showHeader={showHeaders}
                                isFirst={i === 0}
                                onContextMenu={(e) => handleStorylineContextMenu(e, storyline)}
                                isCollapsed={collapsedStorylineIds.has(storyline.id)}
                                onToggleCollapse={() => toggleStorylineCollapse(storyline.id)}
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
                                                    defaultValue={chapter.id === activeChapterId && activeChapterTitle ? activeChapterTitle : chapter.title}
                                                    className="mx-1 rounded-[5px] border border-border bg-surface px-2 py-1.5 text-[13px] leading-4 text-ink outline-none"
                                                    onBlur={(e) => handleRenameChapterSubmit(chapter, e.target.value)}
                                                    onKeyDown={(e) => {
                                                        if (e.key === 'Enter') handleRenameChapterSubmit(chapter, e.currentTarget.value);
                                                        if (e.key === 'Escape') setRenamingChapterId(null);
                                                    }}
                                                />
                                            );
                                        }

                                        const isActiveChapter = chapter.id === activeChapterId;
                                        const liveScenes = isActiveChapter && activeScenes ? activeScenes : chapter.scenes;
                                        const hasScenes = (liveScenes?.length ?? 0) >= 1;
                                        const isExpanded = expandedChapterIds.has(chapter.id);
                                        const liveWordCount = isActiveChapter ? activeChapterWordCount : undefined;

                                        return (
                                            <div key={chapter.id}>
                                                <SortableChapterItem
                                                    chapter={chapter}
                                                    bookId={bookId}
                                                    index={index}
                                                    isActive={isActiveChapter}
                                                    displayTitle={isActiveChapter ? activeChapterTitle : undefined}
                                                    wordCount={liveWordCount}
                                                    onBeforeNavigate={onBeforeNavigate}
                                                    onContextMenu={(e) => handleChapterContextMenu(e, chapter)}
                                                />
                                                {scenesVisible && hasScenes && (
                                                    <div
                                                        className="grid transition-[grid-template-rows] duration-200 ease-out"
                                                        style={{ gridTemplateRows: isExpanded ? '1fr' : '0fr' }}
                                                    >
                                                        <div className="overflow-hidden">
                                                            <SceneList
                                                                scenes={liveScenes!}
                                                                bookId={bookId}
                                                                chapterId={chapter.id}
                                                                onSceneContextMenu={(e, scene) =>
                                                                    handleSceneContextMenu(e, scene, chapter.id, liveScenes!.length)
                                                                }
                                                                renamingSceneId={renamingSceneId}
                                                                sceneRenameRef={sceneRenameRef}
                                                                onRenameSceneSubmit={(scene, newTitle) =>
                                                                    handleRenameSceneSubmit(scene, chapter.id, newTitle)
                                                                }
                                                                onCancelRename={() => setRenamingSceneId(null)}
                                                                onReorder={isActiveChapter ? onSceneReorder : undefined}
                                                            />
                                                            {isActiveChapter && isExpanded && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => {
                                                                        if (onSceneAdd) {
                                                                            onSceneAdd(liveScenes!.length);
                                                                        } else {
                                                                            handleAddScene(chapter.id, liveScenes!.length);
                                                                        }
                                                                    }}
                                                                    className="w-full rounded-[5px] py-1 pl-[42px] pr-2.5 text-left text-[12px] text-ink-faint transition-colors hover:bg-ink/5"
                                                                >
                                                                    {t('chapterList.addScene')}
                                                                </button>
                                                            )}
                                                        </div>
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
                                        className="flex w-full items-center gap-1.5 rounded-[5px] px-2.5 py-[7px] text-ink-faint hover:bg-ink/5"
                                    >
                                        <Plus size={12} className="text-[#C5C5C5]" />
                                        <span className="text-[13px] text-[#B5B5B5]">{t('chapterList.addChapter')}</span>
                                    </button>
                                )}
                            </SortableStorylineGroup>
                        ))}
                    </SortableContext>
                    {onAddStoryline && (
                        <button
                            type="button"
                            onClick={onAddStoryline}
                            className="flex w-full items-center gap-1.5 px-2.5 pt-3.5 pb-1 text-[10px] font-medium uppercase tracking-[0.08em] text-[#B5B5B5] transition-colors hover:text-ink"
                        >
                            <Plus size={10} className="text-[#C5C5C5]" />
                            <span>{t('chapterList.addStoryline')}</span>
                        </button>
                    )}
                </div>

                <DragOverlay>
                    {activeItem?.type === 'chapter' && (
                        <div className="flex items-center gap-2 rounded-lg bg-surface-card px-2.5 py-[7px] text-[13px] leading-4 text-ink opacity-95 shadow-[0_4px_16px_#0000001F,0_0_0_1px_#0000000A]">
                            <span className="flex shrink-0 items-center text-[#D0D0D0]">
                                <GripVertical size={12} />
                            </span>
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

            {contextMenu?.type === 'scene' && (
                <SceneContextMenu
                    scene={contextMenu.scene}
                    canDelete={contextMenu.sceneCount > 1}
                    position={contextMenu.position}
                    onClose={() => setContextMenu(null)}
                    onRename={() => handleRenameScene(contextMenu.scene.id)}
                    onDelete={() => handleDeleteScene(contextMenu.chapterId, contextMenu.scene.id)}
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
