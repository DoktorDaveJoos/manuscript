import { Head, Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';
import { show as showDashboard } from '@/actions/App/Http/Controllers/DashboardController';
import NavItem from '@/components/ui/NavItem';
import SectionLabel from '@/components/ui/SectionLabel';
import UpdateBanner from '@/components/ui/UpdateBanner';

type ActiveSection =
    | 'general'
    | 'writing-style'
    | 'prose-rules'
    | 'publishing'
    | 'cover';

type BookRef = { id: number; title: string };

interface Props {
    activeSection: ActiveSection;
    book: BookRef;
    title?: string;
}

export default function BookSettingsLayout({
    children,
    activeSection,
    book,
    title,
}: PropsWithChildren<Props>) {
    const { t } = useTranslation('settings');

    return (
        <>
            <Head title={title ?? t('bookSettings.title')} />
            <div className="flex h-screen flex-col overflow-hidden bg-surface">
                <UpdateBanner />
                <div className="flex min-h-0 flex-1">
                    {/* Sidebar */}
                    <aside className="flex h-full w-60 shrink-0 flex-col border-r border-border-light bg-surface-sidebar">
                        {/* Header — Back link */}
                        <div className="px-5 py-4">
                            <Link
                                href={showDashboard.url(book.id)}
                                className="flex items-center gap-1.5 text-[12px] font-medium text-ink-muted transition-colors hover:text-ink"
                            >
                                <svg
                                    width="12"
                                    height="12"
                                    viewBox="0 0 16 16"
                                    fill="none"
                                    className="shrink-0"
                                >
                                    <path
                                        d="M10 3L5 8l5 5"
                                        stroke="currentColor"
                                        strokeWidth="1.5"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                </svg>
                                {t('back')}
                            </Link>
                        </div>

                        <div className="px-2.5">
                            <SectionLabel
                                variant="section"
                                className="mb-1.5 block truncate px-2.5"
                            >
                                {book.title}
                            </SectionLabel>
                            <div className="flex flex-col gap-0.5">
                                <NavItem
                                    label={t('section.general')}
                                    href={`/books/${book.id}/settings/general`}
                                    isActive={activeSection === 'general'}
                                />
                                <NavItem
                                    label={t('section.writingStyle')}
                                    href={`/books/${book.id}/settings/writing-style`}
                                    isActive={activeSection === 'writing-style'}
                                />
                                <NavItem
                                    label={t('section.prosePassRules')}
                                    href={`/books/${book.id}/settings/prose-rules`}
                                    isActive={activeSection === 'prose-rules'}
                                />
                                <NavItem
                                    label={t('section.publishing')}
                                    href={`/books/${book.id}/settings/publishing`}
                                    isActive={activeSection === 'publishing'}
                                />
                                <NavItem
                                    label={t('section.cover')}
                                    href={`/books/${book.id}/settings/cover`}
                                    isActive={activeSection === 'cover'}
                                />
                            </div>
                        </div>
                    </aside>

                    {/* Main content */}
                    <main className="flex flex-1 flex-col items-center overflow-y-auto px-12 py-10">
                        <div className="w-full max-w-[640px]">{children}</div>
                    </main>
                </div>
            </div>
        </>
    );
}
