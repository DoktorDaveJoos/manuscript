import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import type { Book, Storyline } from '@/types/models';
import { importMethod } from '@/actions/App/Http/Controllers/BookController';
import { store } from '@/actions/App/Http/Controllers/ChapterController';

export default function ChapterEmpty({
    book,
}: {
    book: Book & { storylines: Pick<Storyline, 'id' | 'book_id' | 'name'>[] };
}) {
    const { t } = useTranslation('editor');
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
            <Head title={`${book.title} — ${t('empty.noChapters')}`} />
            <div className="flex min-h-screen flex-col items-center justify-center bg-surface pb-20">
                <div className="flex flex-col items-center gap-4">
                    <h1 className="font-serif text-[32px] leading-10 font-normal tracking-[-0.01em] text-ink">
                        {t('empty.title')}
                    </h1>
                    <p className="text-sm leading-6 text-ink-muted">
                        {t('empty.description')}
                    </p>
                </div>
                <div className="mt-10 flex items-center gap-4">
                    <Button
                        variant="primary"
                        size="lg"
                        type="button"
                        onClick={handleCreateChapter}
                    >
                        {t('empty.createFirst')}
                    </Button>
                    <Link
                        href={importMethod.url(book)}
                        className="rounded-md border border-border px-7 py-3 text-sm leading-[18px] font-medium text-ink-muted"
                    >
                        {t('empty.importManuscript')}
                    </Link>
                </div>
            </div>
        </>
    );
}
