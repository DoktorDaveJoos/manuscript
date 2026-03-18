import { Head, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import UpdateBanner from '@/components/ui/UpdateBanner';

export default function AppLayout({
    children,
    title,
}: PropsWithChildren<{ title?: string }>) {
    const { name } = usePage().props;

    return (
        <>
            <Head title={title} />
            <div className="flex h-screen flex-col bg-surface">
                <UpdateBanner />
                <div className="flex min-h-0 flex-1">
                    <aside className="flex w-64 shrink-0 flex-col border-r border-border-light bg-white dark:bg-surface-card">
                        <div className="flex h-14 items-center border-b border-border-light px-5">
                            <span className="text-sm font-semibold tracking-tight text-ink">
                                {name}
                            </span>
                        </div>
                        <nav className="flex-1 overflow-y-auto p-3">
                            {/* Book navigation — Phase 1 */}
                        </nav>
                    </aside>

                    <main className="flex flex-1 flex-col overflow-y-auto">
                        {children}
                    </main>
                </div>
            </div>
        </>
    );
}
