import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { duplicate } from '@/actions/App/Http/Controllers/BookController';
import BookCard from '@/components/onboarding/BookCard';
import type {BookWithCounts} from '@/components/onboarding/BookCard';
import CreateBookDialog from '@/components/onboarding/CreateBookDialog';
import DeleteBookDialog from '@/components/onboarding/DeleteBookDialog';
import NewBookCard from '@/components/onboarding/NewBookCard';
import RenameBookDialog from '@/components/onboarding/RenameBookDialog';
import Button from '@/components/ui/Button';
import OnboardingLayout from '@/layouts/OnboardingLayout';

type DialogState =
    | { type: 'create' }
    | { type: 'rename'; book: BookWithCounts }
    | { type: 'delete'; book: BookWithCounts }
    | null;

function EmptyState({ onCreateClick }: { onCreateClick: () => void }) {
    const { t } = useTranslation('onboarding');

    return (
        <div className="flex flex-1 flex-col items-center justify-center pb-20">
            <div className="flex flex-col items-center gap-4">
                <h1 className="font-serif text-5xl leading-[58px] tracking-[-0.02em] text-ink">{t('emptyState.title')}</h1>
                <p className="text-[15px] leading-6 text-ink-muted">
                    {t('emptyState.description')}
                </p>
            </div>
            <Button variant="primary" size="lg" type="button" onClick={onCreateClick} className="mt-10">
                {t('emptyState.createButton')}
            </Button>
        </div>
    );
}

function BookLibrary({
    books,
    onCreateClick,
    onRename,
    onDuplicate,
    onDelete,
}: {
    books: BookWithCounts[];
    onCreateClick: () => void;
    onRename: (book: BookWithCounts) => void;
    onDuplicate: (book: BookWithCounts) => void;
    onDelete: (book: BookWithCounts) => void;
}) {
    const { t } = useTranslation('onboarding');

    return (
        <div className="flex flex-1 flex-col items-center px-10 py-20 gap-12">
            <h1 className="font-serif text-4xl leading-[44px] tracking-[-0.01em] text-ink">{t('bookLibrary.title')}</h1>
            <div className="flex flex-wrap justify-center gap-6">
                {books.map((book) => (
                    <BookCard
                        key={book.id}
                        book={book}
                        onRename={() => onRename(book)}
                        onDuplicate={() => onDuplicate(book)}
                        onDelete={() => onDelete(book)}
                    />
                ))}
                <NewBookCard onClick={onCreateClick} />
            </div>
        </div>
    );
}

export default function BooksIndex({ books }: { books: BookWithCounts[] }) {
    const [dialog, setDialog] = useState<DialogState>(null);

    return (
        <>
            {books.length === 0 ? (
                <EmptyState onCreateClick={() => setDialog({ type: 'create' })} />
            ) : (
                <BookLibrary
                    books={books}
                    onCreateClick={() => setDialog({ type: 'create' })}
                    onRename={(book) => setDialog({ type: 'rename', book })}
                    onDuplicate={(book) => router.post(duplicate.url(book))}
                    onDelete={(book) => setDialog({ type: 'delete', book })}
                />
            )}

            {dialog?.type === 'create' && <CreateBookDialog onClose={() => setDialog(null)} />}
            {dialog?.type === 'rename' && <RenameBookDialog book={dialog.book} onClose={() => setDialog(null)} />}
            {dialog?.type === 'delete' && <DeleteBookDialog book={dialog.book} onClose={() => setDialog(null)} />}
        </>
    );
}

BooksIndex.layout = (page: React.ReactNode) => <OnboardingLayout title="Your Books">{page}</OnboardingLayout>;
