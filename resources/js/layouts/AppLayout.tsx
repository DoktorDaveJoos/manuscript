import UpdateBanner from '@/components/ui/UpdateBanner';
import { Head, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

export default function AppLayout({ children, title }: PropsWithChildren<{ title?: string }>) {
    const { name } = usePage().props;

    return (
        <>
            <Head title={title} />
            <div className="flex h-screen flex-col bg-gray-50">
                <UpdateBanner />
                <div className="flex min-h-0 flex-1">
                    <aside className="flex w-64 shrink-0 flex-col border-r border-gray-200 bg-surface">
                        <div className="flex h-14 items-center border-b border-gray-200 px-5">
                            <span className="text-sm font-semibold tracking-tight text-gray-900">
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
