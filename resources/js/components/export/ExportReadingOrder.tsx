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
import { Check, ChevronDown, GripVertical } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type {
    ChapterRow,
    MatterItem,
    StorylineRef,
} from '@/components/export/types';
import { cn } from '@/lib/utils';

type ExportReadingOrderProps = {
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

function CustomCheckbox({
    checked,
    onClick,
}: {
    checked: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex h-[14px] w-[14px] shrink-0 items-center justify-center rounded-[3px]',
                checked
                    ? 'bg-ink'
                    : 'border border-[#D0CFCD] dark:border-ink-faint',
            )}
        >
            {checked && (
                <Check
                    className="h-[10px] w-[10px] text-white"
                    strokeWidth={3}
                />
            )}
        </button>
    );
}

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
                className="flex shrink-0 cursor-grab items-center text-[#D0D0D0] active:cursor-grabbing dark:text-ink-faint"
            >
                <GripVertical className="h-3 w-3" />
            </span>

            <CustomCheckbox checked={checked} onClick={onToggle} />

            <span className="w-4 shrink-0 text-right text-[11px] font-medium text-[#B5B5B5] dark:text-ink-faint">
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
                        checked
                            ? 'text-[#4A4A4A] dark:text-ink-soft'
                            : 'text-[#B5B5B5] dark:text-ink-faint',
                    )}
                >
                    {chapter.title}
                </div>
                {storyline && (
                    <div className="text-[10px] font-light text-[#B5B5B5] dark:text-ink-faint">
                        {storyline.name}
                    </div>
                )}
            </div>
        </div>
    );
}

function MatterRow({
    item,
    onToggle,
}: {
    item: MatterItem;
    onToggle: () => void;
}) {
    return (
        <div className="flex items-center gap-2">
            <span className="flex shrink-0 items-center text-[#D0D0D0] dark:text-ink-faint">
                <GripVertical className="h-3 w-3" />
            </span>
            <CustomCheckbox checked={item.checked} onClick={onToggle} />
            <span
                className={cn(
                    'text-[12px]',
                    item.checked
                        ? 'text-[#4A4A4A] dark:text-ink-soft'
                        : 'text-[#B5B5B5] dark:text-ink-faint',
                )}
            >
                {item.label}
            </span>
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
        <div className="flex items-center gap-1.5">
            <ChevronDown className="h-3 w-3 text-[#8A8A8A] dark:text-ink-faint" />
            <span className="text-[10px] font-semibold tracking-[0.08em] text-ink-muted uppercase">
                {children}
            </span>
            {count !== undefined && (
                <span className="text-[10px] text-[#B5B5B5] dark:text-ink-faint">
                    {count}
                </span>
            )}
        </div>
    );
}

export default function ExportReadingOrder({
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
    const [activeChapter, setActiveChapter] = useState<ChapterRow | null>(null);

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
        <aside className="flex h-full w-[260px] shrink-0 flex-col border-r border-border-subtle bg-neutral-bg">
            {/* Header */}
            <div className="flex flex-col gap-1 px-4 py-5">
                <h2 className="text-[14px] font-semibold text-ink">
                    {t('readingOrder')}
                </h2>
                <p className="text-[11px] text-ink-faint">
                    {t('readingOrderSubtitle')}
                </p>
            </div>

            {/* Scrollable content */}
            <div className="flex-1 overflow-y-auto">
                {/* Front matter */}
                <div className="border-b border-border-subtle">
                    <div className="px-4 pt-3 pb-2">
                        <SectionHeader>{t('frontMatter')}</SectionHeader>
                    </div>
                    <div className="flex flex-col gap-1 px-4 pt-2 pb-3.5">
                        {frontMatter.map((item) => (
                            <MatterRow
                                key={item.id}
                                item={item}
                                onToggle={() => onToggleFrontMatter(item.id)}
                            />
                        ))}
                    </div>
                </div>

                {/* Chapters */}
                <div className="border-b border-border-subtle">
                    <div className="px-4 pt-1.5 pb-1">
                        <SectionHeader count={orderedChapters.length}>
                            {t('chapters')}
                        </SectionHeader>
                    </div>
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
                                strategy={verticalListSortingStrategy}
                            >
                                {orderedChapters.map((chapter, index) => (
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
                                            onToggleChapter(chapter.id)
                                        }
                                    />
                                ))}
                            </SortableContext>

                            <DragOverlay>
                                {activeChapter && (
                                    <div className="flex items-center gap-2 rounded bg-white px-2 py-1 opacity-95 shadow-[0_4px_16px_#0000001F,0_0_0_1px_#0000000A] dark:bg-surface-card">
                                        <span className="flex shrink-0 items-center text-[#D0D0D0] dark:text-ink-faint">
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
                                        <span className="min-w-0 flex-1 truncate text-[12px] text-[#4A4A4A] dark:text-ink-soft">
                                            {activeChapter.title}
                                        </span>
                                    </div>
                                )}
                            </DragOverlay>
                        </DndContext>
                    </div>
                </div>

                {/* Back matter */}
                <div className="border-b border-border-subtle">
                    <div className="px-4 pt-3 pb-2">
                        <SectionHeader>{t('backMatter')}</SectionHeader>
                    </div>
                    <div className="flex flex-col gap-1 px-4 pt-2 pb-3.5">
                        {backMatter.map((item) => (
                            <MatterRow
                                key={item.id}
                                item={item}
                                onToggle={() => onToggleBackMatter(item.id)}
                            />
                        ))}
                    </div>
                </div>
            </div>

            {/* Footer */}
            <div className="border-t border-border px-4 py-3.5">
                <p className="text-[11px] text-[#B5B5B5] dark:text-ink-faint">
                    {t('excludedHint')}
                </p>
            </div>
        </aside>
    );
}
