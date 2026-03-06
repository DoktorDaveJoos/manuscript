import { editor } from '@/actions/App/Http/Controllers/ChapterController';
import type { Book } from '@/types/models';
import { router } from '@inertiajs/react';
import BookCardMenu from './BookCardMenu';
import ProgressBar from './ProgressBar';

type BookWithCounts = Book & {
    chapters_count: number;
    draft_chapters_count: number;
    revised_chapters_count: number;
    final_chapters_count: number;
    chapters_sum_word_count: number | null;
    storylines?: { id: number; book_id: number; name: string }[];
};

function formatWords(count: number | null): string {
    if (!count) return '0 words';
    return `${count.toLocaleString('en-US')} words`;
}

function timeAgo(date: string): string {
    const diff = Date.now() - new Date(date).getTime();
    const hours = Math.floor(diff / 3600000);
    if (hours < 1) return 'Edited just now';
    if (hours < 24) return `Edited ${hours} hour${hours === 1 ? '' : 's'} ago`;
    const days = Math.floor(hours / 24);
    if (days < 30) return `Edited ${days} day${days === 1 ? '' : 's'} ago`;
    const months = Math.floor(days / 30);
    return `Edited ${months} month${months === 1 ? '' : 's'} ago`;
}

export default function BookCard({
    book,
    onRename,
    onDuplicate,
    onDelete,
}: {
    book: BookWithCounts;
    onRename: () => void;
    onDuplicate: () => void;
    onDelete: () => void;
}) {
    const storylineCount = book.storylines?.length ?? 0;
    const meta = [
        `${book.chapters_count} chapter${book.chapters_count === 1 ? '' : 's'}`,
        formatWords(book.chapters_sum_word_count),
        `${storylineCount} storyline${storylineCount === 1 ? '' : 's'}`,
    ].join(' \u00B7 ');

    return (
        <div
            onClick={() => router.visit(editor.url(book))}
            className="relative flex w-[400px] shrink-0 cursor-pointer flex-col gap-5 rounded-[10px] border border-border bg-surface-card p-8 transition-shadow hover:shadow-md"
        >
            <div className="absolute right-4 top-4">
                <BookCardMenu onRename={onRename} onDuplicate={onDuplicate} onDelete={onDelete} />
            </div>

            <div className="flex flex-col gap-1.5 pr-8">
                <h3 className="font-serif text-2xl leading-8 tracking-[-0.01em] text-ink">{book.title}</h3>
                <p className="text-[13px] leading-[18px] text-ink-muted">{meta}</p>
            </div>

            <ProgressBar
                counts={{
                    final: book.final_chapters_count,
                    revised: book.revised_chapters_count,
                    draft: book.draft_chapters_count,
                }}
            />

            <span className="text-xs leading-4 text-ink-faint">{timeAgo(book.updated_at)}</span>
        </div>
    );
}

export type { BookWithCounts };
