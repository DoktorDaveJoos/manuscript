import { router } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Legacy chapter page — the server now redirects chapters.show to the
 * pane-based editor, but this client-side redirect acts as a safety net
 * in case the page is ever rendered directly (e.g. cached Inertia response).
 */
export default function ChapterShow({
    book,
    chapter,
}: {
    book: { id: number };
    chapter: { id: number };
}) {
    useEffect(() => {
        router.visit(`/books/${book.id}/editor?panes=${chapter.id}`, {
            replace: true,
        });
    }, [book.id, chapter.id]);

    return null;
}
