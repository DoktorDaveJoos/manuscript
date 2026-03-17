import { Link, router, usePage } from '@inertiajs/react';
import { BookOpen, LayoutGrid, Settings, Waypoints } from 'lucide-react';
import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { index } from '@/actions/App/Http/Controllers/BookController';
import { show as showDashboard } from '@/actions/App/Http/Controllers/DashboardController';
import { index as indexPlot } from '@/actions/App/Http/Controllers/PlotController';
import { index as settingsIndex } from '@/actions/App/Http/Controllers/SettingsController';
import { store as storeStoryline } from '@/actions/App/Http/Controllers/StorylineController';
import { index as indexWiki } from '@/actions/App/Http/Controllers/WikiController';
import NavItem from '@/components/ui/NavItem';
import { createChapter, formatCompactCount } from '@/lib/utils';
import type { Book, Scene, Storyline } from '@/types/models';
import ChapterList from './ChapterList';
import TrashBin from './TrashBin';

const STORAGE_KEY = 'manuscript:sidebar-width';
const MIN_WIDTH = 200;
const MAX_WIDTH = 400;
const DEFAULT_WIDTH = 232;

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
    scenesVisible,
    onScenesVisibleChange,
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
    scenesVisible: boolean;
    onScenesVisibleChange: (v: boolean) => void;
    isFocusMode?: boolean;
}) {
    const { t } = useTranslation();
    const scrollContainerRef = useRef<HTMLDivElement>(null);
    const sidebarRef = useRef<HTMLElement>(null);

    const [width, setWidth] = useState(() => {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            const parsed = Number(stored);
            if (parsed >= MIN_WIDTH && parsed <= MAX_WIDTH) return parsed;
        }
        return DEFAULT_WIDTH;
    });
    const widthRef = useRef(width);
    useEffect(() => { widthRef.current = width; }, [width]);
    const dragCleanupRef = useRef<(() => void) | null>(null);

    useEffect(() => {
        return () => dragCleanupRef.current?.();
    }, []);

    const handleMouseDown = useCallback((e: React.MouseEvent) => {
        e.preventDefault();
        const startX = e.clientX;
        const startWidth = widthRef.current;

        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';

        const cleanup = () => {
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            document.removeEventListener('mousemove', handleMouseMove);
            document.removeEventListener('mouseup', handleMouseUp);
            dragCleanupRef.current = null;
        };

        const handleMouseMove = (e: MouseEvent) => {
            const delta = e.clientX - startX;
            const newWidth = Math.min(MAX_WIDTH, Math.max(MIN_WIDTH, startWidth + delta));
            widthRef.current = newWidth;
            if (sidebarRef.current) sidebarRef.current.style.width = `${newWidth}px`;
        };

        const handleMouseUp = () => {
            setWidth(widthRef.current);
            localStorage.setItem(STORAGE_KEY, String(widthRef.current));
            cleanup();
        };

        dragCleanupRef.current = cleanup;
        document.addEventListener('mousemove', handleMouseMove);
        document.addEventListener('mouseup', handleMouseUp);
    }, []);

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
        <aside
            ref={sidebarRef}
            className={`relative flex h-full shrink-0 flex-col overflow-hidden border-r border-[#F0EFED] bg-white transition-[width,opacity] duration-300 ${isFocusMode ? 'opacity-0' : ''}`}
            style={{ width: isFocusMode ? 0 : width }}
        >
            {/* Header */}
            <div className="flex items-center justify-between px-5 py-[18px]">
                <Link href={index.url()} className="text-[13px] font-semibold uppercase tracking-[0.06em] text-ink">
                    Manuscript
                </Link>
                <Link href={settingsIndex.url()} className="text-[#9CA3AF] hover:text-ink transition-colors">
                    <Settings size={18} />
                </Link>
            </div>

            {/* Nav */}
            <div className="flex flex-col gap-0.5 p-3">
                <NavItem
                    label={t('nav.dashboard')}
                    isActive={isDashboard}
                    href={showDashboard.url(book)}
                    icon={
                        <LayoutGrid size={16} className="shrink-0 text-[#B0B0B0]" />
                    }
                />
                <NavItem
                    label={t('nav.wiki')}
                    href={indexWiki.url(book)}
                    isActive={isWiki}
                    icon={
                        <BookOpen size={16} className="shrink-0 text-[#B0B0B0]" />
                    }
                />
                <NavItem
                    label={t('nav.plot')}
                    href={indexPlot.url(book)}
                    isActive={isPlot}
                    icon={
                        <Waypoints size={16} className="shrink-0 text-[#B0B0B0]" />
                    }
                />
            </div>

            {/* Chapter list */}
            <div ref={scrollContainerRef} onScroll={handleSidebarScroll} className="flex-1 overflow-y-auto px-3 py-2">
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
                <span className="text-[11px] text-[#B5B5B5]">{t('wordsCompact', { formatted: formatCompactCount(totalWords) })}</span>
                <span className="text-[11px] text-[#B5B5B5]">·</span>
                <span className="text-[11px] text-[#B5B5B5]">{t('chapters', { count: totalChapters })}</span>
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
