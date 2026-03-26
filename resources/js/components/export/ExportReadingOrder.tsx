import {
    DndContext,
    DragOverlay,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type { DragEndEvent, DragStartEvent } from '@dnd-kit/core';
import {
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    ChevronDown,
    GripVertical,
    PanelLeftClose,
    PanelLeftOpen,
    TableOfContents,
} from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { show as publishShow } from '@/actions/App/Http/Controllers/PublishController';
import { index as settingsIndex } from '@/actions/App/Http/Controllers/SettingsController';
import type {
    ChapterRow,
    MatterItem,
    StorylineRef,
} from '@/components/export/types';
import Checkbox from '@/components/ui/Checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/Collapsible';
import PanelHeader from '@/components/ui/PanelHeader';
import SectionLabel from '@/components/ui/SectionLabel';
import { useResizablePanel } from '@/hooks/useResizablePanel';
import { cn } from '@/lib/utils';

type ExportReadingOrderProps = {
    bookId: number;
    storylines: StorylineRef[];
    selectedChapterIds: Set<number>;
    onToggleChapter: (id: number) => void;
    orderedChapters: ChapterRow[];
    onReorder: (chapters: ChapterRow[]) => void;
    frontMatter: MatterItem[];
    onToggleFrontMatter: (id: string) => void;
    backMatter: MatterItem[];
    onToggleBackMatter: (id: string) => void;
};

const POINTER_SENSOR_OPTIONS = { activationConstraint: { distance: 5 } };

function SortableChapterRow({
    chapter,
    index,
    storyline,
    checked,
    onToggle,
}: {
    chapter: ChapterRow;
    index: number;
    storyline: StorylineRef | undefined;
    checked: boolean;
    onToggle: () => void;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({
        id: `export-${chapter.id}`,
        data: { type: 'export-chapter', chapter },
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            {...attributes}
            className={cn(
                'flex items-start gap-2 py-[3px]',
                isDragging && 'opacity-50',
            )}
        >
            <span
                {...listeners}
                className="flex shrink-0 cursor-grab items-center text-ink-faint active:cursor-grabbing"
            >
                <GripVertical className="h-3 w-3" />
            </span>

            <Checkbox checked={checked} onChange={onToggle} />

            <span className="w-4 shrink-0 text-right text-[11px] font-medium text-ink-faint">
                {index + 1}
            </span>

            <span
                className="mt-1 h-1.5 w-1.5 shrink-0 rounded-full"
                style={{ backgroundColor: storyline?.color ?? '#737373' }}
            />

            <div className="min-w-0 flex-1">
                <div
                    className={cn(
                        'truncate text-[12px]',
                        checked ? 'text-ink-soft' : 'text-ink-faint',
                    )}
                >
                    {chapter.title}
                </div>
                {storyline && (
                    <div className="text-[11px] font-light text-ink-faint">
                        {storyline.name}
                    </div>
                )}
            </div>
        </div>
    );
}

const PUBLISH_ANCHORS = new Set([
    'copyright',
    'dedication',
    'epigraph',
    'acknowledgments',
    'about-author',
    'also-by',
    'epilogue',
]);

function MatterRow({
    item,
    onToggle,
    fromUrl,
    publishUrl,
}: {
    item: MatterItem;
    onToggle: () => void;
    fromUrl: string;
    publishUrl?: string;
}) {
    const linkHref = item.settingsSection
        ? settingsIndex.url({
              query: { section: item.settingsSection, from: fromUrl },
          })
        : publishUrl && PUBLISH_ANCHORS.has(item.id)
          ? `${publishUrl}#${item.id}`
          : undefined;

    return (
        <div className="flex items-center gap-2">
            <span className="flex shrink-0 items-center text-ink-faint">
                <GripVertical className="h-3 w-3" />
            </span>
            <Checkbox checked={item.checked} onChange={onToggle} />
            <span
                className={cn(
                    'min-w-0 flex-1 truncate text-[12px]',
                    item.checked ? 'text-ink-soft' : 'text-ink-faint',
                )}
            >
                {item.label}
            </span>
            {linkHref && (
                <Link
                    href={linkHref}
                    className="shrink-0 text-ink-faint transition-colors hover:text-ink-muted dark:hover:text-ink-soft"
                >
                    <ArrowUpRight className="h-3 w-3" />
                </Link>
            )}
        </div>
    );
}

function SectionHeader({
    children,
    count,
}: {
    children: React.ReactNode;
    count?: number;
}) {
    return (
        <button
            type="button"
            className="group flex w-full items-center gap-1.5"
        >
            <ChevronDown className="h-3 w-3 text-ink-faint transition-transform group-data-[state=closed]:-rotate-90" />
            <SectionLabel>{children}</SectionLabel>
            {count !== undefined && (
                <span className="text-[11px] text-ink-faint">{count}</span>
            )}
        </button>
    );
}

export default function ExportReadingOrder({
    bookId,
    storylines,
    selectedChapterIds,
    onToggleChapter,
    orderedChapters,
    onReorder,
    frontMatter,
    onToggleFrontMatter,
    backMatter,
    onToggleBackMatter,
}: ExportReadingOrderProps) {
    const { t } = useTranslation('export');
    const pageUrl = usePage().url;
    const bookPublishUrl = publishShow.url(bookId);
    const [activeChapter, setActiveChapter] = useState<ChapterRow | null>(null);

    const { width, isCollapsed, toggleCollapsed, panelRef, handleMouseDown } =
        useResizablePanel({
            storageKey: 'export-reading-order-width',
            minWidth: 220,
            maxWidth: 400,
            defaultWidth: 260,
            collapsible: true,
            collapsedWidth: 36,
            collapseThreshold: 160,
        });

    const sensors = useSensors(
        useSensor(PointerSensor, POINTER_SENSOR_OPTIONS),
    );

    const storylineMap = useMemo(() => {
        const map = new Map<number, StorylineRef>();
        for (const s of storylines) map.set(s.id, s);
        return map;
    }, [storylines]);

    const handleDragStart = useCallback((event: DragStartEvent) => {
        const data = event.active.data.current;
        if (data?.type === 'export-chapter') {
            setActiveChapter(data.chapter);
        }
    }, []);

    const handleDragEnd = useCallback(
        (event: DragEndEvent) => {
            setActiveChapter(null);
            const { active, over } = event;
            if (!over || active.id === over.id) return;

            const oldIndex = orderedChapters.findIndex(
                (ch) => `export-${ch.id}` === active.id,
            );
            const newIndex = orderedChapters.findIndex(
                (ch) => `export-${ch.id}` === over.id,
            );
            if (oldIndex === -1 || newIndex === -1) return;

            const reordered = [...orderedChapters];
            const [moved] = reordered.splice(oldIndex, 1);
            reordered.splice(newIndex, 0, moved);
            onReorder(reordered);
        },
        [orderedChapters, onReorder],
    );

    const activeStoryline = activeChapter
        ? storylineMap.get(activeChapter.storyline_id)
        : undefined;

    return (
        <aside
            ref={panelRef}
            className="relative flex h-full shrink-0 flex-col overflow-hidden border-r border-border-subtle bg-white transition-[width] duration-200 ease-out dark:bg-surface-card"
            style={{ width }}
        >
            {isCollapsed ? (
                <div className="flex flex-col items-center pt-3">
                    <button
                        type="button"
                        onClick={toggleCollapsed}
                        className="flex h-7 w-7 items-center justify-center rounded-md text-ink-faint transition-colors hover:bg-neutral-bg hover:text-ink-muted"
                    >
                        <PanelLeftOpen size={14} />
                    </button>
                    <TableOfContents
                        size={14}
                        className="mt-3 text-ink-faint"
                    />
                </div>
            ) : (
                <>
                    {/* Resize handle */}
                    <div
                        onMouseDown={handleMouseDown}
                        className="group absolute inset-y-0 -right-1 z-10 w-2 cursor-col-resize"
                    >
                        <div className="absolute inset-y-0 right-[3px] w-px bg-transparent transition-colors group-hover:bg-ink/20" />
                    </div>

                    {/* Header */}
                    <PanelHeader
                        title={t('readingOrder')}
                        icon={
                            <TableOfContents
                                size={14}
                                className="text-ink-faint"
                            />
                        }
                        suffix={
                            <button
                                type="button"
                                onClick={toggleCollapsed}
                                className="flex size-6 items-center justify-center rounded text-ink-muted transition-colors hover:text-ink-soft"
                            >
                                <PanelLeftClose size={14} />
                            </button>
                        }
                    />

                    {/* Scrollable content */}
                    <div className="flex-1 overflow-y-auto">
                        {/* Front matter */}
                        <Collapsible defaultOpen>
                            <div className="border-b border-border-subtle">
                                <div className="px-4 pt-3 pb-2">
                                    <CollapsibleTrigger asChild>
                                        <SectionHeader>
                                            {t('frontMatter')}
                                        </SectionHeader>
                                    </CollapsibleTrigger>
                                </div>
                                <CollapsibleContent className="overflow-hidden data-[state=closed]:animate-collapsible-up data-[state=open]:animate-collapsible-down">
                                    <div className="flex flex-col gap-1 px-4 pt-2 pb-3.5">
                                        {frontMatter.map((item) => (
                                            <MatterRow
                                                key={item.id}
                                                item={item}
                                                onToggle={() =>
                                                    onToggleFrontMatter(item.id)
                                                }
                                                fromUrl={pageUrl}
                                                publishUrl={bookPublishUrl}
                                            />
                                        ))}
                                    </div>
                                </CollapsibleContent>
                            </div>
                        </Collapsible>

                        {/* Chapters */}
                        <Collapsible defaultOpen>
                            <div className="border-b border-border-subtle">
                                <div className="px-4 pt-3 pb-2">
                                    <CollapsibleTrigger asChild>
                                        <SectionHeader
                                            count={orderedChapters.length}
                                        >
                                            {t('chapters')}
                                        </SectionHeader>
                                    </CollapsibleTrigger>
                                </div>
                                <CollapsibleContent className="overflow-hidden data-[state=closed]:animate-collapsible-up data-[state=open]:animate-collapsible-down">
                                    <div className="flex flex-col px-4 pt-1 pb-3.5">
                                        <DndContext
                                            sensors={sensors}
                                            collisionDetection={closestCenter}
                                            onDragStart={handleDragStart}
                                            onDragEnd={handleDragEnd}
                                        >
                                            <SortableContext
                                                items={orderedChapters.map(
                                                    (ch) => `export-${ch.id}`,
                                                )}
                                                strategy={
                                                    verticalListSortingStrategy
                                                }
                                            >
                                                {orderedChapters.map(
                                                    (chapter, index) => (
                                                        <SortableChapterRow
                                                            key={chapter.id}
                                                            chapter={chapter}
                                                            index={index}
                                                            storyline={storylineMap.get(
                                                                chapter.storyline_id,
                                                            )}
                                                            checked={selectedChapterIds.has(
                                                                chapter.id,
                                                            )}
                                                            onToggle={() =>
                                                                onToggleChapter(
                                                                    chapter.id,
                                                                )
                                                            }
                                                        />
                                                    ),
                                                )}
                                            </SortableContext>

                                            <DragOverlay>
                                                {activeChapter && (
                                                    <div className="flex items-center gap-2 rounded bg-white px-2 py-1 opacity-95 shadow-[0_4px_16px_#0000001F,0_0_0_1px_#0000000A] dark:bg-surface-card">
                                                        <span className="flex shrink-0 items-center text-ink-faint">
                                                            <GripVertical className="h-3 w-3" />
                                                        </span>
                                                        <span
                                                            className="h-1.5 w-1.5 shrink-0 rounded-full"
                                                            style={{
                                                                backgroundColor:
                                                                    activeStoryline?.color ??
                                                                    '#737373',
                                                            }}
                                                        />
                                                        <span className="min-w-0 flex-1 truncate text-[12px] text-ink-soft">
                                                            {
                                                                activeChapter.title
                                                            }
                                                        </span>
                                                    </div>
                                                )}
                                            </DragOverlay>
                                        </DndContext>
                                    </div>
                                </CollapsibleContent>
                            </div>
                        </Collapsible>

                        {/* Back matter */}
                        <Collapsible defaultOpen>
                            <div className="border-b border-border-subtle">
                                <div className="px-4 pt-3 pb-2">
                                    <CollapsibleTrigger asChild>
                                        <SectionHeader>
                                            {t('backMatter')}
                                        </SectionHeader>
                                    </CollapsibleTrigger>
                                </div>
                                <CollapsibleContent className="overflow-hidden data-[state=closed]:animate-collapsible-up data-[state=open]:animate-collapsible-down">
                                    <div className="flex flex-col gap-1 px-4 pt-2 pb-3.5">
                                        {backMatter.map((item) => (
                                            <MatterRow
                                                key={item.id}
                                                item={item}
                                                onToggle={() =>
                                                    onToggleBackMatter(item.id)
                                                }
                                                fromUrl={pageUrl}
                                                publishUrl={bookPublishUrl}
                                            />
                                        ))}
                                    </div>
                                </CollapsibleContent>
                            </div>
                        </Collapsible>
                    </div>

                    {/* Footer */}
                    <div className="border-t border-border px-4 py-3.5">
                        <p className="text-[11px] text-ink-faint">
                            {t('excludedHint')}
                        </p>
                    </div>
                </>
            )}
        </aside>
    );
}
