import { index } from '@/actions/App/Http/Controllers/BookController';
import { index as indexCanvas } from '@/actions/App/Http/Controllers/CanvasController';
import { index as indexCharacters } from '@/actions/App/Http/Controllers/CharacterController';
import { store } from '@/actions/App/Http/Controllers/ChapterController';
import { show as showDashboard } from '@/actions/App/Http/Controllers/DashboardController';
import { index as indexPlot } from '@/actions/App/Http/Controllers/PlotController';
import { about as settingsAbout } from '@/actions/App/Http/Controllers/SettingsController';
import NavItem from '@/components/ui/NavItem';
import { useLicense } from '@/hooks/useLicense';
import { formatCompactCount } from '@/lib/utils';
import type { Book, Storyline } from '@/types/models';
import { Link, router, usePage } from '@inertiajs/react';
import ChapterList from './ChapterList';

export default function Sidebar({
    book,
    storylines,
    activeChapterId,
    onBeforeNavigate,
}: {
    book: Book;
    storylines: Storyline[];
    activeChapterId?: number;
    onBeforeNavigate?: () => Promise<void>;
}) {
    const { isActive: isLicensed } = useLicense();
    const currentUrl = usePage().url;
    const isDashboard = currentUrl.endsWith('/dashboard');
    const isCharacters = currentUrl.endsWith('/characters');
    const isPlot = currentUrl.endsWith('/plot');
    const isCanvas = currentUrl.endsWith('/canvas');

    const totalWords = storylines.reduce(
        (sum, s) => sum + (s.chapters?.reduce((c, ch) => c + ch.word_count, 0) ?? 0),
        0,
    );
    const totalChapters = storylines.reduce((sum, s) => sum + (s.chapters?.length ?? 0), 0);

    const handleAddChapter = async (storylineId: number) => {
        await onBeforeNavigate?.();

        const storylineChapters = storylines.find((s) => s.id === storylineId)?.chapters?.length ?? 0;

        router.post(store.url(book), {
            title: `Chapter ${totalChapters + 1}`,
            storyline_id: storylineId,
        });
    };

    return (
        <aside className="flex h-full w-60 shrink-0 flex-col border-r border-border-light bg-surface">
            {/* Header */}
            <div className="flex items-center justify-between border-b border-border-subtle px-5 py-4">
                <Link href={index.url()} className="text-[13px] font-semibold uppercase tracking-[0.05em] text-ink">
                    Manuscript
                </Link>
                <Link href={settingsAbout.url()} className="text-ink-muted hover:text-ink transition-colors">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <circle cx="8" cy="8" r="2.5" stroke="currentColor" strokeWidth="1.5" />
                        <path d="M8 1v2M8 13v2M1 8h2M13 8h2M3.05 3.05l1.41 1.41M11.54 11.54l1.41 1.41M3.05 12.95l1.41-1.41M11.54 4.46l1.41-1.41" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                    </svg>
                </Link>
            </div>

            {/* Nav */}
            <div className="flex flex-col gap-px px-2.5 py-3">
                <NavItem
                    label="Dashboard"
                    isActive={isDashboard}
                    href={showDashboard.url(book)}
                    icon={
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" className="shrink-0">
                            <rect x="1" y="1" width="6" height="6" rx="1" stroke="currentColor" strokeWidth="1.5" />
                            <rect x="9" y="1" width="6" height="6" rx="1" stroke="currentColor" strokeWidth="1.5" />
                            <rect x="1" y="9" width="6" height="6" rx="1" stroke="currentColor" strokeWidth="1.5" />
                            <rect x="9" y="9" width="6" height="6" rx="1" stroke="currentColor" strokeWidth="1.5" />
                        </svg>
                    }
                />
                <NavItem
                    label="Characters"
                    href={indexCharacters.url(book)}
                    isActive={isCharacters}
                    icon={
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" className="shrink-0">
                            <circle cx="8" cy="5" r="3" stroke="currentColor" strokeWidth="1.5" />
                            <path d="M3 14c0-2.761 2.239-5 5-5s5 2.239 5 5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                        </svg>
                    }
                />
                <NavItem
                    label="Plot"
                    href={indexPlot.url(book)}
                    isActive={isPlot}
                    icon={
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" className="shrink-0">
                            <path d="M2 8c1.5-3 3-4.5 4.5-4.5S9 5 8 8s-1 4.5.5 4.5S12 11 14 8" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                        </svg>
                    }
                />
                <NavItem
                    label="Canvas"
                    href={isLicensed ? indexCanvas.url(book) : undefined}
                    disabled={!isLicensed}
                    isActive={isCanvas}
                    icon={
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" className="shrink-0">
                            <rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" strokeWidth="1.5" />
                        </svg>
                    }
                    suffix={
                        !isLicensed ? (
                            <svg width="12" height="12" viewBox="0 0 16 16" fill="none" className="ml-auto shrink-0 text-ink-faint">
                                <rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" strokeWidth="1.5" />
                                <path d="M5 7V5a3 3 0 016 0v2" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                            </svg>
                        ) : undefined
                    }
                />
            </div>

            {/* Separator */}
            <div className="mx-5 my-1 h-px bg-border-subtle" />

            {/* Chapter list */}
            <div className="flex-1 overflow-y-auto px-2.5 py-3">
                <ChapterList
                    storylines={storylines}
                    bookId={book.id}
                    activeChapterId={activeChapterId}
                    onBeforeNavigate={onBeforeNavigate}
                    onAddChapter={handleAddChapter}
                />
            </div>

            {/* Footer */}
            <div className="flex items-center justify-between border-t border-border-subtle px-5 py-3.5">
                <span className="text-[11px] text-ink-faint">{formatCompactCount(totalWords)} words</span>
                <span className="text-[11px] text-ink-faint">{totalChapters} chapters</span>
            </div>
        </aside>
    );
}
