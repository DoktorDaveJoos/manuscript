import { index as booksIndex } from '@/actions/App/Http/Controllers/BookController';
import BookSwitcher from '@/components/settings/BookSwitcher';
import LanguageSelector from '@/components/ui/LanguageSelector';
import NavItem from '@/components/ui/NavItem';
import UpdateBanner from '@/components/ui/UpdateBanner';
import { Head, Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';

type ActiveSection = 'appearance' | 'license' | 'ai-providers' | 'writing-style' | 'prose-pass-rules' | 'export';

type BookRef = { id: number; title: string };

interface Props {
    activeSection: ActiveSection;
    book?: BookRef | null;
    title?: string;
}

export default function SettingsLayout({ children, activeSection, book, title }: PropsWithChildren<Props>) {
    const { t } = useTranslation('settings');
    const { books_list } = usePage<{ books_list: BookRef[] }>().props;

    return (
        <>
            <Head title={title ?? t('title')} />
            <div className="flex h-screen flex-col overflow-hidden bg-surface">
                <UpdateBanner />
                <div className="flex min-h-0 flex-1">
                {/* Sidebar */}
                <aside className="flex h-full w-60 shrink-0 flex-col border-r border-border bg-surface">
                    {/* Header — Back link + Language selector */}
                    <div className="flex items-center justify-between px-5 py-4">
                        <Link
                            href={book ? `/books/${book.id}/dashboard` : booksIndex.url()}
                            className="flex items-center gap-1.5 text-[12px] font-medium text-ink-muted transition-colors hover:text-ink"
                        >
                            <svg width="12" height="12" viewBox="0 0 16 16" fill="none" className="shrink-0">
                                <path d="M10 3L5 8l5 5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                            {t('back')}
                        </Link>
                        <LanguageSelector />
                    </div>

                    {/* App settings */}
                    <div className="px-2.5">
                        <span className="mb-1.5 block px-2.5 text-[11px] font-medium uppercase tracking-[0.08em] text-ink-faint">
                            {t('section.app')}
                        </span>
                        <div className="flex flex-col gap-0.5">
                            <NavItem
                                label={t('section.appearance')}
                                href="/settings/appearance"
                                isActive={activeSection === 'appearance'}
                            />
                            <NavItem
                                label={t('section.license')}
                                href="/settings/license"
                                isActive={activeSection === 'license'}
                            />
                            <NavItem
                                label={t('section.aiProviders')}
                                href="/settings/ai"
                                isActive={activeSection === 'ai-providers'}
                            />
                        </div>
                    </div>

                    {/* Book-specific settings */}
                    {book && (
                        <>
                            <div className="mx-2.5 my-3 border-t border-border" />
                            <div className="flex flex-1 flex-col overflow-y-auto px-2.5">
                                <BookSwitcher
                                    currentBook={book}
                                    books={books_list ?? []}
                                />
                                <div className="mt-1.5 flex flex-col gap-0.5">
                                    <NavItem
                                        label={t('section.writingStyle')}
                                        href={`/books/${book.id}/settings/writing-style`}
                                        isActive={activeSection === 'writing-style'}
                                    />
                                    <NavItem
                                        label={t('section.prosePassRules')}
                                        href={`/books/${book.id}/settings/prose-pass-rules`}
                                        isActive={activeSection === 'prose-pass-rules'}
                                    />
                                    <NavItem
                                        label={t('section.export')}
                                        href={`/books/${book.id}/settings/export`}
                                        isActive={activeSection === 'export'}
                                    />
                                </div>
                            </div>
                        </>
                    )}
                </aside>

                {/* Main content */}
                <main className="flex flex-1 flex-col items-center overflow-y-auto px-10 py-12">
                    <div className="w-full max-w-[640px]">{children}</div>
                </main>
                </div>
            </div>
        </>
    );
}
