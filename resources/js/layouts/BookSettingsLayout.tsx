import { Head } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';
import {
    cover,
    general,
    proofreading,
    proseRules,
    publishing,
    writingStyle,
} from '@/actions/App/Http/Controllers/BookSettingsController';
import Sidebar from '@/components/editor/Sidebar';
import SideNav from '@/components/ui/SideNav';
import UpdateBanner from '@/components/ui/UpdateBanner';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';

type ActiveSection =
    | 'general'
    | 'writing-style'
    | 'prose-rules'
    | 'proofreading'
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
    const storylines = useSidebarStorylines();

    const sections = [
        {
            key: 'general',
            label: t('section.general'),
            href: general.url(book.id),
        },
        {
            key: 'writing-style',
            label: t('section.writingStyle'),
            href: writingStyle.url(book.id),
        },
        {
            key: 'prose-rules',
            label: t('section.prosePassRules'),
            href: proseRules.url(book.id),
        },
        {
            key: 'proofreading',
            label: t('sidebar.proofreading'),
            href: proofreading.url(book.id),
        },
        {
            key: 'publishing',
            label: t('section.publishing'),
            href: publishing.url(book.id),
        },
        {
            key: 'cover',
            label: t('section.cover'),
            href: cover.url(book.id),
        },
    ];

    return (
        <>
            <Head title={title ?? t('bookSettings.title')} />
            <div className="flex h-screen flex-col overflow-hidden bg-surface">
                <UpdateBanner />
                <div className="flex min-h-0 flex-1">
                    <Sidebar book={book} storylines={storylines} />

                    <SideNav
                        items={sections}
                        activeKey={activeSection}
                        label={t('bookSettings.title')}
                    />

                    <main className="flex min-w-0 flex-1 flex-col items-center overflow-y-auto px-12 py-10">
                        <div className="w-full max-w-[640px]">{children}</div>
                    </main>
                </div>
            </div>
        </>
    );
}
