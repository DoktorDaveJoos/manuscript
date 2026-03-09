import { index } from '@/actions/App/Http/Controllers/BookController';
import { index as indexCanvas } from '@/actions/App/Http/Controllers/CanvasController';
import { index as indexWiki } from '@/actions/App/Http/Controllers/WikiController';
import { show as showDashboard } from '@/actions/App/Http/Controllers/DashboardController';
import { index as indexPlot } from '@/actions/App/Http/Controllers/PlotController';
import { appearance as settingsAppearance } from '@/actions/App/Http/Controllers/AppSettingsController';
import NavItem from '@/components/ui/NavItem';
import { useLicense } from '@/hooks/useLicense';
import { store as storeStoryline } from '@/actions/App/Http/Controllers/StorylineController';
import { createChapter, formatCompactCount } from '@/lib/utils';
import type { Book, Scene, Storyline } from '@/types/models';
import { Link, router, usePage } from '@inertiajs/react';
import { BookOpen, GearSix, Lock, Rectangle, SquaresFour, Strategy } from '@phosphor-icons/react';
import ChapterList from './ChapterList';
import TrashBin from './TrashBin';

export default function Sidebar({
    book,
    storylines,
    activeChapterId,
    activeChapterTitle,
    activeChapterWordCount,
    onBeforeNavigate,
    activeScenes,
    onSceneRename,
    onSceneDelete,
    onSceneReorder,
    onSceneAdd,
    scenesVisible,
    onScenesVisibleChange,
}: {
    book: Book;
    storylines: Storyline[];
    activeChapterId?: number;
    activeChapterTitle?: string;
    activeChapterWordCount?: number;
    onBeforeNavigate?: () => Promise<void>;
    activeScenes?: Scene[];
    onSceneRename?: (sceneId: number, newTitle: string) => void;
    onSceneDelete?: (sceneId: number) => void;
    onSceneReorder?: (orderedIds: number[]) => void;
    onSceneAdd?: (afterPosition: number) => Promise<void>;
    scenesVisible: boolean;
    onScenesVisibleChange: (v: boolean) => void;
}) {
    const { isActive: isLicensed } = useLicense();
    const currentUrl = usePage().url;
    const isDashboard = currentUrl.endsWith('/dashboard');
    const isWiki = currentUrl.includes('/wiki');
    const isPlot = currentUrl.endsWith('/plot');
    const isCanvas = currentUrl.endsWith('/canvas');

    const totalWords = storylines.reduce(
        (sum, s) => sum + (s.chapters?.reduce((c, ch) =>
            c + (ch.id === activeChapterId && activeChapterWordCount != null ? activeChapterWordCount : ch.word_count), 0) ?? 0),
        0,
    );
    const totalChapters = storylines.reduce((sum, s) => sum + (s.chapters?.length ?? 0), 0);

    const handleAddChapter = async (storylineId: number) => {
        await onBeforeNavigate?.();
        createChapter(book.id, storylineId, storylines);
    };

    const handleAddStoryline = async () => {
        await onBeforeNavigate?.();
        router.post(storeStoryline.url({ book: book.id }), { name: `Storyline ${storylines.length + 1}` });
    };

    return (
        <aside className="flex h-full w-60 shrink-0 flex-col border-r border-border-light bg-surface-card">
            {/* Header */}
            <div className="flex h-12 items-center justify-between border-b border-border-subtle px-5">
                <Link href={index.url()} className="text-[13px] font-semibold uppercase tracking-[0.05em] text-ink">
                    Manuscript
                </Link>
                <Link href={settingsAppearance.url()} className="text-ink-muted hover:text-ink transition-colors">
                    <GearSix size={16} weight="regular" />
                </Link>
            </div>

            {/* Nav */}
            <div className="flex flex-col gap-px px-2.5 py-3">
                <NavItem
                    label="Dashboard"
                    isActive={isDashboard}
                    href={showDashboard.url(book)}
                    icon={
                        <SquaresFour size={16} weight="regular" className="shrink-0" />
                    }
                />
                <NavItem
                    label="Wiki"
                    href={indexWiki.url(book)}
                    isActive={isWiki}
                    icon={
                        <BookOpen size={16} weight="regular" className="shrink-0" />
                    }
                />
                <NavItem
                    label="Plot"
                    href={indexPlot.url(book)}
                    isActive={isPlot}
                    icon={
                        <Strategy size={16} weight="regular" className="shrink-0" />
                    }
                />
                <NavItem
                    label="Canvas"
                    href={isLicensed ? indexCanvas.url(book) : undefined}
                    disabled={!isLicensed}
                    isActive={isCanvas}
                    icon={
                        <Rectangle size={16} weight="regular" className="shrink-0" />
                    }
                    suffix={
                        !isLicensed ? (
                            <Lock size={12} weight="regular" className="ml-auto shrink-0 text-ink-faint" />
                        ) : undefined
                    }
                />
            </div>

            {/* Separator */}
            <div className="mx-5 my-1 h-px bg-border-subtle" />

            {/* Chapter list */}
            <div className="flex-1 overflow-y-auto px-2.5 py-3">
                <ChapterList
                    bookTitle={book.title}
                    storylines={storylines}
                    bookId={book.id}
                    activeChapterId={activeChapterId}
                    activeChapterTitle={activeChapterTitle}
                    activeChapterWordCount={activeChapterWordCount}
                    onBeforeNavigate={onBeforeNavigate}
                    onAddChapter={handleAddChapter}
                    onAddStoryline={handleAddStoryline}
                    activeScenes={activeScenes}
                    onSceneRename={onSceneRename}
                    onSceneDelete={onSceneDelete}
                    onSceneReorder={onSceneReorder}
                    onSceneAdd={onSceneAdd}
                    scenesVisible={scenesVisible}
                    onScenesVisibleChange={onScenesVisibleChange}
                />
            </div>

            {/* Trash */}
            <TrashBin bookId={book.id} />

            {/* Footer */}
            <div className="flex items-center justify-between border-t border-border-subtle px-5 py-3.5">
                <span className="text-[11px] text-ink-faint">{formatCompactCount(totalWords)} words</span>
                <span className="text-[11px] text-ink-faint">{totalChapters} chapters</span>
            </div>
        </aside>
    );
}
