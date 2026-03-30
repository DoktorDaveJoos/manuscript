import { Head } from '@inertiajs/react';
import Sidebar from '@/components/editor/Sidebar';
import PageHeader from '@/components/ui/PageHeader';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import type { Book } from '@/types/models';

export default function Canvas({ book }: { book: Book }) {
    const storylines = useSidebarStorylines();

    return (
        <>
            <Head title={`Canvas — ${book.title}`} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar book={book} storylines={storylines} />

                <main className="flex min-w-0 flex-1 flex-col items-center overflow-y-auto px-10 py-12">
                    <div className="flex w-[720px] flex-col gap-10">
                        <PageHeader title="Canvas" />
                    </div>
                </main>
            </div>
        </>
    );
}
