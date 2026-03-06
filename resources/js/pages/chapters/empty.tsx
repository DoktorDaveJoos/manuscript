import { importMethod } from '@/actions/App/Http/Controllers/BookController';
import { store } from '@/actions/App/Http/Controllers/ChapterController';
import type { Book, Storyline } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';

export default function ChapterEmpty({
    book,
}: {
    book: Book & { storylines: Pick<Storyline, 'id' | 'book_id' | 'name'>[] };
}) {
    const firstStorylineId = book.storylines[0]?.id;

    function handleCreateChapter() {
        if (!firstStorylineId) return;

        router.post(store.url(book), {
            title: 'Chapter 1',
            storyline_id: firstStorylineId,
        });
    }

    return (
        <>
            <Head title={`${book.title} — No Chapters`} />
            <div className="flex min-h-screen flex-col items-center justify-center bg-surface pb-20">
                <div className="flex flex-col items-center gap-4">
                    <h1 className="font-serif text-4xl leading-[44px] tracking-[-0.01em] text-ink">No chapters yet</h1>
                    <p className="text-[15px] leading-6 text-ink-muted">
                        Create your first chapter or import an existing manuscript.
                    </p>
                </div>
                <div className="mt-10 flex items-center gap-4">
                    <button
                        type="button"
                        onClick={handleCreateChapter}
                        className="rounded-md bg-ink px-7 py-3 text-sm font-medium leading-[18px] text-surface"
                    >
                        Create first chapter
                    </button>
                    <Link
                        href={importMethod.url(book)}
                        className="rounded-md border border-border px-7 py-3 text-sm font-medium leading-[18px] text-ink-muted"
                    >
                        Import manuscript
                    </Link>
                </div>
            </div>
        </>
    );
}
