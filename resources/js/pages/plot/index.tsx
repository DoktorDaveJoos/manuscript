import type { FormDataConvertible } from '@inertiajs/core';
import { Head, router } from '@inertiajs/react';
import { Check, Filter } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    assignAct,
    interleave as interleaveChapters,
    reorder as reorderChapters,
} from '@/actions/App/Http/Controllers/ChapterController';
import {
    store as storePlotPoint,
    update as updatePlotPoint,
} from '@/actions/App/Http/Controllers/PlotPointController';
import Sidebar from '@/components/editor/Sidebar';
import DetailPanel from '@/components/plot/DetailPanel';
import PlotEmptyState from '@/components/plot/PlotEmptyState';
import PlotPointList from '@/components/plot/PlotPointList';
import PlotWizardModal from '@/components/plot/PlotWizardModal';
import ReadingOrderPanel from '@/components/plot/ReadingOrderPanel';
import SwimLaneTimeline from '@/components/plot/SwimLaneTimeline';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import { getXsrfToken } from '@/lib/csrf';
import { downloadExport } from '@/lib/export-download';
import type { PlotTemplate } from '@/lib/plot-templates';
import { cn } from '@/lib/utils';
import type {
    Act,
    Book,
    Chapter,
    Character,
    PlotPoint,
    PlotPointConnection,
    Storyline,
} from '@/types/models';

type ChapterCol = {
    id: number;
    title: string;
    reader_order: number;
    act_id: number;
    storyline_id: number;
    tension_score: number | null;
    word_count?: number;
};

type PlotPageProps = {
    book: Book;
    storylines: Storyline[];
    acts: (Act & { chapters: ChapterCol[] })[];
    plotPoints: PlotPoint[];
    connections: PlotPointConnection[];
    chapters: Chapter[];
    characters: Character[];
};

type Tab = 'timeline' | 'list';

