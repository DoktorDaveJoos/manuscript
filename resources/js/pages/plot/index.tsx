import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Sidebar from '@/components/editor/Sidebar';
import ActColumn from '@/components/plot/ActColumn';
import ActContextMenu from '@/components/plot/ActContextMenu';
import BeatContextMenu from '@/components/plot/BeatContextMenu';
import BeatDetailPanel from '@/components/plot/BeatDetailPanel';
import PlotEmptyState from '@/components/plot/PlotEmptyState';
import PlotPointContextMenu from '@/components/plot/PlotPointContextMenu';
import PlotWizardModal from '@/components/plot/PlotWizardModal';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import type { PlotTemplate } from '@/lib/plot-templates';
import type {
    Act,
    Beat,
    BeatStatus,
    Book,
    PlotPoint,
    Storyline,
} from '@/types/models';

type PlotPageProps = {
    book: Book;
    storylines: Storyline[];
    acts: Act[];
    plotPoints: (PlotPoint & {
        beats?: (Beat & {
            chapters?: { id: number; title: string; reader_order: number }[];
        })[];
    })[];
};

export default function Plot({
    book,
    storylines,
    acts,
    plotPoints,
}: PlotPageProps) {
    const { t } = useTranslation('plot');
    const sidebarStorylines = useSidebarStorylines();
    const [selectedBeatId, setSelectedBeatId] = useState<number | null>(null);
    const [selectedTemplate, setSelectedTemplate] =
        useState<PlotTemplate | null>(null);
    const [contextMenu, setContextMenu] = useState<{
        beatId: number;
        position: { x: number; y: number };
    } | null>(null);
    const [actContextMenu, setActContextMenu] = useState<{
        act: Act;
        position: { x: number; y: number };
    } | null>(null);
    const [plotPointContextMenu, setPlotPointContextMenu] = useState<{
        plotPoint: PlotPoint;
        position: { x: number; y: number };
    } | null>(null);
    const hasActs = acts.length > 0;

    // Build a beat lookup map once for O(1) access
    const beatMap = useMemo(() => {
        const map = new Map<number, Beat & { plot_point?: PlotPoint }>();
        for (const pp of plotPoints) {
            for (const beat of pp.beats ?? []) {
                map.set(beat.id, { ...beat, plot_point: pp });
            }
        }
        return map;
    }, [plotPoints]);

    const selectedBeat = selectedBeatId
        ? (beatMap.get(selectedBeatId) ?? null)
        : null;

    const plotPointsByAct = useMemo(() => {
        const map = new Map<number, typeof plotPoints>();
        for (const pp of plotPoints) {
            if (pp.act_id !== null && pp.act_id !== undefined) {
                const existing = map.get(pp.act_id) ?? [];
                existing.push(pp);
                map.set(pp.act_id, existing);
            }
        }
        return map;
    }, [plotPoints]);

    const handleCreateBeat = useCallback(
        (plotPointId: number) => {
            router.post(
                `/books/${book.id}/plot-points/${plotPointId}/beats`,
                { title: t('beat.addBeat') },
                { preserveScroll: true },
            );
        },
        [book.id, t],
    );

    const handleCreatePlotPoint = useCallback(
        (actId: number) => {
            router.post(
                `/books/${book.id}/plot-points`,
                {
                    title: t('page.newPlotPointTitle', 'New plot point'),
                    type: 'setup',
                    act_id: actId,
                },
                { preserveScroll: true },
            );
        },
        [book.id, t],
    );

    const handleDeleteAct = useCallback(
        (actId: number) => {
            router.delete(`/books/${book.id}/acts/${actId}`, {
                preserveScroll: true,
            });
            setActContextMenu(null);
        },
        [book.id],
    );

    const handleDeletePlotPoint = useCallback(
        (plotPointId: number) => {
            router.delete(`/books/${book.id}/plot-points/${plotPointId}`, {
                preserveScroll: true,
            });
            setPlotPointContextMenu(null);
        },
        [book.id],
    );

    const handleBeatStatusChange = useCallback(
        (beatId: number, status: BeatStatus) => {
            router.patch(
                `/books/${book.id}/beats/${beatId}/status`,
                { status },
                { preserveScroll: true },
            );
        },
        [book.id],
    );

    const handleDeleteBeat = useCallback(
        (beatId: number) => {
            if (selectedBeatId === beatId) setSelectedBeatId(null);
            router.delete(`/books/${book.id}/beats/${beatId}`, {
                preserveScroll: true,
            });
            setContextMenu(null);
        },
        [book.id, selectedBeatId],
    );

    const handleBeatContextMenu = useCallback(
        (beat: Beat, position: { x: number; y: number }) => {
            setContextMenu({ beatId: beat.id, position });
        },
        [],
    );

    const handleActContextMenu = useCallback(
        (act: Act, position: { x: number; y: number }) => {
            setActContextMenu({ act, position });
        },
        [],
    );

    const handlePlotPointContextMenu = useCallback(
        (plotPoint: PlotPoint, position: { x: number; y: number }) => {
            setPlotPointContextMenu({ plotPoint, position });
        },
        [],
    );

    const handleAddAct = useCallback(() => {
        const nextNumber =
            acts.length > 0 ? Math.max(...acts.map((a) => a.number)) + 1 : 1;
        router.post(
            `/books/${book.id}/acts`,
            { number: nextNumber, title: `Act ${nextNumber}` },
            { preserveScroll: true },
        );
    }, [acts, book.id]);

    const contextMenuBeat = contextMenu
        ? (beatMap.get(contextMenu.beatId) ?? null)
        : null;

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
                            <div className="flex h-12 items-center justify-between border-b border-border px-6">
                                <h1 className="text-[15px] font-semibold text-ink">
                                    {t('page.tabs.timeline', 'Plot')}
                                </h1>
                                <button
                                    type="button"
                                    onClick={handleAddAct}
                                    className="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-[12px] font-medium transition-opacity hover:opacity-80"
                                    style={{
                                        backgroundColor: '#F1EEEA',
                                        color: '#737373',
                                    }}
                                >
                                    <Plus size={14} />
                                    {t('act.addAct')}
                                </button>
                            </div>

                            {/* Act columns + detail panel */}
                            <div className="flex min-h-0 flex-1">
                                <div className="flex flex-1 overflow-x-auto">
                                    {acts.map((act, index) => (
                                        <ActColumn
                                            key={act.id}
                                            act={act}
                                            colorIndex={index}
                                            plotPoints={
                                                plotPointsByAct.get(act.id) ??
                                                []
                                            }
                                            selectedBeatId={selectedBeatId}
                                            isLast={index === acts.length - 1}
                                            onSelectBeat={(beat) =>
                                                setSelectedBeatId(beat.id)
                                            }
                                            onCreateBeat={handleCreateBeat}
                                            onCreatePlotPoint={
                                                handleCreatePlotPoint
                                            }
                                            onBeatContextMenu={
                                                handleBeatContextMenu
                                            }
                                            onActContextMenu={
                                                handleActContextMenu
                                            }
                                            onPlotPointContextMenu={
                                                handlePlotPointContextMenu
                                            }
                                        />
                                    ))}
                                </div>

                                {selectedBeat && (
                                    <BeatDetailPanel
                                        key={selectedBeat.id}
                                        beat={selectedBeat}
                                        bookId={book.id}
                                        onClose={() => setSelectedBeatId(null)}
                                    />
                                )}
                            </div>
                        </>
                    ) : (
                        <>
                            <div className="flex h-12 items-center border-b border-border px-6">
                                <h1 className="text-[15px] font-semibold text-ink">
                                    {t('page.tabs.timeline', 'Plot')}
                                </h1>
                            </div>
                            <PlotEmptyState
                                onSelectTemplate={setSelectedTemplate}
                            />
                        </>
                    )}
                </main>

                {contextMenu && contextMenuBeat && (
                    <BeatContextMenu
                        beat={contextMenuBeat}
                        bookId={book.id}
                        storylines={storylines}
                        position={contextMenu.position}
                        onClose={() => setContextMenu(null)}
                        onStatusChange={handleBeatStatusChange}
                        onDelete={handleDeleteBeat}
                    />
                )}

                {actContextMenu && (
                    <ActContextMenu
                        act={actContextMenu.act}
                        position={actContextMenu.position}
                        onClose={() => setActContextMenu(null)}
                        onDelete={handleDeleteAct}
                    />
                )}

                {plotPointContextMenu && (
                    <PlotPointContextMenu
                        plotPoint={plotPointContextMenu.plotPoint}
                        position={plotPointContextMenu.position}
                        onClose={() => setPlotPointContextMenu(null)}
                        onDelete={handleDeletePlotPoint}
                    />
                )}

                {selectedTemplate && (
                    <PlotWizardModal
                        book={book}
                        template={selectedTemplate}
                        onClose={() => setSelectedTemplate(null)}
                    />
                )}
            </div>
        </>
    );
}
