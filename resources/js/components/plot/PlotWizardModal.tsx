import { store as setupStructure } from '@/actions/App/Http/Controllers/PlotSetupController';
import type { PlotTemplate, TemplateBeat } from '@/lib/plot-templates';
import type { Book, Chapter, PlotPointType, Storyline } from '@/types/models';
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
import { router } from '@inertiajs/react';
import { ChevronDown, ChevronUp, GripVertical, Plus, Sparkles, Trash2, X } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

type WizardAct = {
    title: string;
    color: string;
    beats: TemplateBeat[];
    expanded: boolean;
};

type ChapterAssignments = Record<string, number[]>;

type PlotWizardModalProps = {
    book: Book;
    template: PlotTemplate;
    storylines: Storyline[];
    chapters: Chapter[];
    isOpen: boolean;
    onClose: () => void;
};

type Step = 'customize' | 'map-chapters' | 'review';

export default function PlotWizardModal({ book, template, storylines, chapters, isOpen, onClose }: PlotWizardModalProps) {
    const { t } = useTranslation('plot');
    const hasChapters = chapters.length > 0;
    const steps: Step[] = hasChapters ? ['customize', 'map-chapters', 'review'] : ['customize', 'review'];

    const [currentStep, setCurrentStep] = useState<Step>('customize');
    const [acts, setActs] = useState<WizardAct[]>(() =>
        template.acts.map((a) => ({ ...a, beats: [...a.beats], expanded: false })),
    );
    const [chapterAssignments, setChapterAssignments] = useState<ChapterAssignments>({});
    const [submitting, setSubmitting] = useState(false);

    const currentStepIndex = steps.indexOf(currentStep);
    const totalBeats = acts.reduce((sum, a) => sum + a.beats.length, 0);

    const handleNext = () => {
        const nextIndex = currentStepIndex + 1;
        if (nextIndex < steps.length) {
            setCurrentStep(steps[nextIndex]);
        }
    };

    const handleBack = () => {
        const prevIndex = currentStepIndex - 1;
        if (prevIndex >= 0) {
            setCurrentStep(steps[prevIndex]);
        }
    };

    const handleCreate = () => {
        setSubmitting(true);
        router.post(
            setupStructure.url({ book: book.id }),
            {
                template: template.key,
                acts: acts.map((a) => ({
                    title: a.title,
                    color: a.color,
                    beats: a.beats.map((b) => ({ title: b.title, type: b.type })),
                })),
                chapter_assignments: hasChapters ? chapterAssignments : null,
            },
            {
                onFinish: () => setSubmitting(false),
                onSuccess: () => onClose(),
            },
        );
    };

    const updateAct = (index: number, patch: Partial<WizardAct>) => {
        setActs((prev) => prev.map((a, i) => (i === index ? { ...a, ...patch } : a)));
    };

    const updateBeat = (actIndex: number, beatIndex: number, patch: Partial<TemplateBeat>) => {
        setActs((prev) =>
            prev.map((a, ai) =>
                ai === actIndex
                    ? { ...a, beats: a.beats.map((b, bi) => (bi === beatIndex ? { ...b, ...patch } : b)) }
                    : a,
            ),
        );
    };

    const removeBeat = (actIndex: number, beatIndex: number) => {
        setActs((prev) =>
            prev.map((a, ai) =>
                ai === actIndex ? { ...a, beats: a.beats.filter((_, bi) => bi !== beatIndex) } : a,
            ),
        );
    };

    const addBeat = (actIndex: number) => {
        setActs((prev) =>
            prev.map((a, ai) =>
                ai === actIndex
                    ? { ...a, beats: [...a.beats, { title: '', type: 'setup' as PlotPointType }] }
                    : a,
            ),
        );
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
            <div className="absolute inset-0 bg-black/25" onClick={onClose} />
            <div className="relative z-10 flex max-h-[80vh] w-[640px] flex-col rounded-2xl bg-surface-card shadow-[0_16px_48px_rgba(0,0,0,0.15),0_4px_12px_rgba(0,0,0,0.05)]">
                {/* Header */}
                <div className="flex items-center justify-between px-8 py-6">
                    <div className="flex flex-col gap-1">
                        <span className="text-[11px] font-medium uppercase tracking-[0.08em] text-ink-faint">
                            {t('wizard.stepLabel', {
                                current: currentStepIndex + 1,
                                total: steps.length,
                            })}
                        </span>
                        <h2 className="font-serif text-[24px] leading-8 tracking-[-0.01em] text-ink">
                            {t(`wizard.${currentStep}.title`)}
                        </h2>
                    </div>
                    <button
                        onClick={onClose}
                        className="flex h-8 w-8 items-center justify-center rounded-md text-ink-muted hover:bg-neutral-bg hover:text-ink"
                    >
                        <X size={18} />
                    </button>
                </div>

                <div className="border-t border-border" />

                {/* Body */}
                <div className="flex-1 overflow-y-auto px-8 py-5">
                    {currentStep === 'customize' && (
                        <CustomizeStep
                            acts={acts}
                            onUpdateAct={updateAct}
                            onUpdateBeat={updateBeat}
                            onRemoveBeat={removeBeat}
                            onAddBeat={addBeat}
                        />
                    )}
                    {currentStep === 'map-chapters' && (
                        <MapChaptersStep
                            acts={acts}
                            chapters={chapters}
                            storylines={storylines}
                            assignments={chapterAssignments}
                            onAssign={setChapterAssignments}
                        />
                    )}
                    {currentStep === 'review' && (
                        <ReviewStep
                            template={template}
                            acts={acts}
                            chapterAssignments={chapterAssignments}
                            chapters={chapters}
                            hasChapters={hasChapters}
                        />
                    )}
                </div>

                <div className="border-t border-border" />

                {/* Footer */}
                <div className="flex items-center justify-between px-8 py-5">
                    {currentStepIndex > 0 ? (
                        <button
                            onClick={handleBack}
                            className="rounded-lg border border-border px-6 py-2.5 text-[13px] font-medium text-ink-soft hover:bg-neutral-bg"
                        >
                            {t('wizard.back')}
                        </button>
                    ) : (
                        <div />
                    )}

                    {currentStep === 'review' ? (
                        <button
                            onClick={handleCreate}
                            disabled={submitting}
                            className="flex items-center gap-2 rounded-lg bg-ink px-6 py-2.5 text-[13px] font-medium text-surface disabled:opacity-50"
                        >
                            <Sparkles size={14} />
                            {t('wizard.createStructure')}
                        </button>
                    ) : (
                        <button
                            onClick={handleNext}
                            className="rounded-lg bg-ink px-6 py-2.5 text-[13px] font-medium text-surface"
                        >
                            {t('wizard.next')}
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}

/* ---------- Step: Customize Acts ---------- */

function CustomizeStep({
    acts,
    onUpdateAct,
    onUpdateBeat,
    onRemoveBeat,
    onAddBeat,
}: {
    acts: WizardAct[];
    onUpdateAct: (index: number, patch: Partial<WizardAct>) => void;
    onUpdateBeat: (actIndex: number, beatIndex: number, patch: Partial<TemplateBeat>) => void;
    onRemoveBeat: (actIndex: number, beatIndex: number) => void;
    onAddBeat: (actIndex: number) => void;
}) {
    const { t } = useTranslation('plot');

    return (
        <div className="flex flex-col gap-5">
            {acts.map((act, actIndex) => (
                <div
                    key={actIndex}
                    className="rounded-xl border border-border"
                >
                    <button
                        onClick={() => onUpdateAct(actIndex, { expanded: !act.expanded })}
                        className="flex w-full items-center gap-3 px-5 py-3.5"
                    >
                        <span
                            className="h-4 w-1 shrink-0 rounded-full"
                            style={{ backgroundColor: act.color }}
                        />
                        <input
                            type="text"
                            value={act.title}
                            onChange={(e) => onUpdateAct(actIndex, { title: e.target.value })}
                            onClick={(e) => e.stopPropagation()}
                            className="flex-1 bg-transparent text-[14px] font-semibold text-ink outline-none placeholder:text-ink-faint"
                            placeholder={t('wizard.customize.actNamePlaceholder')}
                        />
                        <span className="text-[12px] text-ink-faint">
                            {t('wizard.customize.beatCount', { count: act.beats.length })}
                        </span>
                        {act.expanded ? (
                            <ChevronUp size={16} className="text-ink-muted" />
                        ) : (
                            <ChevronDown size={16} className="text-ink-muted" />
                        )}
                    </button>

                    {act.expanded && (
                        <div className="border-t border-border px-5 pb-4 pt-3">
                            <div className="flex flex-col gap-2">
                                {act.beats.map((beat, beatIndex) => (
                                    <div
                                        key={beatIndex}
                                        className="flex items-center gap-2"
                                    >
                                        <span className="h-1.5 w-1.5 shrink-0 rounded-full bg-ink-faint" />
                                        <input
                                            type="text"
                                            value={beat.title}
                                            onChange={(e) =>
                                                onUpdateBeat(actIndex, beatIndex, { title: e.target.value })
                                            }
                                            className="flex-1 rounded border border-transparent bg-transparent px-2 py-1 text-[13px] text-ink outline-none hover:border-border focus:border-accent"
                                            placeholder={t('wizard.customize.beatNamePlaceholder')}
                                        />
                                        <button
                                            onClick={() => onRemoveBeat(actIndex, beatIndex)}
                                            className="flex h-6 w-6 shrink-0 items-center justify-center rounded text-ink-faint hover:bg-red-50 hover:text-red-500"
                                        >
                                            <Trash2 size={12} />
                                        </button>
                                    </div>
                                ))}
                            </div>
                            <button
                                onClick={() => onAddBeat(actIndex)}
                                className="mt-3 flex items-center gap-1.5 text-[12px] font-medium text-accent hover:underline"
                            >
                                <Plus size={12} />
                                {t('wizard.customize.addBeat')}
                            </button>
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}

/* ---------- Step: Map Chapters ---------- */

const POINTER_SENSOR_OPTIONS = { activationConstraint: { distance: 5 } };

function DraggableChapter({ chapter, storyline }: { chapter: Chapter; storyline?: Storyline }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: `chapter-${chapter.id}`,
        data: { type: 'wizard-chapter', chapter },
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.4 : 1,
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            {...attributes}
            className="flex items-center gap-2 rounded border border-border bg-surface px-3 py-2 text-[13px]"
        >
            <span {...listeners} className="cursor-grab text-ink-faint">
                <GripVertical size={14} />
            </span>
            {storyline && (
                <span
                    className="h-2 w-2 shrink-0 rounded-full"
                    style={{ backgroundColor: storyline.color ?? '#999' }}
                />
            )}
            <span className="truncate text-ink">{chapter.title}</span>
        </div>
    );
}

function MapChaptersStep({
    acts,
    chapters,
    storylines,
    assignments,
    onAssign,
}: {
    acts: WizardAct[];
    chapters: Chapter[];
    storylines: Storyline[];
    assignments: ChapterAssignments;
    onAssign: (assignments: ChapterAssignments) => void;
}) {
    const { t } = useTranslation('plot');
    const sensors = useSensors(useSensor(PointerSensor, POINTER_SENSOR_OPTIONS));
    const [activeChapter, setActiveChapter] = useState<Chapter | null>(null);

    const assignedChapterIds = useMemo(() => {
        const ids = new Set<number>();
        Object.values(assignments).forEach((arr) => arr.forEach((id) => ids.add(id)));
        return ids;
    }, [assignments]);

    const unassignedChapters = useMemo(
        () => chapters.filter((ch) => !assignedChapterIds.has(ch.id)),
        [chapters, assignedChapterIds],
    );

    const storylineMap = useMemo(() => {
        const map = new Map<number, Storyline>();
        storylines.forEach((s) => map.set(s.id, s));
        return map;
    }, [storylines]);

    const handleDragStart = useCallback((event: DragStartEvent) => {
        const ch = event.active.data.current?.chapter as Chapter | undefined;
        if (ch) setActiveChapter(ch);
    }, []);

    const handleDragEnd = useCallback(
        (event: DragEndEvent) => {
            setActiveChapter(null);
            const { active, over } = event;
            if (!over) return;

            const chapterId = (active.data.current?.chapter as Chapter)?.id;
            const targetActKey = over.id as string;
            if (!chapterId || !targetActKey.startsWith('act_index_')) return;

            const newAssignments = { ...assignments };
            // Remove from any existing assignment
            for (const key of Object.keys(newAssignments)) {
                newAssignments[key] = newAssignments[key].filter((id) => id !== chapterId);
                if (newAssignments[key].length === 0) delete newAssignments[key];
            }
            // Add to target
            if (!newAssignments[targetActKey]) newAssignments[targetActKey] = [];
            newAssignments[targetActKey].push(chapterId);
            onAssign(newAssignments);
        },
        [assignments, onAssign],
    );

    const removeFromAct = (actKey: string, chapterId: number) => {
        const newAssignments = { ...assignments };
        newAssignments[actKey] = (newAssignments[actKey] || []).filter((id) => id !== chapterId);
        if (newAssignments[actKey].length === 0) delete newAssignments[actKey];
        onAssign(newAssignments);
    };

    return (
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragStart={handleDragStart} onDragEnd={handleDragEnd}>
            <div className="flex flex-col gap-5">
                <p className="text-[13px] leading-[20px] text-ink-muted">
                    {t('wizard.mapChapters.description')}
                </p>

                {/* Unassigned chapters */}
                {unassignedChapters.length > 0 && (
                    <div>
                        <h4 className="mb-2 text-[12px] font-medium uppercase tracking-[0.06em] text-ink-faint">
                            {t('wizard.mapChapters.unassigned')} ({unassignedChapters.length})
                        </h4>
                        <SortableContext
                            items={unassignedChapters.map((ch) => `chapter-${ch.id}`)}
                            strategy={verticalListSortingStrategy}
                        >
                            <div className="flex flex-col gap-1.5">
                                {unassignedChapters.map((ch) => (
                                    <DraggableChapter
                                        key={ch.id}
                                        chapter={ch}
                                        storyline={storylineMap.get(ch.storyline_id)}
                                    />
                                ))}
                            </div>
                        </SortableContext>
                    </div>
                )}

                {/* Act drop zones */}
                {acts.map((act, index) => {
                    const actKey = `act_index_${index}`;
                    const assignedIds = assignments[actKey] || [];
                    const assignedChapters = assignedIds
                        .map((id) => chapters.find((ch) => ch.id === id))
                        .filter(Boolean) as Chapter[];

                    return (
                        <ActDropZone
                            key={index}
                            act={act}
                            actKey={actKey}
                            assignedChapters={assignedChapters}
                            storylineMap={storylineMap}
                            onRemove={(chapterId) => removeFromAct(actKey, chapterId)}
                        />
                    );
                })}
            </div>

            <DragOverlay>
                {activeChapter && (
                    <div className="flex items-center gap-2 rounded border border-accent bg-surface-card px-3 py-2 text-[13px] shadow-lg">
                        <GripVertical size={14} className="text-ink-faint" />
                        <span className="text-ink">{activeChapter.title}</span>
                    </div>
                )}
            </DragOverlay>
        </DndContext>
    );
}

function ActDropZone({
    act,
    actKey,
    assignedChapters,
    storylineMap,
    onRemove,
}: {
    act: WizardAct;
    actKey: string;
    assignedChapters: Chapter[];
    storylineMap: Map<number, Storyline>;
    onRemove: (chapterId: number) => void;
}) {
    const { t } = useTranslation('plot');
    const { setNodeRef, isOver } = useSortable({
        id: actKey,
        data: { type: 'act-drop-zone' },
    });

    return (
        <div
            ref={setNodeRef}
            className={`rounded-xl border-2 border-dashed p-4 transition-colors ${
                isOver ? 'border-accent bg-accent/5' : 'border-border'
            }`}
        >
            <div className="mb-2 flex items-center gap-2">
                <span className="h-3 w-1 rounded-full" style={{ backgroundColor: act.color }} />
                <h4 className="text-[13px] font-semibold text-ink">{act.title}</h4>
            </div>

            {assignedChapters.length > 0 ? (
                <div className="flex flex-col gap-1.5">
                    {assignedChapters.map((ch) => (
                        <div
                            key={ch.id}
                            className="flex items-center gap-2 rounded border border-border bg-surface px-3 py-2 text-[13px]"
                        >
                            {storylineMap.get(ch.storyline_id) && (
                                <span
                                    className="h-2 w-2 shrink-0 rounded-full"
                                    style={{ backgroundColor: storylineMap.get(ch.storyline_id)!.color ?? '#999' }}
                                />
                            )}
                            <span className="flex-1 truncate text-ink">{ch.title}</span>
                            <button
                                onClick={() => onRemove(ch.id)}
                                className="flex h-5 w-5 items-center justify-center rounded text-ink-faint hover:text-red-500"
                            >
                                <X size={12} />
                            </button>
                        </div>
                    ))}
                </div>
            ) : (
                <p className="py-3 text-center text-[12px] text-ink-faint">
                    {t('wizard.mapChapters.dropHere')}
                </p>
            )}
        </div>
    );
}

/* ---------- Step: Review ---------- */

function ReviewStep({
    template,
    acts,
    chapterAssignments,
    chapters,
    hasChapters,
}: {
    template: PlotTemplate;
    acts: WizardAct[];
    chapterAssignments: ChapterAssignments;
    chapters: Chapter[];
    hasChapters: boolean;
}) {
    const { t } = useTranslation('plot');
    const totalBeats = acts.reduce((sum, a) => sum + a.beats.length, 0);

    return (
        <div className="flex flex-col gap-5">
            <div className="flex flex-col gap-3">
                <h3 className="text-[15px] font-semibold text-ink">
                    {t(`emptyState.template.${template.key}.name`)}
                </h3>
                <p className="text-[13px] leading-[20px] text-ink-muted">
                    {t('wizard.review.summary', {
                        acts: acts.length,
                        beats: totalBeats,
                    })}
                </p>
            </div>

            <div className="flex flex-col gap-3">
                {acts.map((act, index) => {
                    const actKey = `act_index_${index}`;
                    const assignedCount = (chapterAssignments[actKey] || []).length;

                    return (
                        <div
                            key={index}
                            className="flex items-center gap-3 rounded-lg border border-border px-4 py-3"
                        >
                            <span
                                className="h-4 w-1 shrink-0 rounded-full"
                                style={{ backgroundColor: act.color }}
                            />
                            <span className="flex-1 text-[14px] font-medium text-ink">
                                {t('actTitle', { number: index + 1, title: act.title })}
                            </span>
                            <span className="text-[12px] text-ink-faint">
                                {t('wizard.customize.beatCount', { count: act.beats.length })}
                            </span>
                            {hasChapters && assignedCount > 0 && (
                                <span className="text-[12px] text-ink-faint">
                                    · {t('wizard.review.chaptersAssigned', { count: assignedCount })}
                                </span>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