export default function Plot({
    book,
    storylines,
    acts,
    plotPoints,
    connections,
    chapters,
    characters,
}: PlotPageProps) {
    const { t } = useTranslation('plot');
    const sidebarStorylines = useSidebarStorylines();
    const [activeTab, setActiveTab] = useState<Tab>('timeline');
    const [selectedPlotPointId, setSelectedPlotPointId] = useState<
        number | null
    >(null);
    const selectedPlotPoint = selectedPlotPointId
        ? (plotPoints.find((pp) => pp.id === selectedPlotPointId) ?? null)
        : null;
    const [selectedTemplate, setSelectedTemplate] =
        useState<PlotTemplate | null>(null);
    const [storylineFilter, setStorylineFilter] = useState<Set<number>>(
        new Set(),
    );
    const [filterOpen, setFilterOpen] = useState(false);
    const filterRef = useRef<HTMLDivElement>(null);
    const hasActs = acts.length > 0;

    useEffect(() => {
        if (!filterOpen) return;
        function handleClickOutside(e: MouseEvent) {
            if (
                filterRef.current &&
                !filterRef.current.contains(e.target as Node)
            ) {
                setFilterOpen(false);
            }
        }
        function handleEscape(e: KeyboardEvent) {
            if (e.key === 'Escape') setFilterOpen(false);
        }
        document.addEventListener('mousedown', handleClickOutside);
        document.addEventListener('keydown', handleEscape);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            document.removeEventListener('keydown', handleEscape);
        };
    }, [filterOpen]);

    const toggleStorylineFilter = useCallback((id: number) => {
        setStorylineFilter((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    }, []);

    const filterLabel = useMemo(() => {
        if (storylineFilter.size === 0) return t('page.allStorylines');
        if (storylineFilter.size === 1)
            return storylines.find((s) => storylineFilter.has(s.id))?.name;
        return t('page.storylineFilterCount', { count: storylineFilter.size });
    }, [storylineFilter, storylines, t]);

    const filteredPlotPoints = useMemo(
        () =>
            storylineFilter.size > 0
                ? plotPoints.filter(
                      (pp) =>
                          pp.storyline_id !== null &&
                          storylineFilter.has(pp.storyline_id),
                  )
                : plotPoints,
        [plotPoints, storylineFilter],
    );

    const filteredStorylines = useMemo(
        () =>
            storylineFilter.size > 0
                ? storylines.filter((s) => storylineFilter.has(s.id))
                : storylines,
        [storylines, storylineFilter],
    );

    const handleCreatePlotPoint = (storylineId: number, chapterId: number) => {
        router.post(storePlotPoint.url({ book: book.id }), {
            title: t('page.newPlotPointTitle'),
            type: 'setup',
            storyline_id: storylineId,
            intended_chapter_id: chapterId,
        });
    };

    const handleUpdatePlotPoint = (
        id: number,
        data: Record<string, unknown>,
    ) => {
        router.patch(
            updatePlotPoint.url({ book: book.id, plotPoint: id }),
            data as Record<string, FormDataConvertible>,
            {
                preserveScroll: true,
            },
        );
    };

    const handleReadingOrderReorder = useCallback(
        (order: { id: number; storyline_id: number }[]) => {
            fetch(reorderChapters.url(book.id), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                body: JSON.stringify({ order }),
            }).then(() => {
                router.reload({ only: ['chapters', 'acts'] });
            });
        },
        [book.id],
    );

    const handleInterleave = useCallback(() => {
        fetch(interleaveChapters.url(book.id), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
        }).then(() => {
            router.reload({ only: ['chapters', 'acts'] });
        });
    }, [book.id]);

    const handleAssignChapterAct = useCallback(
        (chapterId: number, actId: number | null) => {
            fetch(assignAct.url({ book: book.id, chapter: chapterId }), {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                body: JSON.stringify({ act_id: actId }),
            }).then(() => {
                router.reload({ only: ['chapters', 'acts'] });
            });
        },
        [book.id],
    );

    const handleExportChapter = useCallback(
        (chapterId: number) => {
            downloadExport(book, {
                format: 'docx',
                scope: 'chapter',
                chapter_id: chapterId,
                include_chapter_titles: true,
            });
        },
        [book],
    );

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
                    {hasActs ? (
                        <>
                            {/* Header bar */}
                            <div className="flex h-12 items-center justify-between border-b border-border px-5">
                                <div className="flex h-full items-stretch gap-4">
                                    <button
                                        onClick={() => setActiveTab('timeline')}
                                        className={`flex items-center border-b-2 px-1 text-[13px] font-medium transition-colors ${
                                            activeTab === 'timeline'
                                                ? 'border-ink text-ink'
                                                : 'border-transparent text-ink-muted hover:text-ink-soft'
                                        }`}
                                    >
                                        {t('page.tabs.timeline')}
                                    </button>
                                    <button
                                        onClick={() => setActiveTab('list')}
                                        className={`flex items-center border-b-2 px-1 text-[13px] font-medium transition-colors ${
                                            activeTab === 'list'
                                                ? 'border-ink text-ink'
                                                : 'border-transparent text-ink-muted hover:text-ink-soft'
                                        }`}
                                    >
                                        {t('page.tabs.list')}
                                    </button>
                                </div>

                                <div className="flex items-center gap-2">
                                    {/* Storyline filter */}
                                    <div className="relative" ref={filterRef}>
                                        <button
                                            onClick={() =>
                                                setFilterOpen((o) => !o)
                                            }
                                            className={cn(
                                                'flex items-center gap-1.5 rounded border py-1.5 pr-3 pl-7 text-[13px] text-ink-soft focus:ring-1 focus:ring-accent focus:outline-none',
                                                storylineFilter.size > 0
                                                    ? 'border-accent bg-accent/10'
                                                    : 'border-border bg-surface-card',
                                            )}
                                        >
                                            <Filter
                                                size={14}
                                                className="absolute top-1/2 left-2 -translate-y-1/2 text-ink-muted"
                                            />
                                            {filterLabel}
                                        </button>

                                        {filterOpen && (
                                            <div className="absolute right-0 z-50 mt-1 min-w-[200px] rounded-lg border border-border bg-surface-card py-1 shadow-lg">
                                                <button
                                                    onClick={() =>
                                                        setStorylineFilter(
                                                            (prev) =>
                                                                prev.size === 0
                                                                    ? prev
                                                                    : new Set(),
                                                        )
                                                    }
                                                    className="flex w-full items-center gap-2 px-3 py-1.5 text-left text-[13px] text-ink-soft hover:bg-neutral-bg"
                                                >
                                                    <Check
                                                        size={14}
                                                        className={cn(
                                                            'shrink-0',
                                                            storylineFilter.size ===
                                                                0
                                                                ? 'text-ink'
                                                                : 'text-transparent',
                                                        )}
                                                    />
                                                    {t('page.allStorylines')}
                                                </button>
                                                <div className="my-1 border-t border-border" />
                                                {storylines.map((s) => (
                                                    <button
                                                        key={s.id}
                                                        onClick={() =>
                                                            toggleStorylineFilter(
                                                                s.id,
                                                            )
                                                        }
                                                        className="flex w-full items-center gap-2 px-3 py-1.5 text-left text-[13px] text-ink-soft hover:bg-neutral-bg"
                                                    >
                                                        <Check
                                                            size={14}
                                                            className={cn(
                                                                'shrink-0',
                                                                storylineFilter.has(
                                                                    s.id,
                                                                )
                                                                    ? 'text-ink'
                                                                    : 'text-transparent',
                                                            )}
                                                        />
                                                        <span
                                                            className="mr-1 inline-block h-2.5 w-2.5 shrink-0 rounded-full"
                                                            style={{
                                                                backgroundColor:
                                                                    s.color ??
                                                                    undefined,
                                                            }}
                                                        />
                                                        {s.name}
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                    </div>

                                    {/* Plot point count badge */}
                                    <span className="rounded-full bg-neutral-bg px-2 py-0.5 text-[12px] font-medium text-ink-soft tabular-nums">
                                        {t('page.plotPointCount', {
                                            count: plotPoints.length,
                                        })}
                                    </span>
                                </div>
                            </div>

                            {/* Content + Detail Panel */}
                            <div className="flex min-h-0 flex-1">
                                <div className="flex-1 overflow-auto p-5">
                                    {activeTab === 'timeline' ? (
                                        <SwimLaneTimeline
                                            acts={acts}
                                            storylines={filteredStorylines}
                                            plotPoints={filteredPlotPoints}
                                            chapters={chapters}
                                            onSelectPlotPoint={(pp) =>
                                                setSelectedPlotPointId(pp.id)
                                            }
                                            onCreatePlotPoint={
                                                handleCreatePlotPoint
                                            }
                                            onAssignChapterAct={
                                                handleAssignChapterAct
                                            }
                                            onExportChapter={
                                                handleExportChapter
                                            }
                                        />
                                    ) : (
                                        <PlotPointList
                                            acts={acts}
                                            plotPoints={filteredPlotPoints}
                                            storylines={storylines}
                                            onSelectPlotPoint={(pp) =>
                                                setSelectedPlotPointId(pp.id)
                                            }
                                        />
                                    )}
                                </div>

                                {selectedPlotPoint && (
                                    <DetailPanel
                                        plotPoint={selectedPlotPoint}
                                        storylines={storylines}
                                        acts={acts}
                                        connections={connections}
                                        onClose={() =>
                                            setSelectedPlotPointId(null)
                                        }
                                        onUpdate={(data) =>
                                            handleUpdatePlotPoint(
                                                selectedPlotPoint.id,
                                                data,
                                            )
                                        }
                                    />
                                )}
                            </div>
                        </>
                    ) : (
                        <>
                            {/* Header bar — minimal for empty state */}
                            <div className="flex h-12 items-center border-b border-border px-5">
                                <div className="flex h-full items-stretch gap-4">
                                    <span className="flex items-center border-b-2 border-ink px-1 text-[13px] font-medium text-ink">
                                        {t('page.tabs.timeline')}
                                    </span>
                                    <span className="flex items-center border-b-2 border-transparent px-1 text-[13px] font-medium text-ink-faint">
                                        {t('page.tabs.list')}
                                    </span>
                                </div>
                            </div>

                            <PlotEmptyState
                                onSelectTemplate={setSelectedTemplate}
                            />
                        </>
                    )}
                </main>

                {hasActs && (
                    <ReadingOrderPanel
                        chapters={chapters}
                        storylines={storylines}
                        book={book}
                        onReorder={handleReadingOrderReorder}
                        onInterleave={handleInterleave}
                    />
                )}

                {selectedTemplate && (
                    <PlotWizardModal
                        book={book}
                        template={selectedTemplate}
                        storylines={storylines}
                        chapters={chapters}
                        onClose={() => setSelectedTemplate(null)}
                    />
                )}
            </div>
        </>
    );
}
