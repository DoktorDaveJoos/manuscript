import {
    DndContext,
    DragOverlay,
    KeyboardSensor,
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
import {
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { router, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    GripVertical,
    Plus,
    Settings2,
    UnfoldVertical,
    FoldVertical,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import {
    reorder as reorderChapters,
    updateTitle,
} from '@/actions/App/Http/Controllers/ChapterController';
import {
    destroy as destroyScene,
    reorder as reorderScenes,
    updateTitle as updateSceneTitle,
} from '@/actions/App/Http/Controllers/SceneController';
import {
    reorder as reorderStorylines,
    update as updateStoryline,
} from '@/actions/App/Http/Controllers/StorylineController';
import Button from '@/components/ui/Button';
import { Collapsible, CollapsibleTrigger } from '@/components/ui/Collapsible';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/DropdownMenu';
import SectionLabel from '@/components/ui/SectionLabel';
import { typedClosestCenter } from '@/lib/dnd';
import {
    addSceneToChapter,
    broadcastChapterDataChanged,
    ensureSuccessfulResponse,
    formatWordCount,
    jsonFetchHeaders,
    saveAppSetting,
} from '@/lib/utils';
import type { AppSettings, Chapter, Scene, Storyline } from '@/types/models';
import ChapterContextMenu from './ChapterContextMenu';
import ChapterListItem from './ChapterListItem';
import DeleteChapterDialog from './DeleteChapterDialog';
import DeleteStorylineDialog from './DeleteStorylineDialog';
import RenameDialog from './RenameDialog';
import SceneContextMenu from './SceneContextMenu';
import SceneListItem from './SceneListItem';
import StorylineContextMenu from './StorylineContextMenu';

const POINTER_SENSOR_OPTIONS = { activationConstraint: { distance: 5 } };

const COLLAPSED_STORYLINES_KEY = 'collapsedStorylineIds';

function loadCollapsedStorylineIds(): Set<number> {
    try {
        const raw = localStorage.getItem(COLLAPSED_STORYLINES_KEY);
        if (raw) {
            return new Set(JSON.parse(raw) as number[]);
        }
    } catch {
        // ignore corrupt data
    }
    return new Set();
}

function orderSignature(storylines: Storyline[]): string {
    return storylines
        .flatMap((storyline) => [
            `storyline:${storyline.id}`,
            ...(storyline.chapters ?? []).map(
                (chapter) => `chapter:${chapter.id}:${storyline.id}`,
            ),
        ])
        .join('|');
}

let savedCollapsedStorylineIds = loadCollapsedStorylineIds();

type ContextMenuState =
    | { type: 'chapter'; chapter: Chapter; position: { x: number; y: number } }
    | {
          type: 'storyline';
          storyline: Storyline;
          position: { x: number; y: number };
      }
    | {
          type: 'scene';
          scene: Scene;
          chapterId: number;
          sceneCount: number;
          position: { x: number; y: number };
      }
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
    showStatusBubble,
    showWordCount,
    compactWordCount,
    onBeforeNavigate,
    onChapterNavigate,
    onOpenInNewPane,
    onContextMenu,
    isInCollapsedStoryline,
}: {
    chapter: Chapter;
    bookId: number;
    index: number;
    isActive: boolean;
    displayTitle?: string;
    wordCount?: number;
    showStatusBubble?: boolean;
    showWordCount?: boolean;
    compactWordCount?: boolean;
    onBeforeNavigate?: () => Promise<void>;
    onChapterNavigate?: (chapterId: number) => void;
    onOpenInNewPane?: (chapterId: number) => void;
    onContextMenu: (e: React.MouseEvent) => void;
    isInCollapsedStoryline?: boolean;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
        isOver,
    } = useSortable({
        id: `chapter-${chapter.id}`,
        data: { type: 'chapter', chapter },
        disabled: isInCollapsedStoryline
            ? { draggable: true, droppable: true }
            : false,
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    return (
        <div ref={setNodeRef} style={style} className="relative">
            {isOver && (
                <div className="absolute -top-px right-2.5 left-2.5 h-0.5 rounded bg-drop" />
            )}
            <ChapterListItem
                chapter={chapter}
                bookId={bookId}
                index={index}
                isActive={isActive}
                displayTitle={displayTitle}
                wordCount={wordCount}
                showStatusBubble={showStatusBubble}
                showWordCount={showWordCount}
                compactWordCount={compactWordCount}
                onBeforeNavigate={onBeforeNavigate}
                onChapterNavigate={onChapterNavigate}
                onOpenInNewPane={onOpenInNewPane}
                onContextMenu={onContextMenu}
                dragAttributes={attributes}
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
    chapterCount,
}: {
    storyline: Storyline;
    showHeader: boolean;
    isFirst: boolean;
    children: React.ReactNode;
    onContextMenu: (e: React.MouseEvent) => void;
    isCollapsed?: boolean;
    onToggleCollapse?: () => void;
    chapterCount: number;
}) {
    const { t } = useTranslation('editor');
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({
        id: `storyline-${storyline.id}`,
        data: { type: 'storyline', storyline },
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    return (
        <Collapsible
            asChild
            open={!isCollapsed}
            onOpenChange={onToggleCollapse}
        >
            <div
                ref={setNodeRef}
                style={style}
                data-storyline-section={storyline.id}
                className={`flex flex-col gap-px ${isDragging ? 'opacity-50' : ''}`}
            >
                {showHeader && (
                    <span
                        onContextMenu={onContextMenu}
                        className={`flex items-center justify-between gap-2 px-2.5 py-[7px] ${isFirst ? '' : 'mt-1'}`}
                    >
                        <span className="flex min-w-0 flex-1 items-center gap-1.5">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={t('drag.storyline')}
                                {...attributes}
                                {...listeners}
                                className="size-6 shrink-0 cursor-grab bg-transparent p-0 text-ink-faint hover:bg-transparent active:cursor-grabbing"
                            >
                                <GripVertical size={12} />
                            </Button>
                            <span
                                className={`size-1.5 shrink-0 rounded-full ${storyline.color ? '' : 'bg-ink-faint'}`}
                                style={
                                    storyline.color
                                        ? { backgroundColor: storyline.color }
                                        : undefined
                                }
                            />
                            <CollapsibleTrigger
                                data-storyline-header
                                className="min-w-0 flex-1 cursor-pointer text-left"
                            >
                                <SectionLabel className="block truncate">
                                    {storyline.name}
                                </SectionLabel>
                            </CollapsibleTrigger>
                        </span>
                        <CollapsibleTrigger className="flex shrink-0 items-center gap-1 text-ink-faint">
                            <span className="text-[11px]">{chapterCount}</span>
                            <ChevronDown
                                size={12}
                                className={`transition-transform duration-150 ${isCollapsed ? '-rotate-90' : ''}`}
                            />
                        </CollapsibleTrigger>
                    </span>
                )}
                <div
                    className="grid transition-[grid-template-rows] duration-200 ease-out"
                    style={{
                        gridTemplateRows: isCollapsed ? '0fr' : '1fr',
                    }}
                >
                    <div className="overflow-hidden">{children}</div>
                </div>
            </div>
        </Collapsible>
    );
}

function SortableSceneItem({
    scene,
    onClick,
    onContextMenu,
    showWordCount,
    compactWordCount,
}: {
    scene: Scene;
    onClick: () => void;
    onContextMenu?: (e: React.MouseEvent) => void;
    showWordCount?: boolean;
    compactWordCount?: boolean;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({
        id: `scene-${scene.id}`,
        data: { type: 'scene', scene },
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={isDragging ? 'opacity-50' : ''}
        >
            <SceneListItem
                scene={scene}
                onClick={onClick}
                dragAttributes={attributes}
                dragListeners={listeners}
                onContextMenu={onContextMenu}
                showWordCount={showWordCount}
                compactWordCount={compactWordCount}
            />
        </div>
    );
}

function SceneList({
    scenes: initialScenes,
    bookId,
    chapterId,
    onSceneContextMenu,
    onReorder,
    showWordCount,
    compactWordCount,
}: {
    scenes: Scene[];
    bookId: number;
    chapterId: number;
    onSceneContextMenu?: (e: React.MouseEvent, scene: Scene) => void;
    onReorder?: (orderedIds: number[]) => void;
    showWordCount?: boolean;
    compactWordCount?: boolean;
}) {
    const { t } = useTranslation('editor');
    const [scenes, setScenes] = useState(initialScenes);
    const [activeScene, setActiveScene] = useState<Scene | null>(null);
    const sensors = useSensors(
        useSensor(PointerSensor, POINTER_SENSOR_OPTIONS),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

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

            const oldIndex = scenes.findIndex(
                (s) => `scene-${s.id}` === active.id,
            );
            const newIndex = scenes.findIndex(
                (s) => `scene-${s.id}` === over.id,
            );
            if (oldIndex === -1 || newIndex === -1) return;

            const previousScenes = scenes;
            const reordered = [...previousScenes];
            const [moved] = reordered.splice(oldIndex, 1);
            reordered.splice(newIndex, 0, moved);
            setScenes(reordered);

            try {
                const response = await fetch(
                    reorderScenes.url({ book: bookId, chapter: chapterId }),
                    {
                        method: 'POST',
                        headers: jsonFetchHeaders(),
                        body: JSON.stringify({
                            order: reordered.map((s) => s.id),
                        }),
                    },
                );
                await ensureSuccessfulResponse(response, t('request.failed'));

                broadcastChapterDataChanged(chapterId);
                onReorder?.(reordered.map((s) => s.id));
            } catch (error) {
                setScenes(previousScenes);
                toast.error(
                    error instanceof Error
                        ? error.message
                        : t('request.failed'),
                );
            }
        },
        [scenes, bookId, chapterId, onReorder, t],
    );

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragStart={handleSceneDragStart}
            onDragEnd={handleSceneDragEnd}
        >
            <SortableContext
                items={scenes.map((s) => `scene-${s.id}`)}
                strategy={verticalListSortingStrategy}
            >
                <div className="flex flex-col gap-px">
                    {scenes.map((scene) => (
                        <SortableSceneItem
                            key={scene.id}
                            scene={scene}
                            showWordCount={showWordCount}
                            compactWordCount={compactWordCount}
                            onClick={() => {
                                const el = document.getElementById(
                                    `scene-${scene.id}`,
                                );
                                el?.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'start',
                                });
                            }}
                            onContextMenu={
                                onSceneContextMenu
                                    ? (e) => onSceneContextMenu(e, scene)
                                    : undefined
                            }
                        />
                    ))}
                </div>
            </SortableContext>
            <DragOverlay>
                {activeScene && (
                    <div className="flex items-center gap-1.5 rounded-md bg-surface-card px-2.5 py-1 text-xs opacity-95 shadow-lg">
                        <span className="flex shrink-0 items-center text-ink-faint">
                            <svg
                                width="8"
                                height="12"
                                viewBox="0 0 8 12"
                                fill="currentColor"
                            >
                                <circle cx="2" cy="2" r="1" />
                                <circle cx="6" cy="2" r="1" />
                                <circle cx="2" cy="6" r="1" />
                                <circle cx="6" cy="6" r="1" />
                                <circle cx="2" cy="10" r="1" />
                                <circle cx="6" cy="10" r="1" />
                            </svg>
                        </span>
                        <span className="min-w-0 flex-1 truncate text-ink">
                            {activeScene.title}
                        </span>
                        {showWordCount && (
                            <span className="shrink-0 text-[11px] text-ink-faint">
                                {formatWordCount(
                                    activeScene.word_count,
                                    compactWordCount ?? true,
                                )}
                            </span>
                        )}
                    </div>
                )}
            </DragOverlay>
        </DndContext>
    );
}

export default function ChapterList({
    storylines: initialStorylines,
    bookId,
    activeChapterId,
    activeChapterTitle,
    activeChapterWordCount,
    onBeforeNavigate,
    onChapterNavigate,
    onOpenInNewPane,
    onAddChapter,
    onAddStoryline,
    activeScenes,
    onChapterRename,
    onSceneRename,
    onSceneDelete,
    onSceneReorder,
    onSceneAdd,
    scenesVisible,
    onScenesVisibleChange,
    scrollContainerRef,
    onScroll,
}: {
    storylines: Storyline[];
    bookId: number;
    activeChapterId?: number;
    activeChapterTitle?: string;
    activeChapterWordCount?: number;
    onBeforeNavigate?: () => Promise<void>;
    onChapterNavigate?: (chapterId: number) => void;
    onOpenInNewPane?: (chapterId: number) => void;
    onAddChapter?: (storylineId: number) => void;
    onAddStoryline?: () => void;
    activeScenes?: Scene[];
    onChapterRename?: (chapterId: number, newTitle: string) => void;
    onSceneRename?: (sceneId: number, newTitle: string) => void;
    onSceneDelete?: (sceneId: number) => void;
    onSceneReorder?: (orderedIds: number[]) => void;
    onSceneAdd?: (afterPosition: number) => Promise<void>;
    scenesVisible: boolean;
    onScenesVisibleChange: (v: boolean) => void;
    scrollContainerRef?: React.RefObject<HTMLDivElement | null>;
    onScroll?: () => void;
}) {
    const { t } = useTranslation('editor');
    const { app_settings } = usePage<{ app_settings: AppSettings }>().props;
    const [displaySettings, setDisplaySettings] = useState(() => ({
        show_status_bubbles: app_settings.show_status_bubbles,
        show_word_count: app_settings.show_word_count,
        compact_word_count: app_settings.compact_word_count,
    }));
    const toggleDisplaySetting = useCallback(
        (key: keyof typeof displaySettings) => {
            const value = !displaySettings[key];
            setDisplaySettings({ ...displaySettings, [key]: value });
            void (async () => {
                try {
                    const response = await saveAppSetting(key, value);
                    await ensureSuccessfulResponse(
                        response,
                        t('request.failed'),
                    );
                } catch (error) {
                    setDisplaySettings((current) => ({
                        ...current,
                        [key]: !value,
                    }));
                    toast.error(
                        error instanceof Error
                            ? error.message
                            : t('request.failed'),
                    );
                }
            })();
        },
        [displaySettings, t],
    );
    const [storylines, setStorylines] = useState(initialStorylines);
    const [activeItem, setActiveItem] = useState<
        | { type: 'chapter'; chapter: Chapter }
        | { type: 'storyline'; storyline: Storyline }
        | null
    >(null);
    const [contextMenu, setContextMenu] = useState<ContextMenuState>(null);
    const [dialog, setDialog] = useState<DialogState>(null);
    const [expandedChapterIds, setExpandedChapterIds] = useState<Set<number>>(
        new Set(),
    );
    const [collapsedStorylineIds, setCollapsedStorylineIds] = useState<
        Set<number>
    >(() => new Set(savedCollapsedStorylineIds));
    const [renaming, setRenaming] = useState<
        | { type: 'chapter'; chapter: Chapter }
        | { type: 'storyline'; storyline: Storyline }
        | { type: 'scene'; scene: Scene; chapterId: number }
        | null
    >(null);
    const storylinesRef = useRef(storylines);
    const dragSnapshotRef = useRef<Storyline[] | null>(null);
    const prevActiveChapterIdRef = useRef(activeChapterId);

    useEffect(() => {
        setStorylines(initialStorylines);
        storylinesRef.current = initialStorylines;
    }, [initialStorylines]);

    useEffect(() => {
        storylinesRef.current = storylines;
    }, [storylines]);

    useEffect(() => {
        savedCollapsedStorylineIds = new Set(collapsedStorylineIds);
        localStorage.setItem(
            COLLAPSED_STORYLINES_KEY,
            JSON.stringify([...collapsedStorylineIds]),
        );
    }, [collapsedStorylineIds]);

    useEffect(() => {
        setExpandedChapterIds(
            activeChapterId ? new Set([activeChapterId]) : new Set(),
        );

        // Only auto-expand the storyline when the active chapter actually changes,
        // not on initial mount where we want to respect the persisted state.
        if (
            activeChapterId &&
            activeChapterId !== prevActiveChapterIdRef.current
        ) {
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
        prevActiveChapterIdRef.current = activeChapterId;
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
        const allStorylinesClosed =
            storylines.length > 1 &&
            storylines.every((s) => collapsedStorylineIds.has(s.id));
        const noChaptersExpanded = expandedChapterIds.size === 0;
        return allStorylinesClosed && noChaptersExpanded;
    }, [storylines, collapsedStorylineIds, expandedChapterIds]);

    const handleToggleCollapseAll = useCallback(() => {
        if (isAllCollapsed) {
            setCollapsedStorylineIds(new Set());
            setExpandedChapterIds(
                activeChapterId ? new Set([activeChapterId]) : new Set(),
            );
        } else {
            setCollapsedStorylineIds(new Set(storylines.map((s) => s.id)));
            setExpandedChapterIds(new Set());
        }
    }, [isAllCollapsed, storylines, activeChapterId]);

    const sensors = useSensors(
        useSensor(PointerSensor, POINTER_SENSOR_OPTIONS),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    let chapterIndex = 1;

    const storylineIds = useMemo(
        () => storylines.map((s) => `storyline-${s.id}`),
        [storylines],
    );

    const handleDragStart = useCallback((event: DragStartEvent) => {
        dragSnapshotRef.current = storylinesRef.current;
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
                const newStorylines = prev.map((s) => ({
                    ...s,
                    chapters: [...(s.chapters ?? [])],
                }));

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

                if (sourceStorylineIdx === -1 || destStorylineIdx === -1)
                    return prev;

                const [moved] = newStorylines[
                    sourceStorylineIdx
                ].chapters!.splice(sourceIdx, 1);
                newStorylines[destStorylineIdx].chapters!.splice(
                    destIdx,
                    0,
                    moved,
                );

                return newStorylines;
            });
        }
    }, []);

    const handleDragEnd = useCallback(
        async (event: DragEndEvent) => {
            setActiveItem(null);

            const { active, over } = event;
            const activeData = active.data.current;
            const snapshot = dragSnapshotRef.current ?? storylinesRef.current;
            dragSnapshotRef.current = null;

            if (activeData?.type === 'storyline') {
                if (!over || active.id === over.id) return;

                const oldIndex = storylines.findIndex(
                    (s) => `storyline-${s.id}` === active.id,
                );
                const newIndex = storylines.findIndex(
                    (s) => `storyline-${s.id}` === over.id,
                );

                if (oldIndex === -1 || newIndex === -1 || oldIndex === newIndex)
                    return;

                const reordered = [...storylines];
                const [moved] = reordered.splice(oldIndex, 1);
                reordered.splice(newIndex, 0, moved);
                setStorylines(reordered);

                try {
                    const response = await fetch(
                        reorderStorylines.url(bookId),
                        {
                            method: 'POST',
                            headers: jsonFetchHeaders(),
                            body: JSON.stringify({
                                order: reordered.map((s) => s.id),
                            }),
                        },
                    );
                    await ensureSuccessfulResponse(
                        response,
                        t('request.failed'),
                    );
                    storylinesRef.current = reordered;
                    router.reload({ only: ['book', 'sidebar_storylines'] });
                } catch (error) {
                    setStorylines(snapshot);
                    storylinesRef.current = snapshot;
                    toast.error(
                        error instanceof Error
                            ? error.message
                            : t('request.failed'),
                    );
                }
                return;
            }

            if (activeData?.type === 'chapter') {
                // handleDragOver already did the optimistic reorder in state.
                // Skip if nothing changed (no drag-over occurred).
                if (orderSignature(storylines) === orderSignature(snapshot)) {
                    return;
                }

                const order = storylines.flatMap((s) =>
                    (s.chapters ?? []).map((ch) => ({
                        id: ch.id,
                        storyline_id: s.id,
                    })),
                );

                try {
                    const response = await fetch(reorderChapters.url(bookId), {
                        method: 'POST',
                        headers: jsonFetchHeaders(),
                        body: JSON.stringify({ order }),
                    });
                    await ensureSuccessfulResponse(
                        response,
                        t('request.failed'),
                    );
                    storylinesRef.current = storylines;
                    router.reload({ only: ['book', 'sidebar_storylines'] });
                } catch (error) {
                    setStorylines(snapshot);
                    storylinesRef.current = snapshot;
                    toast.error(
                        error instanceof Error
                            ? error.message
                            : t('request.failed'),
                    );
                }
            }
        },
        [storylines, bookId, t],
    );

    const handleDragCancel = useCallback(() => {
        setActiveItem(null);
        const snapshot = dragSnapshotRef.current;
        dragSnapshotRef.current = null;
        if (snapshot) {
            setStorylines(snapshot);
            storylinesRef.current = snapshot;
        }
    }, []);

    const handleChapterContextMenu = useCallback(
        (e: React.MouseEvent, chapter: Chapter) => {
            e.preventDefault();
            setContextMenu({
                type: 'chapter',
                chapter,
                position: { x: e.clientX, y: e.clientY },
            });
        },
        [],
    );

    const handleStorylineContextMenu = useCallback(
        (e: React.MouseEvent, storyline: Storyline) => {
            e.preventDefault();
            setContextMenu({
                type: 'storyline',
                storyline,
                position: { x: e.clientX, y: e.clientY },
            });
        },
        [],
    );

    const handleRenameSubmit = useCallback(
        async (newValue: string) => {
            if (!renaming) return;
            const previousStorylines = storylinesRef.current;
            const persist = async (url: string, options: RequestInit) => {
                const response = await fetch(url, options);
                await ensureSuccessfulResponse(response, t('request.failed'));
            };

            try {
                switch (renaming.type) {
                    case 'chapter':
                        setStorylines((prev) =>
                            prev.map((s) => ({
                                ...s,
                                chapters: s.chapters?.map((ch) =>
                                    ch.id === renaming.chapter.id
                                        ? { ...ch, title: newValue }
                                        : ch,
                                ),
                            })),
                        );
                        await persist(
                            updateTitle.url({
                                book: bookId,
                                chapter: renaming.chapter.id,
                            }),
                            {
                                method: 'PATCH',
                                headers: jsonFetchHeaders(),
                                body: JSON.stringify({ title: newValue }),
                            },
                        );
                        onChapterRename?.(renaming.chapter.id, newValue);
                        broadcastChapterDataChanged(renaming.chapter.id);
                        router.reload({
                            only: ['book', 'sidebar_storylines'],
                        });
                        break;
                    case 'storyline':
                        setStorylines((prev) =>
                            prev.map((s) =>
                                s.id === renaming.storyline.id
                                    ? { ...s, name: newValue }
                                    : s,
                            ),
                        );
                        await persist(
                            updateStoryline.url({
                                book: bookId,
                                storyline: renaming.storyline.id,
                            }),
                            {
                                method: 'PATCH',
                                headers: jsonFetchHeaders(),
                                body: JSON.stringify({
                                    name: newValue,
                                    color: renaming.storyline.color,
                                }),
                            },
                        );
                        router.reload({
                            only: ['book', 'sidebar_storylines'],
                        });
                        break;
                    case 'scene':
                        setStorylines((prev) =>
                            prev.map((s) => ({
                                ...s,
                                chapters: s.chapters?.map((ch) =>
                                    ch.id === renaming.chapterId
                                        ? {
                                              ...ch,
                                              scenes: ch.scenes?.map((sc) =>
                                                  sc.id === renaming.scene.id
                                                      ? {
                                                            ...sc,
                                                            title: newValue,
                                                        }
                                                      : sc,
                                              ),
                                          }
                                        : ch,
                                ),
                            })),
                        );
                        await persist(
                            updateSceneTitle.url({
                                book: bookId,
                                chapter: renaming.chapterId,
                                scene: renaming.scene.id,
                            }),
                            {
                                method: 'PATCH',
                                headers: jsonFetchHeaders(),
                                body: JSON.stringify({ title: newValue }),
                            },
                        );
                        broadcastChapterDataChanged(renaming.chapterId);
                        onSceneRename?.(renaming.scene.id, newValue);
                        break;
                }
            } catch (error) {
                setStorylines(previousStorylines);
                storylinesRef.current = previousStorylines;
                toast.error(
                    error instanceof Error
                        ? error.message
                        : t('request.failed'),
                );
            }
        },
        [renaming, bookId, onChapterRename, onSceneRename, t],
    );

    const handleSceneContextMenu = useCallback(
        (
            e: React.MouseEvent,
            scene: Scene,
            chapterId: number,
            sceneCount: number,
        ) => {
            e.preventDefault();
            setContextMenu({
                type: 'scene',
                scene,
                chapterId,
                sceneCount,
                position: { x: e.clientX, y: e.clientY },
            });
        },
        [],
    );

    const handleDeleteScene = useCallback(
        async (chapterId: number, sceneId: number) => {
            try {
                const response = await fetch(
                    destroyScene.url({
                        book: bookId,
                        chapter: chapterId,
                        scene: sceneId,
                    }),
                    {
                        method: 'DELETE',
                        headers: jsonFetchHeaders(),
                    },
                );
                await ensureSuccessfulResponse(response, t('request.failed'));

                broadcastChapterDataChanged(chapterId);
                onSceneDelete?.(sceneId);
            } catch (error) {
                toast.error(
                    error instanceof Error
                        ? error.message
                        : t('request.failed'),
                );
            }
        },
        [bookId, onSceneDelete, t],
    );

    const handleAddScene = useCallback(
        async (chapterId: number, sceneCount: number) => {
            try {
                await addSceneToChapter(bookId, chapterId, sceneCount, t);
            } catch (error) {
                toast.error(
                    error instanceof Error
                        ? error.message
                        : t('request.failed'),
                );
            }
        },
        [bookId, t],
    );

    const renamingProps = useMemo(() => {
        if (!renaming) return null;
        const titleKey =
            renaming.type === 'chapter'
                ? 'renameDialog.chapter'
                : renaming.type === 'storyline'
                  ? 'renameDialog.storyline'
                  : 'renameDialog.scene';
        const labelKey =
            renaming.type === 'storyline'
                ? 'renameDialog.labelName'
                : 'renameDialog.labelTitle';
        let value: string;
        switch (renaming.type) {
            case 'chapter':
                value =
                    (renaming.chapter.id === activeChapterId &&
                        activeChapterTitle) ||
                    renaming.chapter.title;
                break;
            case 'storyline':
                value = renaming.storyline.name;
                break;
            case 'scene':
                value = renaming.scene.title;
                break;
        }
        return { titleKey, labelKey, value };
    }, [renaming, activeChapterId, activeChapterTitle]);

    const showHeaders = true;

    return (
        <>
            <DndContext
                sensors={sensors}
                collisionDetection={typedClosestCenter}
                onDragStart={handleDragStart}
                onDragOver={handleDragOver}
                onDragEnd={handleDragEnd}
                onDragCancel={handleDragCancel}
            >
                <div className="flex min-h-0 flex-1 flex-col">
                    <div className="flex items-center justify-end bg-neutral-bg px-4 py-0.5">
                        <div className="flex items-center gap-2">
                            <span className="group relative">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t(
                                        isAllCollapsed
                                            ? 'chapterList.expandAll'
                                            : 'chapterList.collapseAll',
                                    )}
                                    onClick={handleToggleCollapseAll}
                                    className="size-6 text-ink-faint transition-colors hover:text-ink"
                                >
                                    {isAllCollapsed ? (
                                        <UnfoldVertical size={12} />
                                    ) : (
                                        <FoldVertical size={12} />
                                    )}
                                </Button>
                                <span className="pointer-events-none absolute top-full right-0 z-50 mt-1.5 rounded bg-ink px-2 py-1 text-[11px] whitespace-nowrap text-surface opacity-0 shadow-sm transition-opacity group-hover:opacity-100">
                                    {t(
                                        isAllCollapsed
                                            ? 'chapterList.expandAll'
                                            : 'chapterList.collapseAll',
                                    )}
                                </span>
                            </span>
                            <div className="h-3 w-px bg-border" />
                            <DropdownMenu>
                                <DropdownMenuTrigger
                                    data-testid="chapter-list-display-options"
                                    title={t('chapterList.displayOptions')}
                                    className="flex items-center text-ink-faint transition-colors hover:text-ink"
                                >
                                    <Settings2 size={12} />
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuGroup>
                                        <DropdownMenuCheckboxItem
                                            data-testid="display-toggle-status-bubbles"
                                            checked={
                                                displaySettings.show_status_bubbles
                                            }
                                            onSelect={(e) => e.preventDefault()}
                                            onCheckedChange={() =>
                                                toggleDisplaySetting(
                                                    'show_status_bubbles',
                                                )
                                            }
                                        >
                                            {t('chapterList.showStatusBubbles')}
                                        </DropdownMenuCheckboxItem>
                                        <DropdownMenuCheckboxItem
                                            data-testid="display-toggle-word-count"
                                            checked={
                                                displaySettings.show_word_count
                                            }
                                            onSelect={(e) => e.preventDefault()}
                                            onCheckedChange={() =>
                                                toggleDisplaySetting(
                                                    'show_word_count',
                                                )
                                            }
                                        >
                                            {t('chapterList.showWordCount')}
                                        </DropdownMenuCheckboxItem>
                                        <DropdownMenuCheckboxItem
                                            data-testid="display-toggle-scenes"
                                            checked={scenesVisible}
                                            onSelect={(e) => e.preventDefault()}
                                            onCheckedChange={() =>
                                                onScenesVisibleChange(
                                                    !scenesVisible,
                                                )
                                            }
                                        >
                                            {t('chapterList.showScenes')}
                                        </DropdownMenuCheckboxItem>
                                    </DropdownMenuGroup>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuGroup>
                                        <DropdownMenuCheckboxItem
                                            data-testid="display-toggle-compact-word-count"
                                            checked={
                                                displaySettings.compact_word_count
                                            }
                                            disabled={
                                                !displaySettings.show_word_count
                                            }
                                            onSelect={(e) => e.preventDefault()}
                                            onCheckedChange={() =>
                                                toggleDisplaySetting(
                                                    'compact_word_count',
                                                )
                                            }
                                        >
                                            {t('chapterList.compactWordCount')}
                                        </DropdownMenuCheckboxItem>
                                    </DropdownMenuGroup>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                    <div
                        ref={scrollContainerRef}
                        onScroll={onScroll}
                        className="min-h-0 flex-1 overflow-y-auto px-1.5 pb-2"
                    >
                        <SortableContext
                            items={storylineIds}
                            strategy={verticalListSortingStrategy}
                        >
                            {storylines.map((storyline, i) => (
                                <SortableStorylineGroup
                                    key={storyline.id}
                                    storyline={storyline}
                                    showHeader={showHeaders}
                                    isFirst={i === 0}
                                    onContextMenu={(e) =>
                                        handleStorylineContextMenu(e, storyline)
                                    }
                                    isCollapsed={collapsedStorylineIds.has(
                                        storyline.id,
                                    )}
                                    onToggleCollapse={() =>
                                        toggleStorylineCollapse(storyline.id)
                                    }
                                    chapterCount={
                                        storyline.chapters?.length ?? 0
                                    }
                                >
                                    <SortableContext
                                        items={(storyline.chapters ?? []).map(
                                            (ch) => `chapter-${ch.id}`,
                                        )}
                                        strategy={verticalListSortingStrategy}
                                    >
                                        {storyline.chapters?.map((chapter) => {
                                            const index = chapterIndex++;

                                            const isActiveChapter =
                                                chapter.id === activeChapterId;
                                            const liveScenes =
                                                isActiveChapter && activeScenes
                                                    ? activeScenes
                                                    : chapter.scenes;
                                            const hasScenes =
                                                (liveScenes?.length ?? 0) >= 1;
                                            const isExpanded =
                                                expandedChapterIds.has(
                                                    chapter.id,
                                                );
                                            const liveWordCount =
                                                isActiveChapter
                                                    ? activeChapterWordCount
                                                    : undefined;

                                            return (
                                                <div key={chapter.id}>
                                                    <SortableChapterItem
                                                        chapter={chapter}
                                                        bookId={bookId}
                                                        index={index}
                                                        isActive={
                                                            isActiveChapter
                                                        }
                                                        displayTitle={
                                                            isActiveChapter
                                                                ? activeChapterTitle
                                                                : undefined
                                                        }
                                                        wordCount={
                                                            liveWordCount
                                                        }
                                                        showStatusBubble={
                                                            displaySettings.show_status_bubbles
                                                        }
                                                        showWordCount={
                                                            displaySettings.show_word_count
                                                        }
                                                        compactWordCount={
                                                            displaySettings.compact_word_count
                                                        }
                                                        isInCollapsedStoryline={
                                                            showHeaders &&
                                                            collapsedStorylineIds.has(
                                                                storyline.id,
                                                            )
                                                        }
                                                        onBeforeNavigate={
                                                            onBeforeNavigate
                                                        }
                                                        onChapterNavigate={
                                                            onChapterNavigate
                                                        }
                                                        onOpenInNewPane={
                                                            onOpenInNewPane
                                                        }
                                                        onContextMenu={(e) =>
                                                            handleChapterContextMenu(
                                                                e,
                                                                chapter,
                                                            )
                                                        }
                                                    />
                                                    {scenesVisible &&
                                                        hasScenes && (
                                                            <div
                                                                className="grid transition-[grid-template-rows] duration-200 ease-out"
                                                                style={{
                                                                    gridTemplateRows:
                                                                        isExpanded
                                                                            ? '1fr'
                                                                            : '0fr',
                                                                }}
                                                            >
                                                                <div className="overflow-hidden">
                                                                    <SceneList
                                                                        scenes={
                                                                            liveScenes!
                                                                        }
                                                                        showWordCount={
                                                                            displaySettings.show_word_count
                                                                        }
                                                                        compactWordCount={
                                                                            displaySettings.compact_word_count
                                                                        }
                                                                        bookId={
                                                                            bookId
                                                                        }
                                                                        chapterId={
                                                                            chapter.id
                                                                        }
                                                                        onSceneContextMenu={(
                                                                            e,
                                                                            scene,
                                                                        ) =>
                                                                            handleSceneContextMenu(
                                                                                e,
                                                                                scene,
                                                                                chapter.id,
                                                                                liveScenes!
                                                                                    .length,
                                                                            )
                                                                        }
                                                                        onReorder={
                                                                            isActiveChapter
                                                                                ? onSceneReorder
                                                                                : undefined
                                                                        }
                                                                    />
                                                                    {isActiveChapter &&
                                                                        isExpanded && (
                                                                            <Button
                                                                                type="button"
                                                                                variant="ghost"
                                                                                data-testid="add-scene-button"
                                                                                onClick={() => {
                                                                                    if (
                                                                                        onSceneAdd
                                                                                    ) {
                                                                                        onSceneAdd(
                                                                                            liveScenes!
                                                                                                .length,
                                                                                        );
                                                                                    } else {
                                                                                        handleAddScene(
                                                                                            chapter.id,
                                                                                            liveScenes!
                                                                                                .length,
                                                                                        );
                                                                                    }
                                                                                }}
                                                                                className="h-auto w-full justify-start rounded-md py-1 pr-2.5 pl-[42px] text-left text-xs text-ink-faint transition-colors hover:bg-ink/5"
                                                                            >
                                                                                {t(
                                                                                    'chapterList.addScene',
                                                                                )}
                                                                            </Button>
                                                                        )}
                                                                </div>
                                                            </div>
                                                        )}
                                                </div>
                                            );
                                        })}
                                    </SortableContext>

                                    {onAddChapter && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={() =>
                                                onAddChapter(storyline.id)
                                            }
                                            className="h-auto w-full justify-start gap-1.5 rounded-md px-2.5 py-[7px] text-ink-faint hover:bg-ink/5"
                                        >
                                            <Plus
                                                size={12}
                                                className="text-ink-faint"
                                            />
                                            <span className="text-[13px] text-ink-faint">
                                                {t('chapterList.addChapter')}
                                            </span>
                                        </Button>
                                    )}
                                </SortableStorylineGroup>
                            ))}
                        </SortableContext>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={onAddStoryline}
                            className="h-auto w-full justify-start gap-1.5 px-2.5 pt-3.5 pb-1 text-[11px] font-medium tracking-[0.08em] text-ink-faint uppercase transition-colors hover:text-ink"
                        >
                            <Plus size={12} />
                            <span>{t('chapterList.addStoryline')}</span>
                        </Button>
                    </div>
                </div>

                <DragOverlay>
                    {activeItem?.type === 'chapter' && (
                        <div className="flex items-center gap-2 rounded-lg bg-surface-card px-2.5 py-[7px] text-[13px] leading-4 text-ink opacity-95 shadow-lg">
                            <span className="flex shrink-0 items-center text-ink-faint">
                                <GripVertical size={12} />
                            </span>
                            <span className="min-w-0 flex-1 truncate">
                                {activeItem.chapter.title}
                            </span>
                            {displaySettings.show_word_count && (
                                <span className="shrink-0 text-[11px] text-ink-faint">
                                    {formatWordCount(
                                        activeItem.chapter.word_count,
                                        displaySettings.compact_word_count,
                                    )}
                                </span>
                            )}
                        </div>
                    )}
                    {activeItem?.type === 'storyline' && (
                        <div className="flex items-center gap-1.5 rounded-md bg-surface-card px-2.5 py-[7px] opacity-95 shadow-lg">
                            <span
                                className={`size-1.5 shrink-0 rounded-full ${activeItem.storyline.color ? '' : 'bg-ink-faint'}`}
                                style={
                                    activeItem.storyline.color
                                        ? {
                                              backgroundColor:
                                                  activeItem.storyline.color,
                                          }
                                        : undefined
                                }
                            />
                            <SectionLabel className="block min-w-0 flex-1 truncate">
                                {activeItem.storyline.name}
                            </SectionLabel>
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
                    onRename={() =>
                        setRenaming({
                            type: 'chapter',
                            chapter: contextMenu.chapter,
                        })
                    }
                    onDelete={() =>
                        setDialog({
                            type: 'deleteChapter',
                            chapter: contextMenu.chapter,
                        })
                    }
                    onOpenInNewPane={onOpenInNewPane}
                />
            )}

            {contextMenu?.type === 'storyline' && (
                <StorylineContextMenu
                    bookId={bookId}
                    storyline={contextMenu.storyline}
                    isLastStoryline={storylines.length <= 1}
                    position={contextMenu.position}
                    onClose={() => setContextMenu(null)}
                    onRename={() =>
                        setRenaming({
                            type: 'storyline',
                            storyline: contextMenu.storyline,
                        })
                    }
                    onDelete={() =>
                        setDialog({
                            type: 'deleteStoryline',
                            storyline: contextMenu.storyline,
                        })
                    }
                />
            )}

            {contextMenu?.type === 'scene' && (
                <SceneContextMenu
                    scene={contextMenu.scene}
                    canDelete={contextMenu.sceneCount > 1}
                    position={contextMenu.position}
                    onClose={() => setContextMenu(null)}
                    onRename={() =>
                        setRenaming({
                            type: 'scene',
                            scene: contextMenu.scene,
                            chapterId: contextMenu.chapterId,
                        })
                    }
                    onDelete={() =>
                        handleDeleteScene(
                            contextMenu.chapterId,
                            contextMenu.scene.id,
                        )
                    }
                />
            )}

            {renamingProps && (
                <RenameDialog
                    title={t(renamingProps.titleKey)}
                    label={t(renamingProps.labelKey)}
                    value={renamingProps.value}
                    onSubmit={handleRenameSubmit}
                    onClose={() => setRenaming(null)}
                />
            )}

            {dialog?.type === 'deleteChapter' && (
                <DeleteChapterDialog
                    bookId={bookId}
                    chapter={dialog.chapter}
                    onClose={() => setDialog(null)}
                />
            )}

            {dialog?.type === 'deleteStoryline' && (
                <DeleteStorylineDialog
                    bookId={bookId}
                    storyline={dialog.storyline}
                    onClose={() => setDialog(null)}
                />
            )}
        </>
    );
}
