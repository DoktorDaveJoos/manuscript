import { index as aiSettingsIndex } from '@/actions/App/Http/Controllers/AiSettingsController';
import { index as booksIndex } from '@/actions/App/Http/Controllers/BookController';
import { index as bookSettingsIndex } from '@/actions/App/Http/Controllers/BookSettingsController';
import { index as indexCanvas } from '@/actions/App/Http/Controllers/CanvasController';
import { index as indexWiki } from '@/actions/App/Http/Controllers/WikiController';
import { show as showDashboard } from '@/actions/App/Http/Controllers/DashboardController';
import { index as indexPlot } from '@/actions/App/Http/Controllers/PlotController';
import { about } from '@/actions/App/Http/Controllers/SettingsController';
import NavItem from '@/components/ui/NavItem';
import BookSwitcher from '@/components/settings/BookSwitcher';
import { Head, Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren, useEffect, useState, useRef, useCallback } from 'react';

type ActiveSection = 'book-settings' | 'license-ai' | 'about';

type BookRef = { id: number; title: string };

const BOOK_SECTIONS = ['ai-model', 'writing-style', 'prose-pass-rules', 'export'] as const;

interface Props {
    activeSection: ActiveSection;
    book?: BookRef | null;
    title?: string;
}

export default function SettingsLayout({ children, activeSection, book, title }: PropsWithChildren<Props>) {
    const { books_list } = usePage<{ books_list: BookRef[] }>().props;
    const isBookSettings = activeSection === 'book-settings';
    const [visibleSection, setVisibleSection] = useState<string>('ai-model');
    const mainRef = useRef<HTMLElement>(null);

    useEffect(() => {
        if (!isBookSettings || !mainRef.current) return;

        const observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (entry.isIntersecting) {
                        setVisibleSection(entry.target.id);
                    }
                }
            },
            { root: mainRef.current, rootMargin: '-10% 0px -80% 0px' },
        );

        for (const id of BOOK_SECTIONS) {
            const el = document.getElementById(id);
            if (el) observer.observe(el);
        }

        return () => observer.disconnect();
    }, [isBookSettings]);

    const scrollTo = useCallback((id: string) => {
        const el = document.getElementById(id);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }, []);

    return (
        <>
            <Head title={title ?? 'Settings'} />
            <div className="flex h-screen overflow-hidden bg-surface">
                {/* Sidebar */}
                <aside className="flex h-full w-60 shrink-0 flex-col border-r border-border bg-surface">
                    {/* Header */}
                    <div className="flex items-center justify-between px-5 py-4">
                        <Link href={booksIndex.url()} className="text-[12px] font-semibold uppercase tracking-[0.08em] text-ink">
                            Manuscript
                        </Link>
                        <Link href={aiSettingsIndex.url()} className="text-ink-muted hover:text-ink transition-colors">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                                <circle cx="8" cy="8" r="2.5" stroke="currentColor" strokeWidth="1.5" />
                                <path d="M8 1v2M8 13v2M1 8h2M13 8h2M3.05 3.05l1.41 1.41M11.54 11.54l1.41 1.41M3.05 12.95l1.41-1.41M11.54 4.46l1.41-1.41" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                            </svg>
                        </Link>
                    </div>

                    {/* Zone 1: Book nav (only when book context exists) */}
                    {book && (
                        <>
                            <div className="flex flex-col gap-0.5 px-2.5">
                                <NavItem
                                    label="Dashboard"
                                    href={showDashboard.url(book)}
                                    icon={
                                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" className="shrink-0">
                                            <rect x="1" y="1" width="6" height="6" rx="1" stroke="currentColor" strokeWidth="1.5" />
                                            <rect x="9" y="1" width="6" height="6" rx="1" stroke="currentColor" strokeWidth="1.5" />
                                            <rect x="1" y="9" width="6" height="6" rx="1" stroke="currentColor" strokeWidth="1.5" />
                                            <rect x="9" y="9" width="6" height="6" rx="1" stroke="currentColor" strokeWidth="1.5" />
                                        </svg>
                                    }
                                />
                                <NavItem
                                    label="Wiki"
                                    href={indexWiki.url(book)}
                                    icon={
                                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" className="shrink-0">
                                            <path d="M2 3.5C2 2.672 2.672 2 3.5 2h2C6.328 2 7 2.672 7 3.5v9c0 .828-.672 1.5-1.5 1.5h-2C2.672 14 2 13.328 2 12.5v-9Z" stroke="currentColor" strokeWidth="1.5" />
                                            <path d="M9 3.5C9 2.672 9.672 2 10.5 2h2c.828 0 1.5.672 1.5 1.5v9c0 .828-.672 1.5-1.5 1.5h-2C9.672 14 9 13.328 9 12.5v-9Z" stroke="currentColor" strokeWidth="1.5" />
                                        </svg>
                                    }
                                />
                                <NavItem
                                    label="Plot"
                                    href={indexPlot.url(book)}
                                    icon={
                                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" className="shrink-0">
                                            <path d="M2 8c1.5-3 3-4.5 4.5-4.5S9 5 8 8s-1 4.5.5 4.5S12 11 14 8" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                                        </svg>
                                    }
                                />
                                <NavItem
                                    label="Canvas"
                                    href={indexCanvas.url(book)}
                                    icon={
                                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" className="shrink-0">
                                            <rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" strokeWidth="1.5" />
                                        </svg>
                                    }
                                />
                            </div>
                            <div className="mx-2.5 my-3 border-t border-border" />
                        </>
                    )}

                    {/* Zone 2: App settings */}
                    <div className="px-2.5">
                        <span className="mb-1.5 block px-2.5 text-[11px] font-medium uppercase tracking-[0.08em] text-ink-faint">
                            App
                        </span>
                        <div className="flex flex-col gap-0.5">
                            <NavItem
                                label="License & AI"
                                href={aiSettingsIndex.url()}
                                isActive={activeSection === 'license-ai'}
                            />
                            <NavItem
                                label="About"
                                href={about.url()}
                                isActive={activeSection === 'about'}
                            />
                        </div>
                    </div>

                    {/* Zone 3: Book-specific settings (only when book context exists) */}
                    {book && (
                        <>
                            <div className="mx-2.5 my-3 border-t border-border" />
                            <div className="flex flex-1 flex-col overflow-y-auto px-2.5">
                                <BookSwitcher
                                    currentBook={book}
                                    books={books_list ?? []}
                                />
                                <div className="mt-1.5 flex flex-col gap-0.5">
                                    {isBookSettings ? (
                                        <>
                                            <NavItem
                                                label="AI Model"
                                                onClick={() => scrollTo('ai-model')}
                                                isActive={visibleSection === 'ai-model'}
                                            />
                                            <NavItem
                                                label="Writing Style"
                                                onClick={() => scrollTo('writing-style')}
                                                isActive={visibleSection === 'writing-style'}
                                            />
                                            <NavItem
                                                label="Prose Pass Rules"
                                                onClick={() => scrollTo('prose-pass-rules')}
                                                isActive={visibleSection === 'prose-pass-rules'}
                                            />
                                            <NavItem
                                                label="Export"
                                                onClick={() => scrollTo('export')}
                                                isActive={visibleSection === 'export'}
                                            />
                                        </>
                                    ) : (
                                        <>
                                            <NavItem
                                                label="AI Model"
                                                href={bookSettingsIndex.url(book)}
                                                isActive={false}
                                            />
                                            <NavItem
                                                label="Writing Style"
                                                href={bookSettingsIndex.url(book) + '#writing-style'}
                                                isActive={false}
                                            />
                                            <NavItem
                                                label="Prose Pass Rules"
                                                href={bookSettingsIndex.url(book) + '#prose-pass-rules'}
                                                isActive={false}
                                            />
                                            <NavItem
                                                label="Export"
                                                href={bookSettingsIndex.url(book) + '#export'}
                                                isActive={false}
                                            />
                                        </>
                                    )}
                                </div>
                            </div>
                        </>
                    )}
                </aside>

                {/* Main content */}
                <main ref={mainRef} className="flex flex-1 flex-col items-center overflow-y-auto px-10 py-12">
                    <div className="w-full max-w-[640px]">{children}</div>
                </main>
            </div>
        </>
    );
}
