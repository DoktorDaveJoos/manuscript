import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { update } from '@/actions/App/Http/Controllers/BookNotesController';
import NotesPanel from '@/components/editor/NotesPanel';
import Sidebar from '@/components/editor/Sidebar';
import { useSidebarStorylines } from '@/hooks/useSidebarStorylines';
import type { Book } from '@/types/models';

type Props = {
    book: Pick<Book, 'id' | 'title' | 'notes' | 'notes_version'>;
};

export default function Notes({ book }: Props) {
    const { t } = useTranslation('editor');
    const storylines = useSidebarStorylines();

    return (
        <>
            <Head title={`${t('notesResearch.pageTitle')} — ${book.title}`} />
            <div className="flex h-screen overflow-hidden bg-surface">
                <Sidebar book={book} storylines={storylines} />

                <main className="flex min-w-0 flex-1 flex-col overflow-hidden">
                    <NotesPanel
                        bookId={book.id}
                        initialNotes={book.notes ?? null}
                        initialVersion={book.notes_version ?? 0}
                        saveUrl={update.url(book)}
                        title={t('notesResearch.notebookTitle')}
                        placeholder={t('notesResearch.placeholder')}
                        variant="page"
                        maxLength={5_000_000}
                    />
                </main>
            </div>
        </>
    );
}
