import { interleave as interleaveChapters, reorder as reorderChapters } from '@/actions/App/Http/Controllers/ChapterController';
import { store as storePlotPoint, update as updatePlotPoint } from '@/actions/App/Http/Controllers/PlotPointController';
import Sidebar from '@/components/editor/Sidebar';
import AiActionSidebar from '@/components/plot/AiActionSidebar';
import DetailPanel from '@/components/plot/DetailPanel';
import PlotPointList from '@/components/plot/PlotPointList';
import ReadingOrderPanel from '@/components/plot/ReadingOrderPanel';
import SwimLaneTimeline from '@/components/plot/SwimLaneTimeline';
import TensionArc, { type TensionData } from '@/components/plot/TensionArc';
import { useAiFeatures } from '@/hooks/useAiFeatures';
import { getXsrfToken } from '@/lib/csrf';
import type { Act, Book, Chapter, PlotPoint, PlotPointConnection, Storyline } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import { Filter, List, ListOrdered, PanelLeft, Rows3 } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

type ChapterCol = {
    id: number;
    title: string;
    reader_order: number;
    act_id: number;
    storyline_id: number;
    tension_score: number | null;
};

type PlotPageProps = {
    book: Book & { storylines: Storyline[] };
    storylines: Storyline[];
    acts: (Act & { chapters: ChapterCol[] })[];
    plotPoints: PlotPoint[];
    connections: PlotPointConnection[];
    chapters: Chapter[];
};

type RightPanel = 'reading-order' | 'ai';

type Tab = 'timeline' | 'list';

