import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowUpFromLine,
    BookOpen,
    LayoutGrid,
    Settings,
    Waypoints,
} from 'lucide-react';
import { useCallback, useLayoutEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import NavItem from '@/components/ui/NavItem';
import { useResizablePanel } from '@/hooks/useResizablePanel';
import { createChapter, formatCompactCount } from '@/lib/utils';
import type { Book, Scene, Storyline } from '@/types/models';
import ChapterList from './ChapterList';
import TrashBin from './TrashBin';
import { index } from '@/actions/App/Http/Controllers/BookController';
import { exportMethod } from '@/actions/App/Http/Controllers/BookSettingsController';
import { show as showDashboard } from '@/actions/App/Http/Controllers/DashboardController';
import { index as indexPlot } from '@/actions/App/Http/Controllers/PlotController';
import { index as settingsIndex } from '@/actions/App/Http/Controllers/SettingsController';
import { store as storeStoryline } from '@/actions/App/Http/Controllers/StorylineController';
import { index as indexWiki } from '@/actions/App/Http/Controllers/WikiController';

let savedScrollTop = 0;

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
    scenesVisible = false,
    onScenesVisibleChange = () => {},
    isFocusMode = false,
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
    scenesVisible?: boolean;
    onScenesVisibleChange?: (v: boolean) => void;
    isFocusMode?: boolean;
}) {
    const { t } = useTranslation();
    const scrollContainerRef = useRef<HTMLDivElement>(null);

    const {
        width,
        panelRef: sidebarRef,
        handleMouseDown,
    } = useResizablePanel({
        storageKey: 'manuscript:sidebar-width',
        minWidth: 200,
        maxWidth: 400,
        defaultWidth: 232,
    });

    useLayoutEffect(() => {
        if (scrollContainerRef.current && savedScrollTop > 0) {
            scrollContainerRef.current.scrollTop = savedScrollTop;
        }
    }, []);

    const handleSidebarScroll = useCallback(() => {
        if (scrollContainerRef.current) {
            savedScrollTop = scrollContainerRef.current.scrollTop;
        }
    }, []);

    const page = usePage();
    const currentUrl = page.url;
    const isDashboard = currentUrl.endsWith('/dashboard');
    const isWiki = currentUrl.includes('/wiki');
    const isPlot = currentUrl.endsWith('/plot');
    const isExport = currentUrl.includes('/settings/export');

    const totalWords = storylines.reduce(
        (sum, s) =>
            sum +
            (s.chapters?.reduce(
                (c, ch) =>
                    c +
                    (ch.id === activeChapterId && activeChapterWordCount != null
                        ? activeChapterWordCount
                        : ch.word_count),
                0,
            ) ?? 0),
        0,
    );
    const totalChapters = storylines.reduce(
        (sum, s) => sum + (s.chapters?.length ?? 0),
        0,
    );

    const handleAddChapter = async (storylineId: number) => {
        await onBeforeNavigate?.();
        createChapter(book.id, storylineId, storylines);
    };

    const handleAddStoryline = async () => {
        await onBeforeNavigate?.();
        router.post(storeStoryline.url({ book: book.id }), {
            name: `Storyline ${storylines.length + 1}`,
        });
    };

    return (
        <aside
            ref={sidebarRef}
            className={`relative flex h-full shrink-0 flex-col overflow-hidden border-r border-border-light bg-surface-sidebar transition-[width,opacity] duration-300 ${isFocusMode ? 'opacity-0' : ''}`}
            style={{ width: isFocusMode ? 0 : width }}
        >
            {/* Header */}
            <div className="flex items-center justify-between px-5 py-[18px]">
                <Link
                    href={index.url()}
                    className="text-[13px] font-semibold tracking-[0.06em] text-ink uppercase"
                >
                    Manuscript
                </Link>
                <Link
                    href={settingsIndex.url({ query: { from: currentUrl } })}
                    className="text-ink-faint transition-colors hover:text-ink"
                >
                    <Settings size={16} />
                </Link>
            </div>

            {/* Nav */}
            <div className="flex flex-col gap-0.5 p-3">
                <NavItem
                    label={t('nav.dashboard')}
                    isActive={isDashboard}
                    href={showDashboard.url(book)}
                    icon={
                        <LayoutGrid
                            size={16}
                            className="shrink-0 text-ink-faint"
                        />
                    }
                />
                <NavItem
                    label={t('nav.wiki')}
                    href={indexWiki.url(book)}
                    isActive={isWiki}
                    icon={
                        <BookOpen
                            size={16}
                            className="shrink-0 text-ink-faint"
                        />
                    }
                />
                <NavItem
                    label={t('nav.plot')}
                    href={indexPlot.url(book)}
                    isActive={isPlot}
                    icon={
                        <Waypoints
                            size={16}
                            className="shrink-0 text-ink-faint"
                        />
                    }
                />
                <NavItem
                    label={t('nav.export')}
                    href={exportMethod.url(book)}
                    isActive={isExport}
                    icon={
                        <ArrowUpFromLine
                            size={16}
                            className="shrink-0 text-ink-faint"
                        />
                    }
                    suffix={
                        <span className="ml-auto rounded-full bg-ink px-1.5 py-1 text-[11px] leading-none font-medium text-surface">
                            {t('preview')}
                        </span>
                    }
                />
            </div>

            {/* Chapter list */}
            <div
                ref={scrollContainerRef}
                onScroll={handleSidebarScroll}
                className="flex-1 overflow-y-auto px-3 py-2"
            >
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
            <div className="flex items-center gap-3 px-5 py-3.5">
                <span className="text-[11px] text-ink-faint">
                    {t('wordsCompact', {
                        formatted: formatCompactCount(totalWords),
                    })}
                </span>
                <span className="text-[11px] text-ink-faint">·</span>
                <span className="text-[11px] text-ink-faint">
                    {t('chapters', { count: totalChapters })}
                </span>
            </div>

            {/* Resize handle */}
            {!isFocusMode && (
                <div
                    onMouseDown={handleMouseDown}
                    className="group absolute inset-y-0 -right-1 z-10 w-2 cursor-col-resize"
                >
                    <div className="absolute inset-y-0 right-[3px] w-px bg-transparent transition-colors group-hover:bg-ink/20" />
                </div>
            )}
        </aside>
    );
}
