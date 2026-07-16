import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowUpFromLine,
    BookOpen,
    BookType,
    LayoutGrid,
    LibraryBig,
    NotebookTabs,
    PanelLeftClose,
    PanelLeftOpen,
    Settings,
    Settings2,
    Sparkles,
    Waypoints,
} from 'lucide-react';
import { useCallback, useLayoutEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { index } from '@/actions/App/Http/Controllers/BookController';
import { show as showDesign } from '@/actions/App/Http/Controllers/BookDesignController';
import { index as indexNotes } from '@/actions/App/Http/Controllers/BookNotesController';
import {
    exportMethod,
    index as bookSettingsIndex,
} from '@/actions/App/Http/Controllers/BookSettingsController';
import { show as showDashboard } from '@/actions/App/Http/Controllers/DashboardController';
import { index as editorialReviewIndex } from '@/actions/App/Http/Controllers/EditorialReviewController';
import { index as indexPlot } from '@/actions/App/Http/Controllers/PlotController';
import { index as settingsIndex } from '@/actions/App/Http/Controllers/SettingsController';
import { store as storeStoryline } from '@/actions/App/Http/Controllers/StorylineController';
import { index as indexWiki } from '@/actions/App/Http/Controllers/WikiController';
import Button from '@/components/ui/Button';
import NavGroup from '@/components/ui/NavGroup';
import NavItem from '@/components/ui/NavItem';
import { useResizablePanel } from '@/hooks/useResizablePanel';
import { createChapter, formatCompactCount } from '@/lib/utils';
import type { Book, Scene, Storyline } from '@/types/models';
import ChapterList from './ChapterList';
import TrashBin from './TrashBin';

let savedScrollTop = 0;

export default function Sidebar({
    book,
    storylines,
    activeChapterId,
    activeChapterTitle,
    activeChapterWordCount,
    onBeforeNavigate,
    activeScenes,
    onChapterRename,
    onSceneRename,
    onSceneDelete,
    onSceneReorder,
    onSceneAdd,
    scenesVisible = false,
    onScenesVisibleChange = () => {},
    isFocusMode = false,
    onChapterNavigate,
    onOpenInNewPane,
}: {
    book: Pick<Book, 'id' | 'title'>;
    storylines: Storyline[];
    activeChapterId?: number;
    activeChapterTitle?: string;
    activeChapterWordCount?: number;
    onBeforeNavigate?: () => Promise<void>;
    activeScenes?: Scene[];
    onChapterRename?: (chapterId: number, newTitle: string) => void;
    onSceneRename?: (sceneId: number, newTitle: string) => void;
    onSceneDelete?: (sceneId: number) => void;
    onSceneReorder?: (orderedIds: number[]) => void;
    onSceneAdd?: (afterPosition: number) => Promise<void>;
    scenesVisible?: boolean;
    onScenesVisibleChange?: (v: boolean) => void;
    isFocusMode?: boolean;
    onChapterNavigate?: (chapterId: number) => void;
    onOpenInNewPane?: (chapterId: number) => void;
}) {
    const { t } = useTranslation();
    const { t: tEditor } = useTranslation('editor');
    const scrollContainerRef = useRef<HTMLDivElement>(null);

    const {
        width,
        isCollapsed,
        toggleCollapsed,
        panelRef: sidebarRef,
        handleMouseDown,
    } = useResizablePanel({
        storageKey: 'manuscript:sidebar-width',
        minWidth: 200,
        maxWidth: 400,
        defaultWidth: 232,
        collapsible: true,
        collapsedWidth: 48,
        collapseThreshold: 160,
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
    const isDashboard =
        currentUrl.endsWith('/dashboard') && !currentUrl.includes('/ai/');
    const isWiki = currentUrl.includes('/wiki');
    const isNotes = currentUrl.endsWith('/notes');
    const isPlot = currentUrl.endsWith('/plot');
    const isAi = currentUrl.includes('/ai/');
    const isExport = currentUrl.includes('/settings/export');
    const isDesign = /\/books\/\d+\/design/.test(currentUrl);
    const isBookSettings =
        /\/books\/\d+\/settings/.test(currentUrl) && !isExport;

    const storyItems = [
        {
            label: t('nav.notesResearch'),
            href: indexNotes.url(book),
            isActive: isNotes,
            Icon: NotebookTabs,
        },
        {
            label: t('nav.wiki'),
            href: indexWiki.url(book),
            isActive: isWiki,
            Icon: BookOpen,
        },
        {
            label: t('nav.plot'),
            href: indexPlot.url(book),
            isActive: isPlot,
            Icon: Waypoints,
        },
        {
            label: t('nav.ai'),
            href: editorialReviewIndex.url(book),
            isActive: isAi,
            Icon: Sparkles,
        },
    ];
    const publishItems = [
        {
            label: t('nav.design'),
            href: showDesign.url(book),
            isActive: isDesign,
            Icon: BookType,
        },
        {
            label: t('nav.export'),
            href: exportMethod.url(book),
            isActive: isExport,
            Icon: ArrowUpFromLine,
        },
    ];

    const renderNavItems = (items: typeof storyItems) =>
        items.map(({ label, href, isActive, Icon }) => (
            <NavItem
                key={label}
                label={label}
                href={href}
                isActive={isActive}
                iconOnly={isCollapsed}
                icon={<Icon size={16} className="shrink-0 text-ink-faint" />}
            />
        ));

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
        createChapter(book.id, storylineId, storylines, tEditor);
    };

    const handleAddStoryline = async () => {
        await onBeforeNavigate?.();
        router.post(storeStoryline.url({ book: book.id }), {
            name: tEditor('chapterList.storylineDefault', {
                number: storylines.length + 1,
            }),
        });
    };

    return (
        <aside
            ref={sidebarRef}
            data-state={isCollapsed ? 'collapsed' : 'expanded'}
            className={`relative flex h-full shrink-0 flex-col overflow-hidden border-r border-border-light bg-surface-sidebar transition-[width,opacity] duration-300 ${isFocusMode ? 'opacity-0' : ''}`}
            style={{ width: isFocusMode ? 0 : width }}
        >
            {/* Header */}
            {isCollapsed ? (
                <div className="flex items-center justify-center py-[18px]">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={toggleCollapsed}
                        title={`${book.title} — ${t('sidebar.expand')}`}
                        className="size-6 text-ink-faint transition-colors hover:text-ink"
                    >
                        <PanelLeftOpen size={16} />
                    </Button>
                </div>
            ) : (
                <div className="flex items-start justify-between gap-3 px-5 py-[18px]">
                    <div className="min-w-0">
                        <Link
                            href={index.url()}
                            className="block text-[13px] font-semibold tracking-[0.06em] text-ink uppercase"
                        >
                            Manuscript
                        </Link>
                        <p
                            data-sidebar-book-title
                            title={book.title}
                            className="mt-0.5 truncate text-xs text-ink-muted italic"
                        >
                            {book.title}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={toggleCollapsed}
                        title={t('sidebar.collapse')}
                        className="size-6 text-ink-faint transition-colors hover:text-ink"
                    >
                        <PanelLeftClose size={16} />
                    </Button>
                </div>
            )}

            {/* Nav */}
            <div
                className={
                    isCollapsed
                        ? 'flex flex-col items-center gap-3 px-2 py-1'
                        : 'flex flex-col gap-3 p-3'
                }
            >
                {/* General Nav */}
                <div
                    className={
                        isCollapsed
                            ? 'flex flex-col items-center gap-0.5'
                            : 'flex flex-col gap-0.5'
                    }
                >
                    {!isCollapsed && (
                        <span className="px-2 pb-0.5 text-[11px] font-medium tracking-widest text-ink-faint uppercase">
                            {t('nav.general')}
                        </span>
                    )}
                    <NavItem
                        label={t('nav.settings')}
                        href={settingsIndex.url({
                            query: { from: currentUrl },
                        })}
                        iconOnly={isCollapsed}
                        icon={
                            <Settings
                                size={16}
                                className="shrink-0 text-ink-faint"
                            />
                        }
                    />
                    <NavItem
                        label={t('nav.library')}
                        href={index.url()}
                        iconOnly={isCollapsed}
                        icon={
                            <LibraryBig
                                size={16}
                                className="shrink-0 text-ink-faint"
                            />
                        }
                    />
                </div>

                {/* Book Nav */}
                <div
                    className={
                        isCollapsed
                            ? 'flex flex-col items-center gap-0.5'
                            : 'flex flex-col gap-0.5'
                    }
                >
                    {!isCollapsed && (
                        <span className="px-2 pb-0.5 text-[11px] font-medium tracking-widest text-ink-faint uppercase">
                            {t('nav.book')}
                        </span>
                    )}
                    <NavItem
                        label={t('nav.dashboard')}
                        isActive={isDashboard}
                        href={showDashboard.url(book)}
                        iconOnly={isCollapsed}
                        icon={
                            <LayoutGrid
                                size={16}
                                className="shrink-0 text-ink-faint"
                            />
                        }
                    />
                    <NavItem
                        label={t('nav.bookSettings')}
                        href={bookSettingsIndex.url(book)}
                        isActive={isBookSettings}
                        iconOnly={isCollapsed}
                        icon={
                            <Settings2
                                size={16}
                                className="shrink-0 text-ink-faint"
                            />
                        }
                    />
                    {isCollapsed ? (
                        <>
                            {renderNavItems(storyItems)}
                            {renderNavItems(publishItems)}
                        </>
                    ) : (
                        <>
                            <NavGroup
                                label={t('nav.story')}
                                storageKey="manuscript:nav-group-story"
                                defaultOpen
                                containsActive={
                                    isNotes || isWiki || isPlot || isAi
                                }
                                testId="nav-group-story"
                            >
                                {renderNavItems(storyItems)}
                            </NavGroup>
                            <NavGroup
                                label={t('nav.publish')}
                                storageKey="manuscript:nav-group-publish"
                                containsActive={isDesign || isExport}
                                testId="nav-group-publish"
                            >
                                {renderNavItems(publishItems)}
                            </NavGroup>
                        </>
                    )}
                </div>
            </div>

            {/* Chapter list */}
            {!isCollapsed && (
                <div className="flex min-h-0 flex-1 flex-col">
                    <ChapterList
                        storylines={storylines}
                        bookId={book.id}
                        activeChapterId={activeChapterId}
                        activeChapterTitle={activeChapterTitle}
                        activeChapterWordCount={activeChapterWordCount}
                        onBeforeNavigate={onBeforeNavigate}
                        onChapterNavigate={onChapterNavigate}
                        onOpenInNewPane={onOpenInNewPane}
                        onAddChapter={handleAddChapter}
                        onAddStoryline={handleAddStoryline}
                        activeScenes={activeScenes}
                        onChapterRename={onChapterRename}
                        onSceneRename={onSceneRename}
                        onSceneDelete={onSceneDelete}
                        onSceneReorder={onSceneReorder}
                        onSceneAdd={onSceneAdd}
                        scenesVisible={scenesVisible}
                        onScenesVisibleChange={onScenesVisibleChange}
                        scrollContainerRef={scrollContainerRef}
                        onScroll={handleSidebarScroll}
                    />
                </div>
            )}

            {/* Trash */}
            {!isCollapsed && <TrashBin bookId={book.id} />}

            {/* Footer */}
            {!isCollapsed && (
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
            )}

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