export default function Plot({ book, storylines, acts, plotPoints, connections, chapters }: PlotPageProps) {
    const { t } = useTranslation('plot');
    const [activeTab, setActiveTab] = useState<Tab>('timeline');
    const [selectedPlotPointId, setSelectedPlotPointId] = useState<number | null>(null);
    const selectedPlotPoint = selectedPlotPointId ? plotPoints.find((pp) => pp.id === selectedPlotPointId) ?? null : null;
    const [storylineFilter, setStorylineFilter] = useState<number | null>(null);
    const [rightPanel, setRightPanel] = useState<RightPanel>('reading-order');
    const ai = useAiFeatures();

    const allChapterCount = acts.reduce((sum, act) => sum + act.chapters.length, 0);

    const serverTensionData = useMemo<TensionData[] | null>(() => {
        const chapters = acts.flatMap((act) => act.chapters);
        const withScores = chapters.filter((ch) => ch.tension_score !== null);
        if (withScores.length === 0) return null;
        return withScores.map((ch) => ({
            chapter_id: ch.id,
            reader_order: ch.reader_order,
            tension_score: ch.tension_score as number,
            title: ch.title,
        }));
    }, [acts]);

    const [aiTensionData, setAiTensionData] = useState<TensionData[] | null>(null);
    const tensionData = aiTensionData ?? serverTensionData;
    const [tensionArcVisible, setTensionArcVisible] = useState(serverTensionData !== null);

    const filteredPlotPoints = useMemo(
        () => (storylineFilter ? plotPoints.filter((pp) => pp.storyline_id === storylineFilter) : plotPoints),
        [plotPoints, storylineFilter],
    );

    const filteredStorylines = useMemo(
        () => (storylineFilter ? storylines.filter((s) => s.id === storylineFilter) : storylines),
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

    const handleUpdatePlotPoint = (id: number, data: Record<string, unknown>) => {
        router.patch(updatePlotPoint.url({ book: book.id, plotPoint: id }), data, {
            preserveScroll: true,
        });
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

    return (
        <>
            <Head title={`Plot — ${book.title}`} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar book={book} storylines={book.storylines ?? []} scenesVisible={false} onScenesVisibleChange={() => {}} />

                <main className="flex min-w-0 flex-1 flex-col overflow-hidden">
                    {/* Header bar */}
                    <div className="flex items-center justify-between border-b border-[#ECEAE4] px-5 py-2.5">
                        <div className="flex items-center gap-1">
                            <button
                                onClick={() => setActiveTab('timeline')}
                                className={`flex items-center gap-1.5 rounded px-3 py-1.5 text-[13px] font-medium transition-colors ${
                                    activeTab === 'timeline'
                                        ? 'bg-[#F0EEEA] text-[#2D2A26]'
                                        : 'text-[#8A857D] hover:text-[#5A574F]'
                                }`}
                            >
                                <Rows3 size={16} />
                                {t('page.tabs.timeline')}
                            </button>
                            <button
                                onClick={() => setActiveTab('list')}
                                className={`flex items-center gap-1.5 rounded px-3 py-1.5 text-[13px] font-medium transition-colors ${
                                    activeTab === 'list'
                                        ? 'bg-[#F0EEEA] text-[#2D2A26]'
                                        : 'text-[#8A857D] hover:text-[#5A574F]'
                                }`}
                            >
                                <List size={16} />
                                {t('page.tabs.list')}
                            </button>
                        </div>

                        <div className="flex items-center gap-2">
                            {/* Storyline filter */}
                            <div className="relative">
                                <select
                                    value={storylineFilter ?? ''}
                                    onChange={(e) => setStorylineFilter(e.target.value ? Number(e.target.value) : null)}
                                    className="appearance-none rounded border border-[#ECEAE4] bg-white py-1.5 pl-7 pr-3 text-[13px] text-[#5A574F] focus:outline-none focus:ring-1 focus:ring-[#C8B88A]"
                                >
                                    <option value="">{t('page.allStorylines')}</option>
                                    {storylines.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.name}
                                        </option>
                                    ))}
                                </select>
                                <Filter
                                    size={14}
                                    className="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-[#8A857D]"
                                />
                            </div>

                            {/* Reading Order toggle */}
                            <button
                                onClick={() => setRightPanel((prev) => (prev === 'reading-order' ? 'ai' : 'reading-order'))}
                                className={`rounded p-1.5 transition-colors hover:bg-[#F0EEEA] hover:text-[#5A574F] ${rightPanel === 'reading-order' ? 'bg-[#F0EEEA] text-[#5A574F]' : 'text-[#8A857D]'}`}
                                title={t('readingOrder.header')}
                            >
                                <ListOrdered size={18} />
                            </button>

                            {/* AI sidebar toggle */}
                            {ai.visible && (
                                <button
                                    onClick={() => setRightPanel((prev) => (prev === 'ai' ? 'reading-order' : 'ai'))}
                                    className={`rounded p-1.5 transition-colors hover:bg-[#F0EEEA] hover:text-[#5A574F] ${rightPanel === 'ai' ? 'bg-[#F0EEEA] text-[#5A574F]' : 'text-[#8A857D]'}`}
                                    title={t('page.toggleAiSidebar')}
                                >
                                    <PanelLeft size={18} />
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Content + Detail Panel */}
                    <div className="flex min-h-0 flex-1">
                        <div className="flex-1 overflow-auto p-5">
                            {activeTab === 'timeline' && tensionArcVisible && tensionData && (
                                <TensionArc
                                    data={tensionData}
                                    chapterCount={allChapterCount}
                                    labelWidth={120}
                                    columnWidth={160}
                                    onCollapse={() => setTensionArcVisible(false)}
                                />
                            )}
                            {activeTab === 'timeline' ? (
                                <SwimLaneTimeline
                                    acts={acts}
                                    storylines={filteredStorylines}
                                    plotPoints={filteredPlotPoints}
                                    chapters={chapters}
                                    onSelectPlotPoint={(pp) => setSelectedPlotPointId(pp.id)}
                                    onCreatePlotPoint={handleCreatePlotPoint}
                                />
                            ) : (
                                <PlotPointList
                                    acts={acts}
                                    plotPoints={filteredPlotPoints}
                                    storylines={storylines}
                                    onSelectPlotPoint={(pp) => setSelectedPlotPointId(pp.id)}
                                />
                            )}
                        </div>

                        {selectedPlotPoint && (
                            <DetailPanel
                                plotPoint={selectedPlotPoint}
                                storylines={storylines}
                                acts={acts}
                                connections={connections}
                                onClose={() => setSelectedPlotPointId(null)}
                                onUpdate={(data) => handleUpdatePlotPoint(selectedPlotPoint.id, data)}
                            />
                        )}
                    </div>
                </main>

                {rightPanel === 'reading-order' ? (
                    <ReadingOrderPanel
                        chapters={chapters}
                        storylines={storylines}
                        bookId={book.id}
                        isOpen={true}
                        onToggle={() => setRightPanel('ai')}
                        onReorder={handleReadingOrderReorder}
                        onInterleave={handleInterleave}
                    />
                ) : (
                    <AiActionSidebar
                        book={book}
                        isOpen={true}
                        onToggle={() => setRightPanel('reading-order')}
                        onTensionArcGenerated={(data) => {
                            setAiTensionData(data);
                            setTensionArcVisible(true);
                        }}
                    />
                )}
            </div>
        </>
    );
}
