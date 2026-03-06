import Sidebar from '@/components/editor/Sidebar';
import type { Book, Storyline } from '@/types/models';
import { Head } from '@inertiajs/react';

export default function Canvas({ book }: { book: Book & { storylines?: Storyline[] } }) {
    const storylines = book.storylines ?? [];

    return (
        <>
            <Head title={`Canvas — ${book.title}`} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar book={book} storylines={storylines} />

                <main className="flex min-w-0 flex-1 flex-col items-center overflow-y-auto px-10 py-12">
                    <div className="flex w-[720px] flex-col gap-10">
                        <h1 className="font-serif text-[34px] leading-[40px] tracking-[-0.01em] text-ink">
                            Canvas
                        </h1>
                    </div>
                </main>
            </div>
        </>
    );
}
