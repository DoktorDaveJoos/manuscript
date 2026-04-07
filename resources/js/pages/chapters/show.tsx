import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { editor } from '@/actions/App/Http/Controllers/ChapterController';

/**
 * Safety net for cached Inertia responses — the server now redirects
 * chapters.show to the pane-based editor.
 */
export default function ChapterShow({
    book,
    chapter,
}: {
    book: { id: number };
    chapter: { id: number };
}) {
    useEffect(() => {
        router.visit(
            editor.url(
                { book: book.id },
                { query: { panes: String(chapter.id) } },
            ),
            { replace: true },
        );
    }, [book.id, chapter.id]);

    return null;
}
